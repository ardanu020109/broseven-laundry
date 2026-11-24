<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\ReorderRequest;
use App\Http\Requests\Website\SaveProcedureRequest;
use App\Http\Resources\ProcedureResource;
use App\Models\LaundryProcedure;
use App\Services\WebsiteContentService;
use App\Support\ProcedureIcons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProcedureController extends Controller
{
    public function __construct(private readonly WebsiteContentService $content)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('manage-website');

        return ProcedureResource::collection(LaundryProcedure::query()->ordered()->get())
            ->additional(['icons' => ProcedureIcons::options()]);
    }

    public function store(SaveProcedureRequest $request): JsonResponse
    {
        $procedure = $this->content->createProcedure($request->payload(), $request->user());

        return (new ProcedureResource($procedure))
            ->additional(['message' => 'Prosedur berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SaveProcedureRequest $request, LaundryProcedure $procedure): JsonResponse
    {
        $updated = $this->content->updateProcedure($procedure, $request->payload(), $request->user());

        return (new ProcedureResource($updated))
            ->additional(['message' => 'Prosedur berhasil diperbarui.'])
            ->response();
    }

    public function destroy(Request $request, LaundryProcedure $procedure): JsonResponse
    {
        Gate::authorize('manage-website');

        $this->content->deleteProcedure($procedure, $request->user());

        return response()->json(['message' => 'Prosedur dihapus.']);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $this->content->reorder($request->type(), $request->ids(), $request->user());

        return response()->json(['message' => 'Urutan prosedur diperbarui.']);
    }
}