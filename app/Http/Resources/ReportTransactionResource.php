<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LaundryTransaction */
class ReportTransactionResource extends JsonResource
{
    /**
     * Khusus laporan (admin). Nomor WhatsApp customer sengaja tidak disertakan.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'customer_name' => $this->customer_name,
            'package_name' => $this->package_name,
            'package_unit' => $this->package_unit->value,
            'package_unit_label' => $this->package_unit->label(),
            'quantity' => (float) $this->quantity,
            'package_price' => (float) $this->package_price,
            'total_price' => (float) $this->total_price,
            'received_at' => $this->received_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'received_by' => $this->whenLoaded(
                'receiver',
                fn () => $this->receiver ? ['id' => $this->receiver->id, 'name' => $this->receiver->name] : null
            ),
            'completed_by' => $this->whenLoaded(
                'completer',
                fn () => $this->completer ? ['id' => $this->completer->id, 'name' => $this->completer->name] : null
            ),
        ];
    }
}