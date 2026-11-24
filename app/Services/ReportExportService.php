<?php

namespace App\Services;

use App\Support\ReportRange;
use Carbon\CarbonInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Membuat file Excel (.xlsx) laporan pemasukan.
 *
 * Aturan penting:
 *  - Teks SELALU ditulis sebagai teks murni (setCellValueExplicit), sehingga isi yang
 *    diawali "=", "+", "-", atau "@" tidak pernah dijalankan sebagai rumus.
 *  - Angka dan tanggal ditulis sebagai angka/tanggal asli agar bisa dijumlahkan dan difilter.
 *  - Seluruh angka berasal dari LaundryReportService (backend), bukan dihitung ulang di sini.
 */
class ReportExportService
{
    public const DEFAULT_ROW_LIMIT = 10000;

    private const TEXT_COLOR = '243746';

    private const BRAND_COLOR = 'ADD8E6';

    private const BORDER_COLOR = 'C9D6DC';

    private const TOTAL_FILL = 'EAF5F9';

    private const MONEY = '#,##0.00';

    private const DECIMAL = '#,##0.00';

    private const INTEGER = '#,##0';

    private const DATETIME = 'dd/mm/yyyy hh:mm';

    public function __construct(private readonly LaundryReportService $reports)
    {
    }

    public function filename(ReportRange $range): string
    {
        return 'laporan-pemasukan_'.$range->from->format('Y-m-d').'_'.$range->to->format('Y-m-d').'.xlsx';
    }

    /** Batas jumlah baris rincian (ringkasan dan total tetap mencakup semua transaksi). */
    public function rowLimit(): int
    {
        return max(1, (int) config('broseven.reports.export_limit', self::DEFAULT_ROW_LIMIT));
    }

