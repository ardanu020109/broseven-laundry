<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Verifications\VerificationInputRequest;
use App\Http\Resources\LaundryTransactionResource;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class VerificationController extends Controller
{
    public function __construct(private readonly VerificationService $verifications)
    {
    }

    public function lookup(VerificationInputRequest $request): JsonResponse
    {
        $result = $this->verifications->lookup($request->validated('code'));

        if ($result === null) {
            return $this->notFound();
        }

        return response()->json(['data' => $this->present($result, $request)]);
    }

    public function complete(VerificationInputRequest $request): JsonResponse
    {
        $result = $this->verifications->complete($request->validated('code'), $request->user());

        if ($result === null) {
            return $this->notFound();
        }

        return response()->json([
            'message' => 'Cucian berhasil diserahkan kepada customer.',
            'data' => $this->present($result, $request),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function present(array $result, Request $request): array
    {
        $transaction = $result['transaction']->loadMissing([
            'receiver',
            'completer',
            'verification',
            'statusHistories.changer',
        ]);

        return Arr::except($result, 'transaction') + [
            'transaction' => (new LaundryTransactionResource($transaction))->resolve($request),
        ];
    }

    private function notFound(): JsonResponse
    {
        $message = 'Transaksi tidak ditemukan atau QR Code tidak valid.';

        return response()->json([
            'message' => $message,
            'data' => [
                'valid' => false,
                'can_complete' => false,
                'reason' => 'NOT_FOUND',
                'message' => $message,
                'source' => null,
                'transaction' => null,
            ],
        ], 404);
    }
}