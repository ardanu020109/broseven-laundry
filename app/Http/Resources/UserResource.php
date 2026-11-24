<?php

namespace App\Http\Resources;

use App\Support\UserAbilities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'avatar_url' => $this->avatar_url,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            // Hanya disertakan untuk user yang sedang login (bukan daftar karyawan).
            'abilities' => $this->when(
                $request->user()?->is($this->resource),
                fn () => UserAbilities::for($this->resource)
            ),
        ];
    }
}