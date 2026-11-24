<?php

namespace Tests\Feature\Reports;

use App\Enums\TransactionStatus;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;
use ZipArchive;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private LaundryPackage $kg;

    private LaundryPackage $item;

    /** @var array<int, string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Selasa, 24 November 2026, 10:00 WITA
        $this->travelTo(Carbon::parse('2026-11-24 10:00:00', 'Asia/Makassar'));

        $this->admin = User::factory()->admin()->create();
        $this->kg = LaundryPackage::factory()->create(['name' => 'Reguler', 'price' => 8000]);
        $this->item = LaundryPackage::factory()->perItem()->create(['name' => 'Bedcover', 'price' => 30000]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
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
     * Bulan ini yang dihitung: #3 Bedcover 60.000 (10 Nov), #2 12.000 (24 Nov 00:00),
     * #1 20.000 (24 Nov 09:15) = 92.000. Di luar bulan ini atau bukan pemasukan: tidak dihitung.
     */
    private function seedBaseline(): void
    {
        $this->completed($this->kg, '2026-11-24 09:15:00', 2.5);
        $this->completed($this->kg, '2026-11-24 00:00:00', 1.5);
        $this->completed($this->item, '2026-11-10 14:00:00', 2);
        $this->completed($this->kg, '2026-10-31 23:59:59', 3);

        LaundryTransaction::factory()->forPackage($this->kg)
            ->status(TransactionStatus::Cancelled)->create(['quantity' => 5, 'cancelled_at' => now()]);
        LaundryTransaction::factory()->forPackage($this->kg)
            ->status(TransactionStatus::Ready)->create(['quantity' => 4]);
        $this->completed($this->kg, '2026-11-24 08:00:00', 9)->delete();
    }

    /** @return array{0: Spreadsheet, 1: string, 2: \Illuminate\Testing\TestResponse} */
    private function download(string $query = 'period=this_month'): array
    {
        $response = $this->actingAs($this->admin)->get('/api/admin/reports/export?'.$query)->assertOk();

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lap_'.uniqid('', true).'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $this->files[] = $path;

        return [IOFactory::load($path), $path, $response];
    }

    private function serial(string $local): float
    {
        return (float) ExcelDate::PHPToExcel(Carbon::parse($local, 'Asia/Makassar'));
    }

    /* ------------------------------ Tes ------------------------------ */

    public function test_export_is_a_valid_xlsx_download(): void
    {
        $this->seedBaseline();

        [$book, $path, $response] = $this->download();

        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'laporan-pemasukan_2026-11-01_2026-11-30.xlsx',
            $response->headers->get('Content-Disposition')
        );

        // File .xlsx adalah arsip ZIP (diawali "PK").
        $this->assertSame('PK', substr((string) file_get_contents($path), 0, 2));
        $this->assertSame(['Ringkasan', 'Transaksi'], $book->getSheetNames());
    }

    public function test_summary_sheet_matches_the_report(): void
    {
        $this->seedBaseline();

        [$book] = $this->download();
        $sheet = $book->getSheetByName('Ringkasan');

        $this->assertSame('Laporan Pemasukan', $sheet->getCell('A2')->getValue());
        $this->assertSame('01/11/2026 - 30/11/2026', $sheet->getCell('B4')->getValue());
        $this->assertSame('Semua paket', $sheet->getCell('B5')->getValue());

        $this->assertEquals(92000, $sheet->getCell('B10')->getValue());
        $this->assertEquals(3, $sheet->getCell('B11')->getValue());
        $this->assertEquals(4, $sheet->getCell('B12')->getValue());
        $this->assertEquals(2, $sheet->getCell('B13')->getValue());
        $this->assertEqualsWithDelta(30666.67, (float) $sheet->getCell('B14')->getValue(), 0.001);

        $this->assertSame('Paket', $sheet->getCell('A17')->getValue());
        $this->assertSame('Bedcover', $sheet->getCell('A18')->getValue());
        $this->assertEquals(60000, $sheet->getCell('E18')->getValue());
        $this->assertEqualsWithDelta(65.2, (float) $sheet->getCell('F18')->getValue(), 0.001);
        $this->assertSame('Reguler', $sheet->getCell('A19')->getValue());
        $this->assertEquals(32000, $sheet->getCell('E19')->getValue());
        $this->assertSame('TOTAL', $sheet->getCell('A20')->getValue());
        $this->assertEquals(3, $sheet->getCell('C20')->getValue());
        $this->assertEquals(92000, $sheet->getCell('E20')->getValue());
    }

    public function test_detail_sheet_has_real_numbers_and_dates(): void
    {
        $this->seedBaseline();

        [$book] = $this->download();
        $sheet = $book->getSheetByName('Transaksi');

        $this->assertSame('Kode Transaksi', $sheet->getCell('B1')->getValue());
        $this->assertSame('Total (Rp)', $sheet->getCell('J1')->getValue());

        // Urut dari yang terlama: Bedcover (10 Nov), lalu dua transaksi 24 Nov.
        $this->assertSame('Bedcover', $sheet->getCell('F2')->getValue());
        $this->assertSame('item', $sheet->getCell('G2')->getValue());
        $this->assertEquals(2, $sheet->getCell('H2')->getValue());
        $this->assertEquals(30000, $sheet->getCell('I2')->getValue());
        $this->assertEquals(60000, $sheet->getCell('J2')->getValue());

        // Tanggal disimpan sebagai tanggal Excel (angka), dalam waktu WITA.
        $this->assertEqualsWithDelta($this->serial('2026-11-09 14:00:00'), (float) $sheet->getCell('C2')->getValue(), 0.0001);
        $this->assertEqualsWithDelta($this->serial('2026-11-10 14:00:00'), (float) $sheet->getCell('D2')->getValue(), 0.0001);

        $this->assertSame('Reguler', $sheet->getCell('F3')->getValue());
        $this->assertEquals(1.5, $sheet->getCell('H3')->getValue());
        $this->assertEquals(12000, $sheet->getCell('J3')->getValue());
        $this->assertEquals(2.5, $sheet->getCell('H4')->getValue());
        $this->assertEquals(20000, $sheet->getCell('J4')->getValue());

        $this->assertSame('TOTAL (3 transaksi)', $sheet->getCell('B5')->getValue());
        $this->assertEquals(92000, $sheet->getCell('J5')->getValue());
        $this->assertNull($sheet->getCell('B6')->getValue());

        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame('A1:L4', $sheet->getAutoFilter()->getRange());
    }

    public function test_text_is_never_turned_into_a_formula(): void
    {
        $payloads = ['=HYPERLINK("http://x")', '+62812', '-1+1', '@SUM(A1)'];

        foreach ($payloads as $index => $payload) {
            $this->completed($this->kg, '2026-11-1'.($index + 1).' 10:00:00', 1, ['customer_name' => $payload]);
        }

        [$book, $path] = $this->download();
        $sheet = $book->getSheetByName('Transaksi');

        $names = [];

        for ($row = 2; $row <= 5; $row++) {
            $cell = $sheet->getCell("E{$row}");

            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            $names[] = $cell->getValue();
        }

        $this->assertEqualsCanonicalizing($payloads, $names);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();

        $this->assertNotSame('', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
    }

    public function test_export_respects_package_filter_and_empty_periods(): void
    {
        $this->seedBaseline();

        [$book] = $this->download('period=this_month&package_id='.$this->item->id);

        $this->assertSame('Bedcover', $book->getSheetByName('Ringkasan')->getCell('B5')->getValue());

        $details = $book->getSheetByName('Transaksi');
        $this->assertSame('Bedcover', $details->getCell('F2')->getValue());
        $this->assertSame('TOTAL (1 transaksi)', $details->getCell('B3')->getValue());
        $this->assertEquals(60000, $details->getCell('J3')->getValue());

        [$empty] = $this->download('period=custom&date_from=2024-01-01&date_to=2024-01-31');

        $summary = $empty->getSheetByName('Ringkasan');
        $this->assertEquals(0, $summary->getCell('B10')->getValue());
        $this->assertEquals(0, $summary->getCell('B11')->getValue());
        $this->assertSame('Tidak ada transaksi selesai pada periode ini.', $summary->getCell('A18')->getValue());

        $details = $empty->getSheetByName('Transaksi');
        $this->assertSame('TOTAL (0 transaksi)', $details->getCell('B2')->getValue());
        $this->assertEquals(0, $details->getCell('J2')->getValue());
    }

    public function test_detail_rows_are_capped_but_totals_stay_complete(): void
    {
        $this->seedBaseline();
        config(['broseven.reports.export_limit' => 2]);

        [$book] = $this->download();
        $sheet = $book->getSheetByName('Transaksi');

        $this->assertSame('Bedcover', $sheet->getCell('F2')->getValue());
        $this->assertSame('Reguler', $sheet->getCell('F3')->getValue());

        // Total tetap mencakup seluruh transaksi.
        $this->assertSame('TOTAL (3 transaksi)', $sheet->getCell('B4')->getValue());
        $this->assertEquals(92000, $sheet->getCell('J4')->getValue());
        $this->assertStringContainsString('hanya 2 transaksi pertama', (string) $sheet->getCell('B6')->getValue());

        $this->assertEquals(92000, $book->getSheetByName('Ringkasan')->getCell('B10')->getValue());
    }

    public function test_export_is_restricted_validated_and_logged(): void
    {
        $this->getJson('/api/admin/reports/export')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/reports/export')
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/export?period=custom')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_from', 'date_to']);

        $this->assertDatabaseMissing('laundry_activity_logs', ['action' => 'report.exported']);

        $this->download();

        $this->assertDatabaseHas('laundry_activity_logs', [
            'action' => 'report.exported',
            'user_id' => $this->admin->id,
        ]);
    }
}