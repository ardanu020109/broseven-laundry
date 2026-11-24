<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaundryTransactionStatusHistory extends Model
{
    /** Tabel ini hanya memiliki created_at (histori tidak pernah diubah). */
    const UPDATED_AT = null;

    protected $table = 'laundry_transaction_status_histories';

    protected $fillable = [
        'transaction_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => TransactionStatus::class,
            'to_status' => TransactionStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LaundryTransaction::class, 'transaction_id')->withTrashed();
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }
}