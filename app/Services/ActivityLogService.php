<?php

namespace App\Services;

use App\Models\LaundryActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLogService
{
    /**
     * Mencatat aktivitas pengguna (dipakai untuk histori aktivitas karyawan).
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $user = null,
    ): LaundryActivityLog {
        $request = request();
        $userAgent = $request?->userAgent();

        $log = new LaundryActivityLog();
        $log->forceFill([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'description' => Str::limit($description, 255, ''),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent ? Str::limit($userAgent, 255, '') : null,
        ])->save();

        return $log;
    }
}