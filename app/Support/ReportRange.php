<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Rentang waktu laporan. Batas hari selalu mengikuti zona waktu Asia/Makassar,
 * lalu dikonversi ke zona waktu aplikasi saat dipakai untuk query database.
 */
final class ReportRange
{
    public const TIMEZONE = 'Asia/Makassar';

    public const TODAY = 'today';
    public const THIS_MONTH = 'this_month';
    public const THIS_YEAR = 'this_year';
    public const CUSTOM = 'custom';

    public const PERIODS = [self::TODAY, self::THIS_MONTH, self::THIS_YEAR, self::CUSTOM];

    public const HOUR = 'hour';
    public const DAY = 'day';
    public const MONTH = 'month';

    /** Rentang kustom maksimal 5 tahun. */
    public const MAX_DAYS = 1830;

    /** Sampai batas ini grafik per hari; lebih dari itu per bulan. */
    private const DAY_GRANULARITY_LIMIT = 92;

    private function __construct(
        public readonly string $period,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly CarbonImmutable $chartTo,
        public readonly string $granularity,
        public readonly int $days,
    ) {
    }

    public static function resolve(
        string $period,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?CarbonInterface $now = null,
    ): self {
        $now = CarbonImmutable::instance($now ?? now())->setTimezone(self::TIMEZONE);

        [$from, $to] = match ($period) {
            self::TODAY => [$now->startOfDay(), $now->endOfDay()],
            self::THIS_YEAR => [$now->startOfYear(), $now->endOfYear()],
            self::CUSTOM => [
                self::parseDate($dateFrom)->startOfDay(),
                self::parseDate($dateTo)->endOfDay(),
            ],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };

        $period = in_array($period, self::PERIODS, true) ? $period : self::THIS_MONTH;
        $days = self::spanInDays($from, $to);

        $granularity = match (true) {
            $days <= 1 => self::HOUR,
            $days <= self::DAY_GRANULARITY_LIMIT => self::DAY,
            default => self::MONTH,
        };

        // Grafik tidak menampilkan hari/bulan yang belum terjadi.
        $chartTo = $to < $now ? $to : $now;
        if ($chartTo < $from) {
            $chartTo = $from;
        }

        return new self($period, $from, $to, $chartTo, $granularity, $days);
    }

    /** Jumlah hari (inklusif) antara dua tanggal berformat Y-m-d. */
    public static function daysBetween(string $dateFrom, string $dateTo): int
    {
        return self::spanInDays(self::parseDate($dateFrom), self::parseDate($dateTo));
    }

    /** Batas bawah untuk query, dalam zona waktu aplikasi (tempat data disimpan). */
    public function queryFrom(): CarbonImmutable
    {
        return $this->from->setTimezone((string) config('app.timezone'));
    }

    /** Batas atas untuk query, dalam zona waktu aplikasi. */
    public function queryTo(): CarbonImmutable
    {
        return $this->to->setTimezone((string) config('app.timezone'));
    }

    public function label(): string
    {
        $from = $this->from->format('d/m/Y');
        $to = $this->to->format('d/m/Y');

        return $from === $to ? $from : "{$from} - {$to}";
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'date_from' => $this->from->format('Y-m-d'),
            'date_to' => $this->to->format('Y-m-d'),
            'label' => $this->label(),
            'days' => $this->days,
            'granularity' => $this->granularity,
            'timezone' => self::TIMEZONE,
        ];
    }

    private static function parseDate(?string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', (string) $date, self::TIMEZONE);

        if (! $parsed instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Format tanggal harus YYYY-MM-DD.');
        }

        return $parsed;
    }

    private static function spanInDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        // Asia/Makassar tidak memakai DST, sehingga 1 hari selalu 86.400 detik.
        return intdiv($to->startOfDay()->getTimestamp() - $from->startOfDay()->getTimestamp(), 86400) + 1;
    }
}