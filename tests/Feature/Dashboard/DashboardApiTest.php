<?php

namespace Tests\Feature\Dashboard;

use App\Enums\TransactionStatus;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private LaundryPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        // Selasa, 24 November 2026, 10:00 WITA
        $this->travelTo(Carbon::parse('2026-11-24 10:00:00', 'Asia/Makassar'));

        $this->package = LaundryPackage::factory()->create(['price' => 8000, 'estimated_hours' => 48]);
    }

    private function tx(TransactionStatus $status, string $receivedAt, array $extra = []): LaundryTransaction
    {
        return LaundryTransaction::factory()
            ->forPackage($this->package)
            ->status($status)
            ->create(array_merge([
                'quantity' => 1,
                'received_at' => $receivedAt,
                'estimated_completion_at' => Carbon::parse($receivedAt)->addDays(2),
            ], $extra));
    }

    /**
     * A diterima hari ini | B dicuci hari ini (melewati estimasi) | C siap diambil (kemarin)
     * D selesai hari ini 20.000 | E selesai 22 Nov 8.000 | F dibatalkan hari ini | G dihapus
     *
     * @return array<string, LaundryTransaction>
     */
    private function seedData(): array
    {
        $b = $this->tx(TransactionStatus::Washing, '2026-11-24 09:00:00', ['estimated_completion_at' => '2026-11-24 09:30:00']);

        $this->tx(TransactionStatus::Received, '2026-11-24 08:00:00');
        $this->tx(TransactionStatus::Ready, '2026-11-23 15:00:00');
        $this->tx(TransactionStatus::Completed, '2026-11-23 10:00:00', ['quantity' => 2.5, 'completed_at' => '2026-11-24 09:15:00']);
        $this->tx(TransactionStatus::Completed, '2026-11-21 10:00:00', ['quantity' => 1, 'completed_at' => '2026-11-22 12:00:00']);
        $this->tx(TransactionStatus::Cancelled, '2026-11-24 07:00:00', ['cancelled_at' => '2026-11-24 07:30:00']);
        $this->tx(TransactionStatus::Received, '2026-11-24 06:00:00')->delete();

        return ['overdue' => $b];
    }

    public function test_guests_and_inactive_users_are_rejected(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/notifications')->assertUnauthorized();

        $this->actingAs(User::factory()->inactive()->create())
            ->getJson('/api/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_admin_sees_counts_revenue_and_a_seven_day_series(): void
    {
        $this->seedData();

        $response = $this->actingAs(User::factory()->admin()->create())->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.can_view_revenue', true)
            ->assertJsonPath('data.date', '2026-11-24')
            ->assertJsonPath('data.stats.transactions_today', 3)
            ->assertJsonPath('data.stats.in_progress', 2)
            ->assertJsonPath('data.stats.ready', 1)
            ->assertJsonPath('data.stats.completed_today', 1);

        $this->assertEquals(20000, $response->json('data.stats.revenue_today'));

        $series = collect($response->json('data.series'))->keyBy('key');

        $this->assertSame(
            ['2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21', '2026-11-22', '2026-11-23', '2026-11-24'],
            $series->keys()->all()
        );

        // Jumlah transaksi per tanggal diterima.
        $this->assertSame(0, $series['2026-11-20']['count']);
        $this->assertSame(1, $series['2026-11-21']['count']);
        $this->assertSame(2, $series['2026-11-23']['count']);
        $this->assertSame(3, $series['2026-11-24']['count']);

        // Pemasukan per tanggal selesai.
        $this->assertEquals(0, $series['2026-11-20']['income']);
        $this->assertEquals(8000, $series['2026-11-22']['income']);
        $this->assertSame(1, $series['2026-11-22']['completed']);
        $this->assertEquals(20000, $series['2026-11-24']['income']);
    }

    public function test_recent_transactions_are_limited_ordered_and_hide_phone_numbers(): void
    {
        foreach (range(0, 9) as $hour) {
            $this->tx(TransactionStatus::Received, sprintf('2026-11-24 %02d:00:00', $hour));
        }

        $response = $this->actingAs(User::factory()->create())->getJson('/api/dashboard')->assertOk();

        $recent = collect($response->json('data.recent'));

        $this->assertCount(8, $recent);
        $this->assertSame('09:00', Carbon::parse($recent->first()['received_at'])->timezone('Asia/Makassar')->format('H:i'));
        $this->assertSame('02:00', Carbon::parse($recent->last()['received_at'])->timezone('Asia/Makassar')->format('H:i'));
        $this->assertArrayNotHasKey('customer_phone', $recent->first());
        $this->assertStringNotContainsString('tracking', $response->getContent());
    }

    public function test_notifications_report_ready_and_overdue_laundry(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.items', []);

        $this->seedData();

        $response = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonCount(2, 'data.items');

        $items = collect($response->json('data.items'))->keyBy('key');

        $this->assertSame(1, $items['ready']['count']);
        $this->assertSame('/transactions?status=READY', $items['ready']['link']);
        $this->assertSame(1, $items['overdue']['count']);
        $this->assertSame('/transactions?status=IN_PROGRESS&overdue=1', $items['overdue']['link']);
    }

    public function test_overdue_filter_on_the_transaction_list(): void
    {
        $seed = $this->seedData();

        $this->actingAs(User::factory()->create());

        $this->getJson('/api/transactions?status=IN_PROGRESS&overdue=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', $seed['overdue']->code);

        $this->getJson('/api/transactions?status=IN_PROGRESS&overdue=0')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/transactions?status=IN_PROGRESS')->assertJsonPath('meta.total', 2);

        $this->getJson('/api/transactions?overdue=abc')->assertUnprocessable()->assertJsonValidationErrors('overdue');
    }
}
