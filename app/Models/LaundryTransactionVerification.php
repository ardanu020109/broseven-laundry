<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaundryTransactionVerification extends Model
{
    protected $table = 'laundry_transaction_verifications';

    protected $fillable = [
        'transaction_id',
        'token',
        'expires_at',
        'used_at',
        'used_by',
        'scan_count',
        'last_scanned_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'last_scanned_at' => 'datetime',
            'scan_count' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LaundryTransaction::class, 'transaction_id')->withTrashed();
    }

    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by')->withTrashed();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}