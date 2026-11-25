<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin \App\Models\User */
class EmployeeResource extends JsonResource
{
    /** Tanpa password, token, maupun alamat IP login. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'is_active' => (bool) $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'transactions_received' => (int) ($this->received_transactions_count ?? 0),
            'transactions_completed' => (int) ($this->completed_transactions_count ?? 0),
            'last_activity_at' => $this->activity_logs_max_created_at
                ? Carbon::parse($this->activity_logs_max_created_at)->toIso8601String()
                : null,
        ];
    }
}