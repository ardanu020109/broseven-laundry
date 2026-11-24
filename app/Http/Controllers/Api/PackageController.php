<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LaundryPackageResource;
use App\Models\LaundryPackage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PackageController extends Controller
{
    /**
     * Paket aktif untuk dropdown form transaksi (admin dan karyawan).
     * Pengelolaan paket (CRUD) ada di modul admin, Fase 10.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', LaundryPackage::class);

        return LaundryPackageResource::collection(
            LaundryPackage::query()->active()->ordered()->get()
        );
    }
}