    public function build(ReportRange $range, ?int $packageId): Spreadsheet
    {
        $this->raiseLimits();

        $overview = $this->reports->overview($range, $packageId);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator($overview['business']['name'])
            ->setTitle('Laporan Pemasukan '.$range->label())
            ->setSubject('Laporan Pemasukan');

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Ringkasan');
        $this->summarySheet($summary, $overview);

        $details = $spreadsheet->createSheet();
        $details->setTitle('Transaksi');
        $this->detailSheet($details, $range, $packageId, $overview['summary']);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** Menulis workbook ke tujuan (default: keluaran HTTP). */
    public function write(Spreadsheet $spreadsheet, string $target = 'php://output'): void
    {
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($target);

        $spreadsheet->disconnectWorksheets();
    }

    /* ---------------------------------------------------------------
     | Lembar "Ringkasan"
     | ------------------------------------------------------------- */

    /** @param  array<string, mixed>  $overview */
    private function summarySheet(Worksheet $sheet, array $overview): void
    {
        $summary = $overview['summary'];

        $this->text($sheet, 'A1', $overview['business']['name']);
        $this->text($sheet, 'A2', 'Laporan Pemasukan');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);

        $meta = [
            'Periode' => $overview['period']['label'],
            'Paket' => $overview['package']['name'] ?? 'Semua paket',
            'Zona waktu' => 'WITA (Asia/Makassar)',
            'Dibuat' => now()->timezone(ReportRange::TIMEZONE)->format('d/m/Y H:i').' WITA',
        ];

        $row = 4;
        foreach ($meta as $label => $value) {
            $this->text($sheet, "A{$row}", $label);
            $this->text($sheet, "B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        // Angka ringkasan: baris 10-14
        $this->text($sheet, 'A9', 'Ringkasan');
        $sheet->getStyle('A9')->getFont()->setBold(true)->setSize(12);

        $figures = [
            [10, 'Total pemasukan (Rp)', $summary['total_income'], self::MONEY],
            [11, 'Jumlah transaksi', $summary['transaction_count'], self::INTEGER],
            [12, 'Total berat cucian (kg)', $summary['total_weight_kg'], self::DECIMAL],
            [13, 'Total jumlah item', $summary['total_items'], self::INTEGER],
            [14, 'Rata-rata per transaksi (Rp)', $summary['average_transaction'], self::MONEY],
        ];

        foreach ($figures as [$line, $label, $value, $format]) {
            $this->text($sheet, "A{$line}", $label);
            $this->number($sheet, "B{$line}", $value);
            $sheet->getStyle("B{$line}")->getNumberFormat()->setFormatCode($format);
        }

        $sheet->getStyle('A10:B14')->applyFromArray($this->gridStyle());
        $sheet->getStyle('A10:A14')->getFont()->setBold(true);
        $sheet->getStyle('B10:B14')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Pemasukan per paket: header baris 17, data mulai baris 18
        $this->text($sheet, 'A16', 'Pemasukan per Paket');
        $sheet->getStyle('A16')->getFont()->setBold(true)->setSize(12);

        $headers = ['Paket', 'Satuan', 'Jumlah Transaksi', 'Jumlah Cucian', 'Pemasukan (Rp)', 'Kontribusi (%)'];

        foreach ($headers as $index => $header) {
            $this->text($sheet, Coordinate::stringFromColumnIndex($index + 1).'17', $header);
        }

        $sheet->getStyle('A17:F17')->applyFromArray($this->headerStyle());

        $row = 18;

        foreach ($overview['by_package'] as $package) {
            $this->text($sheet, "A{$row}", $package['name']);
            $this->text($sheet, "B{$row}", $package['unit']);
            $this->number($sheet, "C{$row}", $package['transaction_count']);
            $this->number($sheet, "D{$row}", $package['quantity']);
            $this->number($sheet, "E{$row}", $package['total_income']);
            $this->number($sheet, "F{$row}", $package['share_percent']);
            $row++;
        }

        if ($row === 18) {
            $this->text($sheet, 'A18', 'Tidak ada transaksi selesai pada periode ini.');
            $sheet->getStyle('A18')->getFont()->setItalic(true);
        } else {
            $this->text($sheet, "A{$row}", 'TOTAL');
            $this->number($sheet, "C{$row}", $summary['transaction_count']);
            $this->number($sheet, "E{$row}", $summary['total_income']);

            $last = $row;

            $sheet->getStyle("A17:F{$last}")->applyFromArray($this->gridStyle());
            $sheet->getStyle("C18:C{$last}")->getNumberFormat()->setFormatCode(self::INTEGER);
            $sheet->getStyle("D18:D{$last}")->getNumberFormat()->setFormatCode(self::DECIMAL);
            $sheet->getStyle("E18:E{$last}")->getNumberFormat()->setFormatCode(self::MONEY);
            $sheet->getStyle("F18:F{$last}")->getNumberFormat()->setFormatCode('0.0');
            $sheet->getStyle("B18:B{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$last}:F{$last}")->applyFromArray($this->totalStyle());
        }

        foreach (['A' => 30, 'B' => 26, 'C' => 18, 'D' => 16, 'E' => 20, 'F' => 16] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }

    /* ---------------------------------------------------------------
     | Lembar "Transaksi"
     | ------------------------------------------------------------- */

    /** @param  array<string, mixed>  $summary */
    private function detailSheet(Worksheet $sheet, ReportRange $range, ?int $packageId, array $summary): void
    {
        $headers = [
            'No', 'Kode Transaksi', 'Tanggal Masuk', 'Tanggal Selesai', 'Nama Customer', 'Paket',
            'Satuan', 'Jumlah', 'Harga Satuan (Rp)', 'Total (Rp)', 'Diterima Oleh', 'Diserahkan Oleh',
        ];

        foreach ($headers as $index => $header) {
            $this->text($sheet, Coordinate::stringFromColumnIndex($index + 1).'1', $header);
        }

        $sheet->getStyle('A1:L1')->applyFromArray($this->headerStyle());
        $sheet->getRowDimension(1)->setRowHeight(24);

        $rows = $this->reports->exportRows($range, $packageId)->limit($this->rowLimit())->get()->values();

        foreach ($rows as $index => $transaction) {
            $row = $index + 2;

            $this->number($sheet, "A{$row}", $index + 1);
            $this->text($sheet, "B{$row}", $transaction->code);
            $this->date($sheet, "C{$row}", $transaction->received_at);
            $this->date($sheet, "D{$row}", $transaction->completed_at);
            $this->text($sheet, "E{$row}", $transaction->customer_name);
            $this->text($sheet, "F{$row}", $transaction->package_name);
            $this->text($sheet, "G{$row}", $transaction->package_unit->value);
            $this->number($sheet, "H{$row}", $transaction->quantity);
            $this->number($sheet, "I{$row}", $transaction->package_price);
            $this->number($sheet, "J{$row}", $transaction->total_price);
            $this->text($sheet, "K{$row}", $transaction->receiver?->name);
            $this->text($sheet, "L{$row}", $transaction->completer?->name);
        }

        $count = $rows->count();
        $lastData = $count + 1;
        $totalRow = $lastData + 1;

        if ($count > 0) {
            $sheet->getStyle("A1:L{$lastData}")->applyFromArray($this->gridStyle());
            $sheet->getStyle("C2:D{$lastData}")->getNumberFormat()->setFormatCode(self::DATETIME);
            $sheet->getStyle("H2:H{$lastData}")->getNumberFormat()->setFormatCode(self::DECIMAL);
            $sheet->getStyle("I2:J{$lastData}")->getNumberFormat()->setFormatCode(self::MONEY);
            $sheet->getStyle("A2:A{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G2:G{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setAutoFilter("A1:L{$lastData}");
        }

        // Total berasal dari agregasi database (mencakup semua transaksi, bukan hanya baris yang tampil).
        $this->text($sheet, "B{$totalRow}", "TOTAL ({$summary['transaction_count']} transaksi)");
        $this->number($sheet, "J{$totalRow}", $summary['total_income']);
        $sheet->getStyle("J{$totalRow}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("A{$totalRow}:L{$totalRow}")->applyFromArray($this->totalStyle());

        if ($summary['transaction_count'] > $count) {
            $this->text(
                $sheet,
                'B'.($totalRow + 2),
                "Catatan: hanya {$count} transaksi pertama (berdasarkan tanggal selesai) yang ditampilkan di lembar ini. "
                .'Total di atas dan lembar Ringkasan mencakup seluruh transaksi. Persempit periode untuk melihat rincian lengkap.'
            );
            $sheet->getStyle('B'.($totalRow + 2))->getFont()->setItalic(true);
        }

        $widths = ['A' => 6, 'B' => 18, 'C' => 18, 'D' => 18, 'E' => 26, 'F' => 24, 'G' => 9, 'H' => 11, 'I' => 18, 'J' => 18, 'K' => 22, 'L' => 22];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->freezePane('A2');

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(1, 1);
    }

    /* ---------------------------------------------------------------
     | Pembantu sel dan gaya
     | ------------------------------------------------------------- */

    /** Selalu teks murni: isi yang diawali "=" tidak akan dijalankan sebagai rumus. */
    private function text(Worksheet $sheet, string $coordinate, ?string $value): void
    {
        $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
    }

    private function number(Worksheet $sheet, string $coordinate, float|int|string $value): void
    {
        $sheet->setCellValueExplicit($coordinate, (float) $value, DataType::TYPE_NUMERIC);
    }

    /** Tanggal asli Excel, dalam waktu setempat (WITA). */
    private function date(Worksheet $sheet, string $coordinate, ?CarbonInterface $date): void
    {
        if ($date === null) {
            return;
        }

        $serial = ExcelDate::PHPToExcel($date->copy()->timezone(ReportRange::TIMEZONE));

        if ($serial === false) {
            return;
        }

        $sheet->setCellValueExplicit($coordinate, $serial, DataType::TYPE_NUMERIC);
    }

    /** @return array<string, mixed> */
    private function headerStyle(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => self::TEXT_COLOR]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND_COLOR]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER_COLOR]]],
        ];
    }

    /** @return array<string, mixed> */
    private function gridStyle(): array
    {
        return [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER_COLOR]]],
        ];
    }

    /** @return array<string, mixed> */
    private function totalStyle(): array
    {
        return [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TOTAL_FILL]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::TEXT_COLOR]]],
        ];
    }

    /** Menaikkan batas waktu dan memori untuk workbook besar (hanya jika batas saat ini lebih kecil). */
    private function raiseLimits(): void
    {
        @set_time_limit(180);

        $limit = ini_get('memory_limit');

        if ($limit !== false && $limit !== '-1' && $this->toBytes($limit) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
    }

    private function toBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}