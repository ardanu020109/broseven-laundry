<?php

namespace Tests\Feature\Reports;

use App\Support\ReportRange;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ReportRangeTest extends TestCase
{
    private function now(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-24 10:30:00', 'Asia/Makassar');
    }

    private function fmt(CarbonImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function test_today(): void
    {
        $range = ReportRange::resolve('today', null, null, $this->now());

        $this->assertSame('2026-11-24 00:00:00', $this->fmt($range->from));
        $this->assertSame('2026-11-24 23:59:59', $this->fmt($range->to));
        $this->assertSame(ReportRange::HOUR, $range->granularity);
        $this->assertSame(1, $range->days);
        $this->assertSame('24/11/2026', $range->label());
    }

    public function test_this_month(): void
    {
        $range = ReportRange::resolve('this_month', null, null, $this->now());

        $this->assertSame('2026-11-01 00:00:00', $this->fmt($range->from));
        $this->assertSame('2026-11-30 23:59:59', $this->fmt($range->to));
        $this->assertSame(ReportRange::DAY, $range->granularity);
        $this->assertSame(30, $range->days);
        $this->assertSame('2026-11-24', $range->chartTo->format('Y-m-d'));
        $this->assertSame('01/11/2026 - 30/11/2026', $range->label());
    }

    public function test_this_year(): void
    {
        $range = ReportRange::resolve('this_year', null, null, $this->now());

        $this->assertSame('2026-01-01 00:00:00', $this->fmt($range->from));
        $this->assertSame('2026-12-31 23:59:59', $this->fmt($range->to));
        $this->assertSame(ReportRange::MONTH, $range->granularity);
        $this->assertSame(365, $range->days);
        $this->assertSame('2026-11-24', $range->chartTo->format('Y-m-d'));
    }

    public function test_unknown_period_falls_back_to_this_month(): void
    {
        $range = ReportRange::resolve('apa-saja', null, null, $this->now());

        $this->assertSame('this_month', $range->period);
        $this->assertSame('2026-11-01', $range->from->format('Y-m-d'));
    }

    public function test_custom_range_and_granularity_thresholds(): void
    {
        $single = ReportRange::resolve('custom', '2026-05-05', '2026-05-05', $this->now());
        $this->assertSame(ReportRange::HOUR, $single->granularity);
        $this->assertSame(1, $single->days);

        // 92 hari (maksimal untuk grafik harian)
        $daily = ReportRange::resolve('custom', '2026-01-01', '2026-04-02', $this->now());
        $this->assertSame(92, $daily->days);
        $this->assertSame(ReportRange::DAY, $daily->granularity);

        // 93 hari beralih ke grafik bulanan
        $monthly = ReportRange::resolve('custom', '2026-01-01', '2026-04-03', $this->now());
        $this->assertSame(93, $monthly->days);
        $this->assertSame(ReportRange::MONTH, $monthly->granularity);
    }

    public function test_chart_end_is_never_before_the_start(): void
    {
        $range = ReportRange::resolve('custom', '2027-01-01', '2027-01-10', $this->now());

        $this->assertSame('2027-01-01', $range->chartTo->format('Y-m-d'));
    }

    public function test_query_bounds_are_converted_to_the_application_timezone(): void
    {
        $range = ReportRange::resolve('today', null, null, $this->now());

        $this->assertSame('2026-11-24 00:00:00', $this->fmt($range->queryFrom()));

        // Data tersimpan dalam zona waktu aplikasi: batas harian WITA harus ikut dikonversi.
        config(['app.timezone' => 'UTC']);

        $this->assertSame('2026-11-23 16:00:00', $this->fmt($range->queryFrom()));
        $this->assertSame('2026-11-24 15:59:59', $this->fmt($range->queryTo()));
    }

    public function test_days_between_and_to_array(): void
    {
        $this->assertSame(1, ReportRange::daysBetween('2026-11-24', '2026-11-24'));
        $this->assertSame(30, ReportRange::daysBetween('2026-11-01', '2026-11-30'));

        $array = ReportRange::resolve('this_month', null, null, $this->now())->toArray();

        $this->assertSame('this_month', $array['period']);
        $this->assertSame('2026-11-01', $array['date_from']);
        $this->assertSame('2026-11-30', $array['date_to']);
        $this->assertSame('Asia/Makassar', $array['timezone']);
    }
}