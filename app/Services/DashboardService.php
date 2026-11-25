<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\LaundryTransaction;
use App\Models\User;
use App\Support\Money;
use App\Support\ReportRange;
use Illuminate\Support\Facades\Gate;

/**
 * Data dashboard dan notifikasi. Batas hari mengikuti WITA (Asia/Makassar).
 * Pemasukan hanya dihitung dan dikirim jika pengguna berhak (Gate "view-revenue").
 */
class DashboardService
{
    public const RECENT_LIMIT = 8;

    public const SERIES_DAYS = 7;

    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        $now = now()->timezone(ReportRange::TIMEZONE);
        $today = ReportRange::resolve(ReportRange::TODAY, null, null, $now);
        $week = ReportRange::resolve(
            ReportRange::CUSTOM,
            $now->copy()->subDays(self::SERIES_DAYS - 1)->format('Y-m-d'),
            $now->format('Y-m-d'),
            $now
        );

        $canViewRevenue = Gate::forUser($user)->allows('view-revenue');

        $stats = [
            'transactions_today' => LaundryTransaction::query()
                ->whereBetween('received_at', [$today->queryFrom(), $today->queryTo()])
                ->count(),
            'in_progress' => LaundryTransaction::query()->inProgress()->count(),
            'ready' => LaundryTransaction::query()->withStatus(TransactionStatus::Ready)->count(),
            'completed_today' => LaundryTransaction::query()
                ->income()
                ->whereBetween('completed_at', [$today->queryFrom(), $today->queryTo()])
                ->count(),
        ];

        $counts = LaundryTransaction::query()
            ->whereBetween('received_at', [$week->queryFrom(), $week->queryTo()])
            ->selectRaw('DATE(received_at) as bucket, COUNT(*) as aggregate_count')
            ->groupByRaw('DATE(received_at)')
            ->toBase()
            ->get()
            ->pluck('aggregate_count', 'bucket');

        $income = collect();

        if ($canViewRevenue) {
            $income = LaundryTransaction::query()
                ->income()
                ->whereBetween('completed_at', [$week->queryFrom(), $week->queryTo()])
                ->selectRaw(
                    'DATE(completed_at) as bucket,
                     COUNT(*) as aggregate_count,
                     ROUND(SUM(total_price), 2) as aggregate_income'
                )
                ->groupByRaw('DATE(completed_at)')
                ->toBase()
                ->get()
                ->keyBy('bucket');

            $stats['revenue_today'] = (float) Money::decimal(
                (string) ($income->get($now->format('Y-m-d'))->aggregate_income ?? 0)
            );
        }

        $series = [];

        for ($offset = self::SERIES_DAYS - 1; $offset >= 0; $offset--) {
            $key = $now->copy()->subDays($offset)->format('Y-m-d');

            $row = ['key' => $key, 'count' => (int) $counts->get($key, 0)];

            if ($canViewRevenue) {
                $paid = $income->get($key);

                $row['income'] = (float) Money::decimal((string) ($paid->aggregate_income ?? 0));
                $row['completed'] = (int) ($paid->aggregate_count ?? 0);
            }

            $series[] = $row;
        }

        return [
            'date' => $now->format('Y-m-d'),
            'generated_at' => now()->toIso8601String(),
            'can_view_revenue' => $canViewRevenue,
            'stats' => $stats,
            'series' => $series,
            'recent' => $this->recent(),
        ];
    }

    /**
     * Notifikasi operasional: cucian siap diambil dan cucian yang melewati estimasi.
     *
     * @return array{count: int, items: array<int, array<string, mixed>>}
     */
    public function notifications(): array
    {
        $ready = LaundryTransaction::query()->withStatus(TransactionStatus::Ready)->count();

        $overdue = LaundryTransaction::query()
            ->inProgress()
            ->whereNotNull('estimated_completion_at')
            ->where('estimated_completion_at', '<', now())
            ->count();

        $items = [];

        if ($ready > 0) {
            $items[] = [
                'key' => 'ready',
                'tone' => 'warning',
                'title' => 'Cucian siap diambil',
                'description' => "{$ready} cucian menunggu diambil customer.",
                'count' => $ready,
                'link' => '/transactions?status=READY',
            ];
        }

        if ($overdue > 0) {
            $items[] = [
                'key' => 'overdue',
                'tone' => 'danger',
                'title' => 'Melewati estimasi',
                'description' => "{$overdue} cucian dalam proses sudah melewati estimasi selesai.",
                'count' => $overdue,
                'link' => '/transactions?status=IN_PROGRESS&overdue=1',
            ];
        }

        return ['count' => $ready + $overdue, 'items' => $items];
    }

    /** Ringkas dan tanpa nomor WhatsApp customer. @return array<int, array<string, mixed>> */
    private function recent(): array
    {
        return LaundryTransaction::query()
            ->with('receiver')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (LaundryTransaction $transaction) => [
                'id' => $transaction->id,
                'code' => $transaction->code,
                'customer_name' => $transaction->customer_name,
                'package_name' => $transaction->package_name,
                'quantity' => (float) $transaction->quantity,
                'unit' => $transaction->package_unit->value,
                'total_price' => (float) $transaction->total_price,
                'status' => $transaction->status->value,
                'status_label' => $transaction->status->label(),
                'status_tone' => $transaction->status->tone(),
                'received_at' => $transaction->received_at?->toIso8601String(),
                'estimated_completion_at' => $transaction->estimated_completion_at?->toIso8601String(),
                'received_by' => $transaction->receiver?->name,
            ])
            ->values()
            ->all();
    }
}