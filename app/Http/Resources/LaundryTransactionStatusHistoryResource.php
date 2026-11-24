<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LaundryTransactionStatusHistory */
class LaundryTransactionStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status?->value,
            'from_label' => $this->from_status?->label(),
            'to_status' => $this->to_status->value,
            'to_label' => $this->to_status->label(),
            'note' => $this->note,
            'changed_by' => $this->whenLoaded(
                'changer',
                fn () => $this->changer ? ['id' => $this->changer->id, 'name' => $this->changer->name] : null
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}