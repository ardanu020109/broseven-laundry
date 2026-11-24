<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LaundryPackage */
class AdminPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'unit' => $this->unit->value,
            'unit_label' => $this->unit->label(),
            'price_label' => $this->unit->priceLabel(),
            'estimated_hours' => $this->estimated_hours,
            'features' => $this->features ?? [],
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'archived' => $this->trashed(),
            'transactions_count' => $this->whenCounted('transactions'),
        ];
    }
}