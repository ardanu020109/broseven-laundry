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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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

    /** Bulan ini yang dihitung: Andi 60.000, Siti 12.000, Budi 20.000 = 92.000. */
    private function seedBaseline(): void
    {
        $this->completed($this->kg, '2026-11-24 09:15:00', 2.5, ['customer_name' => 'Budi Santoso']);
        $this->completed($this->kg, '2026-11-24 00:00:00', 1.5, ['customer_name' => 'Siti Aminah']);
        $this->completed($this->item, '2026-11-10 14:00:00', 2, ['customer_name' => 'Andi Wijaya']);
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

    private function rowOf(Worksheet $sheet, string $text, string $column = 'A'): int
    {
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            if ($sheet->getCell($column.$row)->getValue() === $text) {
                return $row;
            }
        }

        $this->fail("Teks tidak ditemukan di kolom {$column}: {$text}");
    }

    public function test_export_is_a_single_sheet_xlsx_download(): void
    {
        $this->seedBaseline();

        [$book, $path, $response] = $this->download();

        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('laporan-pemasukan_2026-11-01_2026-11-30.xlsx', $response->headers->get('Content-Disposition'));
        $this->assertSame('PK', substr((string) file_get_contents($path), 0, 2));
        $this->assertSame(['Laporan'], $book->getSheetNames());
    }

    public function test_sheet_shows_summary_per_package_and_every_customer_item(): void
    {
        $this->seedBaseline();

        [$book] = $this->download();
        $sheet = $book->getSheetByName('Laporan');

        $this->assertSame(config('app.name'), $sheet->getCell('A1')->getValue());
        $this->assertSame('Laporan Pemasukan', $sheet->getCell('A2')->getValue());

        // Ringkasan
        $s = $this->rowOf($sheet, 'Ringkasan');
        $this->assertEquals(92000, $sheet->getCell('D'.($s + 1))->getValue());
        $this->assertEquals(3, $sheet->getCell('D'.($s + 2))->getValue());
        $this->assertEquals(4, $sheet->getCell('D'.($s + 3))->getValue());
        $this->assertEquals(2, $sheet->getCell('D'.($s + 4))->getValue());
        $this->assertEqualsWithDelta(30666.67, (float) $sheet->getCell('D'.($s + 5))->getValue(), 0.001);

        // Pemasukan per paket
        $p = $this->rowOf($sheet, 'Pemasukan per Paket');
        $this->assertSame('Paket', $sheet->getCell('A'.($p + 1))->getValue());
        $this->assertSame('Bedcover', $sheet->getCell('A'.($p + 2))->getValue());
        $this->assertEquals(1, $sheet->getCell('E'.($p + 2))->getValue());
        $this->assertEquals(60000, $sheet->getCell('G'.($p + 2))->getValue());
        $this->assertEqualsWithDelta(65.2, (float) $sheet->getCell('I'.($p + 2))->getValue(), 0.001);
        $this->assertSame('Reguler', $sheet->getCell('A'.($p + 3))->getValue());
        $this->assertEquals(32000, $sheet->getCell('G'.($p + 3))->getValue());
        $this->assertSame('TOTAL', $sheet->getCell('A'.($p + 4))->getValue());
        $this->assertEquals(92000, $sheet->getCell('G'.($p + 4))->getValue());

        // Rincian SEMUA customer (urut dari yang terlama)
        $d = $this->rowOf($sheet, 'Rincian Transaksi (Semua Customer)');
        $this->assertSame('Kode Transaksi', $sheet->getCell('B'.($d + 1))->getValue());
        $this->assertSame('Andi Wijaya', $sheet->getCell('E'.($d + 2))->getValue());
        $this->assertSame('Siti Aminah', $sheet->getCell('E'.($d + 3))->getValue());
        $this->assertSame('Budi Santoso', $sheet->getCell('E'.($d + 4))->getValue());

        $this->assertSame('Bedcover', $sheet->getCell('F'.($d + 2))->getValue());
        $this->assertSame('item', $sheet->getCell('G'.($d + 2))->getValue());
        $this->assertEquals(2, $sheet->getCell('H'.($d + 2))->getValue());
        $this->assertEquals(30000, $sheet->getCell('I'.($d + 2))->getValue());
        $this->assertEquals(60000, $sheet->getCell('J'.($d + 2))->getValue());
        $this->assertEqualsWithDelta($this->serial('2026-11-10 14:00:00'), (float) $sheet->getCell('D'.($d + 2))->getValue(), 0.0001);

        $this->assertSame('TOTAL (3 transaksi)', $sheet->getCell('B'.($d + 5))->getValue());
        $this->assertEquals(92000, $sheet->getCell('J'.($d + 5))->getValue());
        $this->assertSame('A'.($d + 1).':L'.($d + 4), $sheet->getAutoFilter()->getRange());
    }

    public function test_text_is_never_turned_into_a_formula(): void
    {
        $payloads = ['=HYPERLINK("http://x")', '+62812', '-1+1', '@SUM(A1)'];

        foreach ($payloads as $index => $payload) {
            $this->completed($this->kg, '2026-11-1'.($index + 1).' 10:00:00', 1, ['customer_name' => $payload]);
        }

        [$book, $path] = $this->download();
        $sheet = $book->getSheetByName('Laporan');
        $d = $this->rowOf($sheet, 'Rincian Transaksi (Semua Customer)');

        $names = [];

        for ($row = $d + 2; $row <= $d + 5; $row++) {
            $cell = $sheet->getCell("E{$row}");

            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            $names[] = $cell->getValue();
        }

        $this->assertEqualsCanonicalizing($payloads, $names);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertNotSame('', $xml);
        $this->assertStringNotContainsString('<f>', $xml);
    }

    public function test_export_respects_package_filter_and_empty_periods(): void
    {
        $this->seedBaseline();

        [$book] = $this->download('period=this_month&package_id='.$this->item->id);
        $sheet = $book->getSheetByName('Laporan');
        $d = $this->rowOf($sheet, 'Rincian Transaksi (Semua Customer)');

        $this->assertSame('Paket: Bedcover', $sheet->getCell('A4')->getValue());
        $this->assertSame('Andi Wijaya', $sheet->getCell('E'.($d + 2))->getValue());
        $this->assertSame('TOTAL (1 transaksi)', $sheet->getCell('B'.($d + 3))->getValue());

        [$empty] = $this->download('period=custom&date_from=2024-01-01&date_to=2024-01-31');
        $sheet = $empty->getSheetByName('Laporan');

        $this->assertNotNull($this->rowOf($sheet, 'Tidak ada transaksi selesai pada periode ini.'));
        $row = $this->rowOf($sheet, 'TOTAL (0 transaksi)', 'B');
        $this->assertEquals(0, $sheet->getCell('J'.$row)->getValue());
    }

    public function test_detail_rows_are_capped_but_totals_stay_complete(): void
    {
        $this->seedBaseline();
        config(['broseven.reports.export_limit' => 2]);

        [$book] = $this->download();
        $sheet = $book->getSheetByName('Laporan');
        $d = $this->rowOf($sheet, 'Rincian Transaksi (Semua Customer)');

        $this->assertSame('Andi Wijaya', $sheet->getCell('E'.($d + 2))->getValue());
        $this->assertSame('Siti Aminah', $sheet->getCell('E'.($d + 3))->getValue());
        $this->assertSame('TOTAL (3 transaksi)', $sheet->getCell('B'.($d + 4))->getValue());
        $this->assertEquals(92000, $sheet->getCell('J'.($d + 4))->getValue());
        $this->assertStringContainsString('hanya 2 transaksi pertama', (string) $sheet->getCell('B'.($d + 6))->getValue());
    }

    public function test_export_is_restricted_validated_and_logged(): void
    {
        $this->getJson('/api/admin/reports/export')->assertUnauthorized();

        $this->actingAs(User::factory()->create())->getJson('/api/admin/reports/export')->assertForbidden();

        $this->actingAs($this->admin)->getJson('/api/admin/reports/export?period=custom')
            ->assertUnprocessable()->assertJsonValidationErrors(['date_from', 'date_to']);

        $this->assertDatabaseMissing('laundry_activity_logs', ['action' => 'report.exported']);

        $this->download();

        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'report.exported', 'user_id' => $this->admin->id]);
    }
}