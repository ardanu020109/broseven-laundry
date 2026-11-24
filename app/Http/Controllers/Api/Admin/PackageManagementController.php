<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\ReorderRequest;
use App\Http\Requests\Website\SavePackageRequest;
use App\Http\Resources\AdminPackageResource;
use App\Models\LaundryPackage;
use App\Services\WebsiteContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PackageManagementController extends Controller
{
    public function __construct(private readonly WebsiteContentService $content)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', LaundryPackage::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive', 'archived'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return AdminPackageResource::collection(
            $this->content->listPackages($filters['status'] ?? null, $filters['search'] ?? null)
        );
    }

    public function store(SavePackageRequest $request): JsonResponse
    {
        $package = $this->content->createPackage($request->payload(), $request->user());

        return (new AdminPackageResource($package->loadCount('transactions')))
            ->additional(['message' => 'Paket berhasil dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SavePackageRequest $request, LaundryPackage $package): JsonResponse
    {
        $updated = $this->content->updatePackage($package, $request->payload(), $request->user());

        return (new AdminPackageResource($updated->loadCount('transactions')))
            ->additional(['message' => 'Paket berhasil diperbarui.'])
            ->response();
    }

    public function destroy(Request $request, LaundryPackage $package): JsonResponse
    {
        Gate::authorize('delete', $package);

        $this->content->archivePackage($package, $request->user());

        return response()->json([
            'message' => 'Paket diarsipkan. Transaksi yang sudah ada tidak terpengaruh.',
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $package = LaundryPackage::onlyTrashed()->findOrFail($id);

        Gate::authorize('restore', $package);

        $restored = $this->content->restorePackage($package, $request->user());

        return (new AdminPackageResource($restored->loadCount('transactions')))
            ->additional(['message' => 'Paket dipulihkan. Aktifkan kembali agar tampil di website.'])
            ->response();
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $this->content->reorder($request->type(), $request->ids(), $request->user());

        return response()->json(['message' => 'Urutan paket diperbarui.']);
    }
}