<?php

namespace App\Http\Resources;

use App\Support\AppUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LaundryTransaction */
class LaundryTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,

            'package_id' => $this->package_id,
            'package_name' => $this->package_name,
            'package_unit' => $this->package_unit->value,
            'package_unit_label' => $this->package_unit->label(),
            'package_price_label' => $this->package_unit->priceLabel(),
            'package_price' => (float) $this->package_price,
            'quantity' => (float) $this->quantity,
            'total_price' => (float) $this->total_price,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'allowed_transitions' => collect($this->status->allowedTransitions())
                ->map(fn ($status) => ['value' => $status->value, 'label' => $status->label()])
                ->values(),

            'notes' => $this->notes,
            'cancel_reason' => $this->cancel_reason,

            'received_at' => $this->received_at?->toIso8601String(),
            'estimated_completion_at' => $this->estimated_completion_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'received_by' => $this->whenLoaded(
                'receiver',
                fn () => $this->receiver ? ['id' => $this->receiver->id, 'name' => $this->receiver->name] : null
            ),
            'completed_by' => $this->whenLoaded(
                'completer',
                fn () => $this->completer ? ['id' => $this->completer->id, 'name' => $this->completer->name] : null
            ),

            // Token tracking tidak pernah dikirim mentah, hanya URL lengkapnya.
            'tracking_url' => AppUrl::to('/tracking/'.$this->tracking_token),
            'qr_url' => '/api/transactions/'.$this->id.'/qr',

            'verification' => $this->whenLoaded(
                'verification',
                fn () => $this->verification ? [
                    'used' => $this->verification->used_at !== null,
                    'used_at' => $this->verification->used_at?->toIso8601String(),
                    'scan_count' => $this->verification->scan_count,
                ] : null
            ),

            'status_histories' => LaundryTransactionStatusHistoryResource::collection(
                $this->whenLoaded('statusHistories')
            ),

            // Untuk UX frontend (tombol). Keputusan akhir tetap di backend.
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'change_status' => (bool) $user?->can('changeStatus', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }
}