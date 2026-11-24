<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Http\Resources\ReportTransactionResource;
use App\Services\ActivityLogService;
use App\Services\LaundryReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly LaundryReportService $reports,
        private readonly ActivityLogService $activity,
    ) {
    }

    /** Ringkasan, data grafik, rincian per paket, dan opsi filter. */
    public function show(ReportFilterRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reports->report($request->range(), $request->packageId()),
        ]);
    }

    /** Rincian transaksi (pagination dan pencarian). */
    public function transactions(ReportFilterRequest $request): AnonymousResourceCollection
    {
        return ReportTransactionResource::collection(
            $this->reports->transactions(
                $request->range(),
                $request->packageId(),
                $request->searchTerm(),
                $request->perPage(),
            )
        );
    }

    /** Data lengkap untuk dicetak. */
    public function print(ReportFilterRequest $request): JsonResponse
    {
        $range = $request->range();
        $data = $this->reports->printable($range, $request->packageId());

        $data['transactions'] = ReportTransactionResource::collection($data['transactions'])->resolve($request);

        $this->activity->log(
            'report.printed',
            "Mencetak laporan pemasukan {$range->label()}",
            null,
            ['period' => $range->period, 'package_id' => $request->packageId()],
            $request->user()
        );

        return response()->json(['data' => $data]);
    }

    /** Unduh CSV. */
    public function export(ReportFilterRequest $request): StreamedResponse
    {
        $range = $request->range();
        $packageId = $request->packageId();
        $delimiter = $request->csvDelimiter();

        $this->activity->log(
            'report.exported',
            "Mengunduh laporan pemasukan {$range->label()} (CSV)",
            null,
            ['period' => $range->period, 'package_id' => $packageId],
            $request->user()
        );

        return response()->streamDownload(
            function () use ($range, $packageId, $delimiter) {
                $output = fopen('php://output', 'w');

                $this->reports->writeCsv($output, $range, $packageId, $delimiter);

                fclose($output);
            },
            $this->reports->csvFilename($range),
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}