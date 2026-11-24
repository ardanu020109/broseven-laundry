<?php

namespace App\Models;

use App\Enums\PackageUnit;
use App\Enums\TransactionStatus;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaundryTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'laundry_transactions';

    /**
     * Hanya data input dari karyawan. code, tracking_token, snapshot paket,
     * total_price, dan status diisi oleh service backend (forceFill),
     * tidak pernah dari request.
     */
    protected $fillable = [
        'customer_name',
        'customer_phone',
        'package_id',
        'quantity',
        'notes',
        'estimated_completion_at',
    ];

    /** Token tracking tidak ikut serialisasi secara tidak sengaja. */
    protected $hidden = [
        'tracking_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'package_unit' => PackageUnit::class,
            'package_price' => 'decimal:2',
            'quantity' => 'decimal:2',
            'total_price' => 'decimal:2',
            'received_at' => 'datetime',
            'estimated_completion_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------
     | Relasi
     | ------------------------------------------------------------- */

    public function package(): BelongsTo
    {
        return $this->belongsTo(LaundryPackage::class, 'package_id')->withTrashed();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LaundryTransactionStatusHistory::class, 'transaction_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function verification(): HasOne
    {
        return $this->hasOne(LaundryTransactionVerification::class, 'transaction_id');
    }

    /* ---------------------------------------------------------------
     | Scope
     | ------------------------------------------------------------- */

    /** Mencari kode, nama, atau nomor telepon (0812... juga menemukan 62812...). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $phone = strlen($digits) >= 4 ? PhoneNumber::normalize($term) : null;

        return $query->where(function (Builder $q) use ($like, $phone) {
            $q->where('code', 'like', $like)
                ->orWhere('customer_name', 'like', $like)
                ->orWhere('customer_phone', 'like', $like);

            if ($phone) {
                $q->orWhere('customer_phone', 'like', '%'.$phone.'%');
            }
        });
    }

    public function scopeWithStatus(Builder $query, TransactionStatus|string|null $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where(
            'status',
            $status instanceof TransactionStatus ? $status->value : $status
        );
    }

    /** Transaksi yang dihitung sebagai pemasukan: hanya COMPLETED. */
    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::Completed->value)
            ->whereNotNull('completed_at');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn(
            'status',
            array_map(fn (TransactionStatus $s) => $s->value, TransactionStatus::inProgress())
        );
    }
}