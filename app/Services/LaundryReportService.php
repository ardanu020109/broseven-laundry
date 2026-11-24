<?php

namespace App\Services;

use App\Enums\PackageUnit;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\LaundryWebsiteSetting;
use App\Support\Money;
use App\Support\ReportRange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Seluruh perhitungan laporan dilakukan di sini (backend).
 * Pemasukan = transaksi COMPLETED, dihitung berdasarkan completed_at.
 */
class LaundryReportService
{
    public const PRINT_LIMIT = 2000;

    /* ---------------------------------------------------------------
     | Laporan utama
     | ------------------------------------------------------------- */

    /** @return array<string, mixed> */
    public function report(ReportRange $range, ?int $packageId): array
    {
        $totals = $this->totals($range, $packageId);

        return [
            'period' => $range->toArray(),
            'package' => $this->packageFilter($packageId),
            'summary' => $this->presentSummary($totals),
            'series' => [
                'granularity' => $range->granularity,
                'points' => $this->points($range, $packageId),
            ],
            'by_package' => $this->byPackage($range, $packageId, $totals['income']),
            'options' => ['packages' => $this->packageOptions()],
        ];
    }

    public function transactions(
        ReportRange $range,
        ?int $packageId,
        ?string $search,
        int $perPage,
    ): LengthAwarePaginator {
        return $this->baseQuery($range, $packageId)
            ->with(['receiver', 'completer'])
            ->search($search)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Ringkasan untuk cetak dan Excel (tanpa rincian transaksi).
     *
     * @return array<string, mixed>
     */
    public function overview(ReportRange $range, ?int $packageId): array
    {
        $totals = $this->totals($range, $packageId);

        return [
            'business' => ['name' => $this->businessName()],
            'period' => $range->toArray(),
            'package' => $this->packageFilter($packageId),
            'summary' => $this->presentSummary($totals),
            'by_package' => $this->byPackage($range, $packageId, $totals['income']),
        ];
    }

    /** @return array<string, mixed> */
    public function printable(ReportRange $range, ?int $packageId): array
    {
        $overview = $this->overview($range, $packageId);

        $rows = $this->exportRows($range, $packageId)->limit(self::PRINT_LIMIT)->get();

        return $overview + [
            'generated_at' => now()->toIso8601String(),
            'transactions' => $rows,
            'truncated' => $overview['summary']['transaction_count'] > self::PRINT_LIMIT,
            'limit' => self::PRINT_LIMIT,
        ];
    }

    /** Transaksi selesai pada rentang tersebut, urut dari yang terlama (untuk cetak dan Excel). */
    public function exportRows(ReportRange $range, ?int $packageId): Builder
    {
        return $this->baseQuery($range, $packageId)
            ->with(['receiver', 'completer'])
            ->orderBy('completed_at')
            ->orderBy('id');
    }

    /* ---------------------------------------------------------------
     | Query dan agregasi
     | ------------------------------------------------------------- */

    /**
     * Dasar semua laporan. Hanya transaksi COMPLETED (dengan completed_at) dalam rentang;
     * soft delete otomatis dikecualikan oleh model.
     */
    private function baseQuery(ReportRange $range, ?int $packageId): Builder
    {
        return LaundryTransaction::query()
            ->income()
            ->whereBetween('completed_at', [$range->queryFrom(), $range->queryTo()])
            ->when($packageId, fn (Builder $query) => $query->where('package_id', $packageId));
    }

    /** @return array{count: int, income: string, kg: string, items: string} */
    private function totals(ReportRange $range, ?int $packageId): array
    {
        $row = $this->baseQuery($range, $packageId)
            ->selectRaw(
                'COUNT(*) as aggregate_count,
                 ROUND(SUM(total_price), 2) as aggregate_income,
                 ROUND(SUM(CASE WHEN package_unit = ? THEN quantity ELSE 0 END), 2) as aggregate_kg,
                 ROUND(SUM(CASE WHEN package_unit = ? THEN quantity ELSE 0 END), 2) as aggregate_items',
                [PackageUnit::Kg->value, PackageUnit::Item->value]
            )
            ->toBase()
            ->first();

        return [
            'count' => (int) ($row->aggregate_count ?? 0),
            'income' => Money::decimal((string) ($row->aggregate_income ?? 0)),
            'kg' => Money::decimal((string) ($row->aggregate_kg ?? 0)),
            'items' => Money::decimal((string) ($row->aggregate_items ?? 0)),
        ];
    }

    /**
     * @param  array{count: int, income: string, kg: string, items: string}  $totals
     * @return array<string, mixed>
     */
    private function presentSummary(array $totals): array
    {
        $average = $totals['count'] > 0
            ? Money::divide($totals['income'], (string) $totals['count'])
            : '0.00';

        return [
            'total_income' => (float) $totals['income'],
            'transaction_count' => $totals['count'],
            'average_transaction' => (float) $average,
            'total_weight_kg' => (float) $totals['kg'],
            'total_items' => (float) $totals['items'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function byPackage(ReportRange $range, ?int $packageId, string $totalIncome): array
    {
        $rows = $this->baseQuery($range, $packageId)
            ->selectRaw(
                'package_id, package_unit,
                 COUNT(*) as aggregate_count,
                 ROUND(SUM(total_price), 2) as aggregate_income,
                 ROUND(SUM(quantity), 2) as aggregate_quantity'
            )
            ->groupBy('package_id', 'package_unit')
            ->toBase()
            ->get();

        $names = LaundryPackage::withTrashed()
            ->whereIn('id', $rows->pluck('package_id'))
            ->pluck('name', 'id');

        return $rows
            ->map(fn ($row) => [
                'package_id' => (int) $row->package_id,
                'name' => $names[$row->package_id] ?? 'Paket #'.$row->package_id,
                'unit' => (string) $row->package_unit,
                'unit_label' => PackageUnit::tryFrom((string) $row->package_unit)?->label() ?? (string) $row->package_unit,
                'transaction_count' => (int) $row->aggregate_count,
                'quantity' => Money::decimal((string) $row->aggregate_quantity),
                'income' => Money::decimal((string) $row->aggregate_income),
            ])
            ->sort(fn (array $a, array $b) => bccomp($b['income'], $a['income'], 2))
            ->values()
            ->map(fn (array $row) => [
                'package_id' => $row['package_id'],
                'name' => $row['name'],
                'unit' => $row['unit'],
                'unit_label' => $row['unit_label'],
                'transaction_count' => $row['transaction_count'],
                'quantity' => (float) $row['quantity'],
                'total_income' => (float) $row['income'],
                'share_percent' => bccomp($totalIncome, '0', 2) > 0
                    ? (float) Money::divide(bcmul($row['income'], '100', 4), $totalIncome, 1)
                    : 0.0,
            ])
            ->all();
    }

    /* ---------------------------------------------------------------
     | Data grafik
     | ------------------------------------------------------------- */

    /** @return array<int, array{key: string, count: int, total: float}> */
    private function points(ReportRange $range, ?int $packageId): array
    {
        return match ($range->granularity) {
            ReportRange::HOUR => $this->hourlyPoints($range, $packageId),
            ReportRange::DAY => $this->dailyPoints($range, $packageId),
            default => $this->monthlyPoints($range, $packageId),
        };
    }

    /** Satu hari: 24 titik (00-23), dikelompokkan berdasarkan jam WITA. */
    private function hourlyPoints(ReportRange $range, ?int $packageId): array
    {
        $buckets = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $buckets[$hour] = ['count' => 0, 'income' => '0.00'];
        }

        $this->baseQuery($range, $packageId)
            ->get(['completed_at', 'total_price'])
            ->each(function (LaundryTransaction $transaction) use (&$buckets) {
                $hour = (int) $transaction->completed_at->copy()->timezone(ReportRange::TIMEZONE)->format('G');

                $buckets[$hour]['count']++;
                $buckets[$hour]['income'] = bcadd(
                    $buckets[$hour]['income'],
                    Money::decimal((string) $transaction->total_price),
                    2
                );
            });

        $points = [];

        foreach ($buckets as $hour => $bucket) {
            $points[] = $this->point(sprintf('%02d', $hour), $bucket['count'], $bucket['income']);
        }

        return $points;
    }

    private function dailyPoints(ReportRange $range, ?int $packageId): array
    {
        $totals = $this->dailyTotals($range, $packageId);
        $points = [];

        $end = $range->chartTo->startOfDay();

        for ($cursor = $range->from->startOfDay(); $cursor <= $end; $cursor = $cursor->addDay()) {
            $key = $cursor->format('Y-m-d');
            $row = $totals->get($key);

            $points[] = $this->point($key, $row['count'] ?? 0, $row['income'] ?? '0.00');
        }

        return $points;
    }

    /** Pengelompokan bulan dilakukan di PHP agar tidak bergantung pada fungsi SQL tertentu. */
    private function monthlyPoints(ReportRange $range, ?int $packageId): array
    {
        $months = [];

        foreach ($this->dailyTotals($range, $packageId) as $date => $row) {
            $month = substr((string) $date, 0, 7);

            $months[$month] ??= ['count' => 0, 'income' => '0.00'];
            $months[$month]['count'] += $row['count'];
            $months[$month]['income'] = bcadd($months[$month]['income'], $row['income'], 2);
        }

        $points = [];
        $end = $range->chartTo->startOfMonth();

        for ($cursor = $range->from->startOfMonth(); $cursor <= $end; $cursor = $cursor->addMonth()) {
            $key = $cursor->format('Y-m');

            $points[] = $this->point($key, $months[$key]['count'] ?? 0, $months[$key]['income'] ?? '0.00');
        }

        return $points;
    }

    /** @return Collection<string, array{count: int, income: string}> */
    private function dailyTotals(ReportRange $range, ?int $packageId): Collection
    {
        return $this->baseQuery($range, $packageId)
            ->selectRaw(
                'DATE(completed_at) as bucket,
                 COUNT(*) as aggregate_count,
                 ROUND(SUM(total_price), 2) as aggregate_income'
            )
            ->groupByRaw('DATE(completed_at)')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row) => [
                (string) $row->bucket => [
                    'count' => (int) $row->aggregate_count,
                    'income' => Money::decimal((string) $row->aggregate_income),
                ],
            ]);
    }

    /** @return array{key: string, count: int, total: float} */
    private function point(string $key, int $count, string $income): array
    {
        return ['key' => $key, 'count' => $count, 'total' => (float) $income];
    }

    /* ---------------------------------------------------------------
     | Opsi filter dan profil
     | ------------------------------------------------------------- */

    /** @return array<int, array{id: int, name: string, archived: bool}> */
    public function packageOptions(): array
    {
        return LaundryPackage::withTrashed()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LaundryPackage $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'archived' => $package->trashed(),
            ])
            ->values()
            ->all();
    }

    /** @return array{id: int, name: string}|null */
    private function packageFilter(?int $packageId): ?array
    {
        if ($packageId === null) {
            return null;
        }

        $package = LaundryPackage::withTrashed()->find($packageId);

        return $package ? ['id' => $package->id, 'name' => $package->name] : null;
    }

    private function businessName(): string
    {
        $name = trim((string) LaundryWebsiteSetting::query()->where('key', 'business_name')->value('value'));

        return $name !== '' ? $name : (string) config('app.name');
    }
}