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
 * File Excel (.xlsx) laporan pemasukan: SATU lembar "Laporan" dengan susunan seperti cetak/PDF
 * (ringkasan, pemasukan per paket, lalu rincian SEMUA transaksi customer).
 *
 *  - Teks selalu ditulis sebagai teks murni (isi berawalan = + - @ tidak menjadi rumus).
 *  - Angka dan tanggal ditulis sebagai angka/tanggal asli agar bisa dijumlahkan dan difilter.
 *  - Seluruh angka berasal dari LaundryReportService (backend).
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

    /** Batas baris rincian transaksi (ringkasan dan total tetap mencakup semua transaksi). */
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

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan');
        $this->reportSheet($sheet, $range, $packageId, $overview);

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

    /** @param  array<string, mixed>  $overview */
    private function reportSheet(Worksheet $sheet, ReportRange $range, ?int $packageId, array $overview): void
    {
        $summary = $overview['summary'];
        $row = 1;

        // Judul
        $titles = [
            [$overview['business']['name'], 18, true],
            ['Laporan Pemasukan', 14, true],
            ['Periode: '.$overview['period']['label'].' (WITA)', 11, false],
            ['Paket: '.($overview['package']['name'] ?? 'Semua paket'), 11, false],
            ['Dibuat: '.now()->timezone(ReportRange::TIMEZONE)->format('d/m/Y H:i').' WITA', 10, false],
        ];

        foreach ($titles as [$text, $size, $bold]) {
            $this->text($sheet, "A{$row}", $text);
            $sheet->mergeCells("A{$row}:L{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => $bold, 'size' => $size],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $row++;
        }

        $row++;

        // Ringkasan
        $this->section($sheet, $row++, 'Ringkasan');

        $figures = [
            ['Total pemasukan (Rp)', $summary['total_income'], self::MONEY],
            ['Jumlah transaksi', $summary['transaction_count'], self::INTEGER],
            ['Total berat cucian (kg)', $summary['total_weight_kg'], self::DECIMAL],
            ['Total jumlah item', $summary['total_items'], self::INTEGER],
            ['Rata-rata per transaksi (Rp)', $summary['average_transaction'], self::MONEY],
        ];

        $first = $row;

        foreach ($figures as [$label, $value, $format]) {
            $this->text($sheet, "A{$row}", $label);
            $sheet->mergeCells("A{$row}:C{$row}");
            $this->number($sheet, "D{$row}", $value);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($format);
            $row++;
        }

        $sheet->getStyle("A{$first}:D".($row - 1))->applyFromArray($this->gridStyle());
        $sheet->getStyle("A{$first}:C".($row - 1))->getFont()->setBold(true);
        $row += 1;

        // Pemasukan per paket
        $this->section($sheet, $row++, 'Pemasukan per Paket');

        if ($overview['by_package'] === []) {
            $this->text($sheet, "A{$row}", 'Tidak ada transaksi selesai pada periode ini.');
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
            $row++;
        } else {
            $this->text($sheet, "A{$row}", 'Paket');
            $sheet->mergeCells("A{$row}:C{$row}");
            foreach (['D' => 'Satuan', 'E' => 'Jumlah Transaksi', 'F' => 'Jumlah Cucian', 'G' => 'Pemasukan (Rp)', 'I' => 'Kontribusi (%)'] as $col => $label) {
                $this->text($sheet, "{$col}{$row}", $label);
            }
            $sheet->mergeCells("G{$row}:H{$row}");
            $sheet->getStyle("A{$row}:I{$row}")->applyFromArray($this->headerStyle());

            $headerRow = $row++;
            $dataStart = $row;

            foreach ($overview['by_package'] as $package) {
                $this->text($sheet, "A{$row}", $package['name']);
                $sheet->mergeCells("A{$row}:C{$row}");
                $this->text($sheet, "D{$row}", $package['unit']);
                $this->number($sheet, "E{$row}", $package['transaction_count']);
                $this->number($sheet, "F{$row}", $package['quantity']);
                $this->number($sheet, "G{$row}", $package['total_income']);
                $sheet->mergeCells("G{$row}:H{$row}");
                $this->number($sheet, "I{$row}", $package['share_percent']);
                $row++;
            }

            $this->text($sheet, "A{$row}", 'TOTAL');
            $sheet->mergeCells("A{$row}:C{$row}");
            $this->number($sheet, "E{$row}", $summary['transaction_count']);
            $this->number($sheet, "G{$row}", $summary['total_income']);
            $sheet->mergeCells("G{$row}:H{$row}");

            $sheet->getStyle("A{$headerRow}:I{$row}")->applyFromArray($this->gridStyle());
            $sheet->getStyle("E{$dataStart}:E{$row}")->getNumberFormat()->setFormatCode(self::INTEGER);
            $sheet->getStyle("F{$dataStart}:F{$row}")->getNumberFormat()->setFormatCode(self::DECIMAL);
            $sheet->getStyle("G{$dataStart}:G{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
            $sheet->getStyle("I{$dataStart}:I{$row}")->getNumberFormat()->setFormatCode('0.0');
            $sheet->getStyle("D{$dataStart}:D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}:I{$row}")->applyFromArray($this->totalStyle());
            $row++;
        }

        $row++;

        // Rincian semua transaksi customer
        $this->section($sheet, $row++, 'Rincian Transaksi (Semua Customer)');

        $headers = [
            'No', 'Kode Transaksi', 'Tanggal Masuk', 'Tanggal Selesai', 'Nama Customer', 'Paket',
            'Satuan', 'Jumlah', 'Harga Satuan (Rp)', 'Total (Rp)', 'Diterima Oleh', 'Diserahkan Oleh',
        ];

        foreach ($headers as $index => $header) {
            $this->text($sheet, Coordinate::stringFromColumnIndex($index + 1).$row, $header);
        }

        $sheet->getStyle("A{$row}:L{$row}")->applyFromArray($this->headerStyle());
        $sheet->getRowDimension($row)->setRowHeight(24);

        $header = $row++;
        $rows = $this->reports->exportRows($range, $packageId)->limit($this->rowLimit())->get()->values();

        foreach ($rows as $index => $transaction) {
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
            $row++;
        }

        $count = $rows->count();

        if ($count > 0) {
            $last = $row - 1;
            $first = $header + 1;

            $sheet->getStyle("A{$header}:L{$last}")->applyFromArray($this->gridStyle());
            $sheet->getStyle("C{$first}:D{$last}")->getNumberFormat()->setFormatCode(self::DATETIME);
            $sheet->getStyle("H{$first}:H{$last}")->getNumberFormat()->setFormatCode(self::DECIMAL);
            $sheet->getStyle("I{$first}:J{$last}")->getNumberFormat()->setFormatCode(self::MONEY);
            $sheet->getStyle("A{$first}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$first}:G{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setAutoFilter("A{$header}:L{$last}");
        }

        // Total dari agregasi database (mencakup semua transaksi, bukan hanya baris yang tampil).
        $this->text($sheet, "B{$row}", "TOTAL ({$summary['transaction_count']} transaksi)");
        $this->number($sheet, "J{$row}", $summary['total_income']);
        $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle("A{$row}:L{$row}")->applyFromArray($this->totalStyle());

        if ($summary['transaction_count'] > $count) {
            $note = $row + 2;
            $this->text(
                $sheet,
                "B{$note}",
                "Catatan: hanya {$count} transaksi pertama (berdasarkan tanggal selesai) yang ditampilkan. "
                .'Total di atas dan bagian Ringkasan mencakup seluruh transaksi. Persempit periode untuk rincian lengkap.'
            );
            $sheet->getStyle("B{$note}")->getFont()->setItalic(true);
        }

        $widths = ['A' => 6, 'B' => 18, 'C' => 18, 'D' => 18, 'E' => 26, 'F' => 24, 'G' => 10, 'H' => 12, 'I' => 18, 'J' => 18, 'K' => 22, 'L' => 22];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }

    private function section(Worksheet $sheet, int $row, string $title): void
    {
        $this->text($sheet, "A{$row}", $title);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
    }

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

        if ($serial !== false) {
            $sheet->setCellValueExplicit($coordinate, $serial, DataType::TYPE_NUMERIC);
        }
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
        return ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER_COLOR]]]];
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