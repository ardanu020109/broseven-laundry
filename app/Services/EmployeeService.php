<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\LaundryActivityLog;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Pengelolaan karyawan oleh admin. Role selalu "employee"; tidak pernah berasal dari request.
 */
class EmployeeService
{
    /** Kelompok aktivitas yang bisa difilter (awalan kolom action). */
    public const ACTIVITY_GROUPS = ['transaction', 'whatsapp', 'auth', 'account'];

    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly UserSessionService $sessions,
    ) {
    }

    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $term = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'all';

        return User::query()
            ->where('role', UserRole::Employee->value)
            ->withCount(['receivedTransactions', 'completedTransactions'])
            ->withMax('activityLogs', 'created_at')
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($term !== '', function (Builder $query) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $digits = preg_replace('/\D+/', '', $term) ?? '';
                $phone = strlen($digits) >= 4 ? PhoneNumber::normalize($term) : null;

                // 0812... juga menemukan nomor yang tersimpan sebagai 62812...
                $query->where(function (Builder $inner) use ($like, $phone) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);

                    if ($phone) {
                        $inner->orWhere('phone', 'like', '%'.$phone.'%');
                    }
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $employee = new User();
            $employee->fill(Arr::only($data, ['name', 'username', 'email', 'phone']));
            $employee->password = $data['password']; // di-hash oleh cast "hashed"

            // role dan status tidak pernah dibaca dari request.
            $employee->forceFill([
                'role' => UserRole::Employee,
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();

            $this->activity->log(
                'employee.created',
                "Menambah karyawan {$employee->name}",
                $employee,
                ['email' => $employee->email],
                $actor
            );

            return $employee;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $employee, array $data, User $actor): User
    {
        return DB::transaction(function () use ($employee, $data, $actor) {
            $employee->fill(Arr::only($data, ['name', 'username', 'email', 'phone']));

            $changed = array_keys($employee->getDirty());

            if ($changed !== []) {
                $employee->save();

                $this->activity->log(
                    'employee.updated',
                    "Mengubah data karyawan {$employee->name}",
                    $employee,
                    ['fields' => $changed],
                    $actor
                );
            }

            return $employee->refresh();
        });
    }

    public function setActive(User $employee, bool $active, User $actor): User
    {
        return DB::transaction(function () use ($employee, $active, $actor) {
            if ($employee->is_active === $active) {
                return $employee;
            }

            $employee->forceFill(['is_active' => $active])->save();

            // Penonaktifan langsung berlaku: semua sesi dan token diakhiri.
            if (! $active) {
                $this->sessions->revokeAll($employee);
            }

            $this->activity->log(
                $active ? 'employee.activated' : 'employee.deactivated',
                ($active ? 'Mengaktifkan' : 'Menonaktifkan')." karyawan {$employee->name}",
                $employee,
                [],
                $actor
            );

            return $employee->refresh();
        });
    }

    public function resetPassword(User $employee, string $password, User $actor): void
    {
        DB::transaction(function () use ($employee, $password, $actor) {
            $employee->password = $password;
            $employee->save();

            $this->sessions->revokeAll($employee);

            // Password tidak pernah ikut dicatat di log.
            $this->activity->log(
                'employee.password_reset',
                "Mengatur ulang password karyawan {$employee->name}",
                $employee,
                [],
                $actor
            );
        });
    }

    public function activities(User $employee, ?string $group, int $perPage): LengthAwarePaginator
    {
        return LaundryActivityLog::query()
            ->where('user_id', $employee->getKey())
            ->when(
                $group !== null && in_array($group, self::ACTIVITY_GROUPS, true),
                fn (Builder $query) => $query->where('action', 'like', $group.'.%')
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}