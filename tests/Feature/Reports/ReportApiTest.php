<?php

namespace Tests\Feature\Reports;

use App\Enums\TransactionStatus;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\LaundryWebsiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private LaundryPackage $kg;

    private LaundryPackage $item;

    protected function setUp(): void
    {
        parent::setUp();

        // Selasa, 24 November 2026, 10:00 WITA
        $this->travelTo(Carbon::parse('2026-11-24 10:00:00', 'Asia/Makassar'));

        $this->admin = User::factory()->admin()->create();
        $this->kg = LaundryPackage::factory()->create(['name' => 'Reguler', 'price' => 8000]);
        $this->item = LaundryPackage::factory()->perItem()->create(['name' => 'Bedcover', 'price' => 30000]);
    }

    /* ------------------------------ Pembantu ------------------------------ */

    private function completed(LaundryPackage $package, string $completedAt, float|int $quantity = 1, array $extra = []): LaundryTransaction
    {
        return LaundryTransaction::factory()
            ->forPackage($package)
            ->status(TransactionStatus::Completed)
            ->create(array_merge([
                'quantity' => $quantity,
                'received_at' => Carbon::parse($completedAt)->subDay(),
                'ready_at' => Carbon::parse($completedAt)->subHour(),
                'completed_at' => $completedAt,
                'completed_by' => $this->admin->id,
            ], $extra));
    }

    /**
     * Pemasukan yang dihitung (total):
     *   hari ini     : #1 20.000 + #2 12.000                         = 32.000
     *   bulan ini    : + #3 60.000                                   = 92.000
     *   tahun ini    : + #4 24.000                                   = 116.000
     *   2025 - 2026  : + #5 8.000                                    = 124.000
     * Tidak dihitung: dibatalkan, belum selesai, selesai tanpa tanggal, dan yang dihapus.
     */
    private function seedBaseline(): void
    {
        $this->completed($this->kg, '2026-11-24 09:15:00', 2.5);    // #1  20.000
        $this->completed($this->kg, '2026-11-24 00:00:00', 1.5);    // #2  12.000 (batas awal hari)
        $this->completed($this->item, '2026-11-10 14:00:00', 2);    // #3  60.000
        $this->completed($this->kg, '2026-10-31 23:59:59', 3);      // #4  24.000 (bulan lalu)
        $this->completed($this->kg, '2025-12-31 23:59:59', 1);      // #5   8.000 (tahun lalu)

        LaundryTransaction::factory()->forPackage($this->kg)
            ->status(TransactionStatus::Cancelled)->create(['quantity' => 5, 'cancelled_at' => now()]);
        LaundryTransaction::factory()->forPackage($this->kg)
            ->status(TransactionStatus::Ready)->create(['quantity' => 4]);
        LaundryTransaction::factory()->forPackage($this->kg)
            ->status(TransactionStatus::Completed)->create(['quantity' => 7]); // completed_at kosong
        $this->completed($this->kg, '2026-11-24 08:00:00', 9)->delete();       // soft delete
    }

    private function report(array $query = [])
    {
        return $this->actingAs($this->admin)->getJson('/api/admin/reports?'.http_build_query($query));
    }

    /* ------------------------------ Akses ------------------------------ */

    private const ENDPOINTS = [
        '/api/admin/reports',
        '/api/admin/reports/transactions',
        '/api/admin/reports/print',
        '/api/admin/reports/export',
    ];

    public function test_guests_are_rejected(): void
    {
        foreach (self::ENDPOINTS as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    public function test_employees_cannot_access_reports(): void
    {
        $employee = User::factory()->create();

        foreach (self::ENDPOINTS as $url) {
            $this->actingAs($employee)->getJson($url)
                ->assertForbidden()
                ->assertJsonPath('message', 'Anda tidak memiliki izin untuk mengakses fitur ini.');
        }
    }

    public function test_inactive_admin_is_rejected(): void
    {
        $inactive = User::factory()->admin()->inactive()->create();

        $this->actingAs($inactive)->getJson('/api/admin/reports')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    /* ------------------------------ Ringkasan ------------------------------ */

    public function test_today_summary_counts_only_completed_transactions(): void
    {
        $this->seedBaseline();

        $response = $this->report(['period' => 'today'])
            ->assertOk()
            ->assertJsonPath('data.period.period', 'today')
            ->assertJsonPath('data.period.date_from', '2026-11-24')
            ->assertJsonPath('data.period.date_to', '2026-11-24')
            ->assertJsonPath('data.period.granularity', 'hour')
            ->assertJsonPath('data.summary.transaction_count', 2);

        $summary = $response->json('data.summary');

        $this->assertEquals(32000, $summary['total_income']);
        $this->assertEquals(16000, $summary['average_transaction']);
        $this->assertEquals(4, $summary['total_weight_kg']);
        $this->assertEquals(0, $summary['total_items']);
    }

    public function test_this_month_summary_separates_kilograms_from_items(): void
    {
        $this->seedBaseline();

        $response = $this->report(['period' => 'this_month'])
            ->assertOk()
            ->assertJsonPath('data.summary.transaction_count', 3);

        $summary = $response->json('data.summary');

        $this->assertEquals(92000, $summary['total_income']);
        $this->assertEquals(30666.67, $summary['average_transaction']);
        $this->assertEquals(4, $summary['total_weight_kg']);
        $this->assertEquals(2, $summary['total_items']);
    }

    public function test_default_period_is_this_month(): void
    {
        $this->seedBaseline();

        $this->report()
            ->assertOk()
            ->assertJsonPath('data.period.period', 'this_month')
            ->assertJsonPath('data.period.date_from', '2026-11-01')
            ->assertJsonPath('data.period.date_to', '2026-11-30')
            ->assertJsonPath('data.summary.transaction_count', 3);
    }

    public function test_this_year_and_custom_ranges(): void
    {
        $this->seedBaseline();

        $year = $this->report(['period' => 'this_year'])
            ->assertOk()
            ->assertJsonPath('data.summary.transaction_count', 4);

        $this->assertEquals(116000, $year->json('data.summary.total_income'));
        $this->assertEquals(29000, $year->json('data.summary.average_transaction'));
        $this->assertEquals(7, $year->json('data.summary.total_weight_kg'));

        $custom = $this->report(['period' => 'custom', 'date_from' => '2025-01-01', 'date_to' => '2026-12-31'])
            ->assertOk()
            ->assertJsonPath('data.summary.transaction_count', 5)
            ->assertJsonPath('data.period.granularity', 'month');

        $this->assertEquals(124000, $custom->json('data.summary.total_income'));
        $this->assertEquals(24800, $custom->json('data.summary.average_transaction'));
    }

    public function test_day_boundaries_use_the_makassar_timezone(): void
    {
        $this->completed($this->kg, '2026-11-23 23:59:59', 1);   // kemarin: tidak dihitung
        $this->completed($this->kg, '2026-11-24 00:00:00', 1);   // awal hari ini
        $this->completed($this->kg, '2026-11-24 23:59:59', 1);   // akhir hari ini
        $this->completed($this->kg, '2026-11-25 00:00:00', 1);   // besok: tidak dihitung

        $response = $this->report(['period' => 'today'])->assertOk();

        $this->assertSame(2, $response->json('data.summary.transaction_count'));
        $this->assertEquals(16000, $response->json('data.summary.total_income'));

        $hours = collect($response->json('data.series.points'))->pluck('count', 'key');
        $this->assertSame(1, $hours['00']);
        $this->assertSame(1, $hours['23']);
    }

    public function test_empty_report_returns_zeroes(): void
    {
        $response = $this->report(['period' => 'today'])->assertOk();

        $summary = $response->json('data.summary');

        $this->assertSame(0, $summary['transaction_count']);
        $this->assertEquals(0, $summary['total_income']);
        $this->assertEquals(0, $summary['average_transaction']);
        $this->assertEquals(0, $summary['total_weight_kg']);
        $this->assertSame([], $response->json('data.by_package'));
        $this->assertCount(24, $response->json('data.series.points'));
    }

    /* ------------------------------ Per paket ------------------------------ */

    public function test_breakdown_by_package_is_sorted_and_has_shares(): void
    {
        $this->seedBaseline();

        $rows = $this->report(['period' => 'this_month'])->assertOk()->json('data.by_package');

        $this->assertCount(2, $rows);

        $this->assertSame('Bedcover', $rows[0]['name']);
        $this->assertSame('item', $rows[0]['unit']);
        $this->assertSame(1, $rows[0]['transaction_count']);
        $this->assertEquals(2, $rows[0]['quantity']);
        $this->assertEquals(60000, $rows[0]['total_income']);
        $this->assertEquals(65.2, $rows[0]['share_percent']);

        $this->assertSame('Reguler', $rows[1]['name']);
        $this->assertSame('kg', $rows[1]['unit']);
        $this->assertSame(2, $rows[1]['transaction_count']);
        $this->assertEquals(4, $rows[1]['quantity']);
        $this->assertEquals(32000, $rows[1]['total_income']);
        $this->assertEquals(34.8, $rows[1]['share_percent']);
    }

    public function test_package_filter_limits_every_figure(): void
    {
        $this->seedBaseline();

        $response = $this->report(['period' => 'this_month', 'package_id' => $this->item->id])
            ->assertOk()
            ->assertJsonPath('data.package.id', $this->item->id)
            ->assertJsonPath('data.package.name', 'Bedcover')
            ->assertJsonPath('data.summary.transaction_count', 1)
            ->assertJsonCount(1, 'data.by_package');

        $this->assertEquals(60000, $response->json('data.summary.total_income'));
        $this->assertEquals(60000, $response->json('data.summary.average_transaction'));
        $this->assertEquals(0, $response->json('data.summary.total_weight_kg'));
        $this->assertEquals(2, $response->json('data.summary.total_items'));
        $this->assertEquals(100, $response->json('data.by_package.0.share_percent'));
    }

    public function test_archived_packages_remain_in_reports_and_filters(): void
    {
        $this->seedBaseline();
        $this->item->delete();

        $response = $this->report(['period' => 'this_month', 'package_id' => $this->item->id])
            ->assertOk()
            ->assertJsonPath('data.summary.transaction_count', 1)
            ->assertJsonPath('data.by_package.0.name', 'Bedcover');

        $options = collect($response->json('data.options.packages'));

        $this->assertTrue($options->firstWhere('id', $this->item->id)['archived']);
        $this->assertFalse($options->firstWhere('id', $this->kg->id)['archived']);
    }

    /* ------------------------------ Grafik ------------------------------ */

    public function test_hourly_series_for_today(): void
    {
        $this->seedBaseline();

        $response = $this->report(['period' => 'today'])->assertOk()->assertJsonPath('data.series.granularity', 'hour');

        $points = collect($response->json('data.series.points'))->keyBy('key');

        $this->assertCount(24, $points);
        $this->assertEquals(20000, $points['09']['total']);
        $this->assertSame(1, $points['09']['count']);
        $this->assertEquals(12000, $points['00']['total']);
        $this->assertEquals(0, $points['15']['total']);
    }

    public function test_daily_series_is_zero_filled_and_stops_at_today(): void
    {
        $this->seedBaseline();

        $response = $this->report(['period' => 'this_month'])->assertOk()->assertJsonPath('data.series.granularity', 'day');

        $points = collect($response->json('data.series.points'));

        $this->assertCount(24, $points);
        $this->assertSame('2026-11-01', $points->first()['key']);
        $this->assertSame('2026-11-24', $points->last()['key']);

        $byKey = $points->keyBy('key');
        $this->assertEquals(60000, $byKey['2026-11-10']['total']);
        $this->assertEquals(32000, $byKey['2026-11-24']['total']);
        $this->assertSame(2, $byKey['2026-11-24']['count']);
        $this->assertEquals(0, $byKey['2026-11-01']['total']);
    }

    public function test_monthly_series_for_long_ranges(): void
    {
        $this->seedBaseline();

        $year = collect($this->report(['period' => 'this_year'])->assertOk()->json('data.series.points'));

        $this->assertCount(11, $year);
        $this->assertSame('2026-01', $year->first()['key']);
        $this->assertEquals(24000, $year->firstWhere('key', '2026-10')['total']);
        $this->assertEquals(92000, $year->firstWhere('key', '2026-11')['total']);

        $custom = collect($this->report([
            'period' => 'custom', 'date_from' => '2025-01-01', 'date_to' => '2026-12-31',
        ])->assertOk()->json('data.series.points'));

        $this->assertCount(23, $custom);
        $this->assertEquals(8000, $custom->firstWhere('key', '2025-12')['total']);
        $this->assertEquals(0, $custom->firstWhere('key', '2025-01')['total']);
    }

    /* ------------------------------ Validasi ------------------------------ */

    public function test_filter_validation(): void
    {
        $this->report(['period' => 'minggu-ini'])->assertUnprocessable()->assertJsonValidationErrors('period');

        $this->report(['period' => 'custom'])
            ->assertUnprocessable()->assertJsonValidationErrors(['date_from', 'date_to']);

        $this->report(['period' => 'custom', 'date_from' => '24-11-2026', 'date_to' => '2026-11-30'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_from');

        $this->report(['period' => 'custom', 'date_from' => '2026-02-31', 'date_to' => '2026-03-05'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_from');

        $this->report(['period' => 'custom', 'date_from' => '2026-11-30', 'date_to' => '2026-11-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to')
            ->assertJsonPath('errors.date_to.0', 'Tanggal akhir tidak boleh sebelum tanggal awal.');

        $this->report(['period' => 'custom', 'date_from' => '2020-01-01', 'date_to' => '2026-11-24'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_to.0', 'Rentang tanggal maksimal 5 tahun.');

        $this->report(['package_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('package_id');
    }

    /* ------------------------------ Rincian transaksi ------------------------------ */

    public function test_transactions_are_paginated_newest_first_and_hide_phone_numbers(): void
    {
        $this->seedBaseline();

        $page1 = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/transactions?period=this_month&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('data.0.package_name', 'Reguler')
            ->assertJsonPath('data.0.completed_by.name', $this->admin->name)
            ->assertJsonMissingPath('data.0.customer_phone');

        $this->assertEquals(20000, $page1->json('data.0.total_price'));
        $this->assertEquals(12000, $page1->json('data.1.total_price'));

        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/transactions?period=this_month&per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.package_name', 'Bedcover');
    }

    public function test_transactions_support_search_and_package_filter(): void
    {
        $this->seedBaseline();
        $named = $this->completed($this->kg, '2026-11-12 10:00:00', 1, ['customer_name' => 'Siti Aminah']);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/transactions?period=this_month&search=siti')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.code', $named->code);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/transactions?period=this_month&search='.$named->code)
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/transactions?period=this_month&package_id='.$this->item->id)
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/transactions?per_page=500')
            ->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    /* ------------------------------ Cetak ------------------------------ */

    public function test_print_endpoint_returns_everything_needed_to_print(): void
    {
        $this->seedBaseline();

        LaundryWebsiteSetting::create([
            'group' => 'business', 'key' => 'business_name', 'value' => 'Laundry Uji', 'type' => 'text',
        ]);

        $this->actingAs($this->admin)->getJson('/api/admin/reports/print?period=this_month')
            ->assertOk()
            ->assertJsonPath('data.business.name', 'Laundry Uji')
            ->assertJsonPath('data.period.label', '01/11/2026 - 30/11/2026')
            ->assertJsonPath('data.package', null)
            ->assertJsonPath('data.summary.transaction_count', 3)
            ->assertJsonPath('data.truncated', false)
            ->assertJsonCount(3, 'data.transactions')
            ->assertJsonCount(2, 'data.by_package')
            ->assertJsonPath('data.transactions.0.package_name', 'Bedcover'); // urut dari yang terlama

        $this->assertDatabaseHas('laundry_activity_logs', [
            'action' => 'report.printed',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_package_names_and_prices_use_the_snapshot_not_the_current_package(): void
    {
        $transaction = $this->completed($this->kg, '2026-11-12 10:00:00', 2);

        $this->kg->update(['name' => 'Nama Baru', 'price' => 99999]);

        $row = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/transactions?period=this_month')
            ->assertOk()
            ->json('data.0');

        // Nama yang tampil di rincian adalah snapshot transaksi; total tidak berubah.
        $this->assertSame($transaction->package_name, $row['package_name']);
        $this->assertEquals(16000, $row['total_price']);
        $this->assertEquals(8000, $row['package_price']);
    }
}