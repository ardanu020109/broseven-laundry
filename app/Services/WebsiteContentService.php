<?php

namespace App\Services;

use App\Models\LaundryFaq;
use App\Models\LaundryPackage;
use App\Models\LaundryProcedure;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan konten website: paket laundry, prosedur, dan FAQ.
 */
class WebsiteContentService
{
    private const ORDERABLE = [
        'packages' => LaundryPackage::class,
        'procedures' => LaundryProcedure::class,
        'faqs' => LaundryFaq::class,
    ];

    public function __construct(private readonly ActivityLogService $activity)
    {
    }

    /* ---------------------------------------------------------------
     | Paket laundry
     | ------------------------------------------------------------- */

    /** @return Collection<int, LaundryPackage> */
    public function listPackages(?string $status, ?string $search): Collection
    {
        $query = LaundryPackage::query()->withCount('transactions');

        match ($status) {
            'archived' => $query->onlyTrashed(),
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => $query,
        };

        $term = trim((string) $search);

        if ($term !== '') {
            $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    /** @param  array<string, mixed>  $data */
    public function createPackage(array $data, User $actor): LaundryPackage
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['sort_order'] ??= ((int) LaundryPackage::withTrashed()->max('sort_order')) + 1;
            $data['is_active'] ??= true;
            $data['features'] ??= [];

            $package = new LaundryPackage();
            $package->fill($data)->save();

            $this->activity->log(
                'package.created',
                "Menambah paket {$package->name}",
                $package,
                ['price' => $data['price'], 'unit' => $data['unit']],
                $actor
            );

            return $package;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function updatePackage(LaundryPackage $package, array $data, User $actor): LaundryPackage
    {
        return DB::transaction(function () use ($package, $data, $actor) {
            $package->fill($data);

            $changes = [];
            foreach ($package->getDirty() as $key => $value) {
                $changes[$key] = ['from' => $package->getRawOriginal($key), 'to' => $value];
            }

            if ($changes !== []) {
                $package->save();

                $this->activity->log(
                    'package.updated',
                    "Mengubah paket {$package->name}",
                    $package,
                    ['changes' => $changes],
                    $actor
                );
            }

            return $package->refresh();
        });
    }

    /** Arsip: paket hilang dari website dan transaksi baru, tetapi data lama tetap utuh. */
    public function archivePackage(LaundryPackage $package, User $actor): void
    {
        DB::transaction(function () use ($package, $actor) {
            $package->forceFill(['is_active' => false])->save();
            $package->delete();

            $this->activity->log('package.archived', "Mengarsipkan paket {$package->name}", $package, [], $actor);
        });
    }

    public function restorePackage(LaundryPackage $package, User $actor): LaundryPackage
    {
        $taken = LaundryPackage::query()->where('name', $package->name)->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'package' => 'Sudah ada paket aktif dengan nama yang sama. Ubah atau arsipkan paket tersebut terlebih dahulu.',
            ]);
        }

        DB::transaction(function () use ($package, $actor) {
            $package->restore();

            $this->activity->log('package.restored', "Memulihkan paket {$package->name}", $package, [], $actor);
        });

        return $package->refresh();
    }

    /* ---------------------------------------------------------------
     | Prosedur
     | ------------------------------------------------------------- */

    /** @param  array<string, mixed>  $data */
    public function createProcedure(array $data, User $actor): LaundryProcedure
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['sort_order'] ??= ((int) LaundryProcedure::query()->max('sort_order')) + 1;
            $data['is_active'] ??= true;

            $procedure = new LaundryProcedure();
            $procedure->fill($data)->save();

            $this->activity->log('procedure.created', "Menambah prosedur {$procedure->title}", $procedure, [], $actor);

            return $procedure;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function updateProcedure(LaundryProcedure $procedure, array $data, User $actor): LaundryProcedure
    {
        return DB::transaction(function () use ($procedure, $data, $actor) {
            $procedure->fill($data);

            if ($procedure->isDirty()) {
                $procedure->save();
                $this->activity->log('procedure.updated', "Mengubah prosedur {$procedure->title}", $procedure, [], $actor);
            }

            return $procedure->refresh();
        });
    }

    public function deleteProcedure(LaundryProcedure $procedure, User $actor): void
    {
        DB::transaction(function () use ($procedure, $actor) {
            $procedure->delete();
            $this->activity->log('procedure.deleted', "Menghapus prosedur {$procedure->title}", null, [], $actor);
        });
    }

    /* ---------------------------------------------------------------
     | FAQ
     | ------------------------------------------------------------- */

    /** @param  array<string, mixed>  $data */
    public function createFaq(array $data, User $actor): LaundryFaq
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['sort_order'] ??= ((int) LaundryFaq::query()->max('sort_order')) + 1;
            $data['is_active'] ??= true;

            $faq = new LaundryFaq();
            $faq->fill($data)->save();

            $this->activity->log('faq.created', 'Menambah FAQ', $faq, ['question' => $faq->question], $actor);

            return $faq;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function updateFaq(LaundryFaq $faq, array $data, User $actor): LaundryFaq
    {
        return DB::transaction(function () use ($faq, $data, $actor) {
            $faq->fill($data);

            if ($faq->isDirty()) {
                $faq->save();
                $this->activity->log('faq.updated', 'Mengubah FAQ', $faq, ['question' => $faq->question], $actor);
            }

            return $faq->refresh();
        });
    }

    public function deleteFaq(LaundryFaq $faq, User $actor): void
    {
        DB::transaction(function () use ($faq, $actor) {
            $faq->delete();
            $this->activity->log('faq.deleted', 'Menghapus FAQ', null, ['question' => $faq->question], $actor);
        });
    }

    /* ---------------------------------------------------------------
     | Urutan
     | ------------------------------------------------------------- */

    /**
     * @param  string  $type  packages | procedures | faqs
     * @param  array<int, int>  $ids  id berurutan sesuai urutan baru
     */
    public function reorder(string $type, array $ids, User $actor): void
    {
        $model = self::ORDERABLE[$type] ?? null;

        abort_if($model === null, 404);

        DB::transaction(function () use ($model, $ids, $type, $actor) {
            foreach (array_values($ids) as $index => $id) {
                $model::query()->whereKey($id)->update(['sort_order' => $index + 1]);
            }

            $this->activity->log("{$type}.reordered", 'Mengubah urutan '.$type, null, ['ids' => array_values($ids)], $actor);
        });
    }
}