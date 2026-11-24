<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\ReorderRequest;
use App\Http\Requests\Website\SaveFaqRequest;
use App\Http\Resources\FaqResource;
use App\Models\LaundryFaq;
use App\Services\WebsiteContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FaqController extends Controller
{
    public function __construct(private readonly WebsiteContentService $content)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('manage-website');

        return FaqResource::collection(LaundryFaq::query()->ordered()->get());
    }

    public function store(SaveFaqRequest $request): JsonResponse
    {
        $faq = $this->content->createFaq($request->payload(), $request->user());

        return (new FaqResource($faq))
            ->additional(['message' => 'FAQ berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SaveFaqRequest $request, LaundryFaq $faq): JsonResponse
    {
        $updated = $this->content->updateFaq($faq, $request->payload(), $request->user());

        return (new FaqResource($updated))
            ->additional(['message' => 'FAQ berhasil diperbarui.'])
            ->response();
    }

    public function destroy(Request $request, LaundryFaq $faq): JsonResponse
    {
        Gate::authorize('manage-website');

        $this->content->deleteFaq($faq, $request->user());

        return response()->json(['message' => 'FAQ dihapus.']);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $this->content->reorder($request->type(), $request->ids(), $request->user());

        return response()->json(['message' => 'Urutan FAQ diperbarui.']);
    }
}