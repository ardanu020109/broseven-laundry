<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\WhatsAppMessageRequest;
use App\Models\LaundryTransaction;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;

class WhatsAppController extends Controller
{
    public function __construct(private readonly WhatsAppService $whatsapp)
    {
    }

    /** Pratinjau pesan dan link WhatsApp. Tidak ada efek samping. */
    public function preview(WhatsAppMessageRequest $request, LaundryTransaction $transaction): JsonResponse
    {
        return response()->json([
            'data' => $this->whatsapp->prepare($transaction, $request->validated('template')),
        ]);
    }

    /** Dipanggil saat tombol "Buka WhatsApp" ditekan: mencatat aktivitas. */
    public function opened(WhatsAppMessageRequest $request, LaundryTransaction $transaction): JsonResponse
    {
        $data = $this->whatsapp->recordOpened(
            $transaction,
            $request->user(),
            $request->validated('template'),
        );

        return response()->json([
            'message' => 'WhatsApp dibuka. Pesan baru terkirim setelah Anda menekan Kirim di aplikasi WhatsApp.',
            'data' => $data,
        ]);
    }
}