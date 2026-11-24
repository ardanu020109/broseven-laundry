<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\ChangeTransactionStatusRequest;
use App\Http\Requests\Transactions\IndexTransactionRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Http\Resources\LaundryTransactionResource;
use App\Models\LaundryTransaction;
use App\Services\LaundryStatusService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactions,
        private readonly LaundryStatusService $statuses,
    ) {
    }

    public function statuses(): JsonResponse
    {
        Gate::authorize('viewAny', LaundryTransaction::class);

        return response()->json([
            'data' => collect(TransactionStatus::cases())->map(fn (TransactionStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'tone' => $status->tone(),
            ])->values(),
        ]);
    }

    public function index(IndexTransactionRequest $request): AnonymousResourceCollection
    {
        return LaundryTransactionResource::collection(
            $this->transactions->paginate($request->validated())
        );
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $transaction = $this->transactions->create($request->validated(), $request->user());

        return (new LaundryTransactionResource($this->loadDetail($transaction)))
            ->additional(['message' => 'Transaksi berhasil dibuat.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(LaundryTransaction $transaction): LaundryTransactionResource
    {
        Gate::authorize('view', $transaction);

        return new LaundryTransactionResource($this->loadDetail($transaction));
    }

    public function update(UpdateTransactionRequest $request, LaundryTransaction $transaction): LaundryTransactionResource
    {
        $updated = $this->transactions->update($transaction, $request->validated(), $request->user());

        return (new LaundryTransactionResource($this->loadDetail($updated)))
            ->additional(['message' => 'Transaksi berhasil diperbarui.']);
    }

    public function destroy(Request $request, LaundryTransaction $transaction): JsonResponse
    {
        Gate::authorize('delete', $transaction);

        $this->transactions->delete($transaction, $request->user());

        return response()->json(['message' => 'Transaksi berhasil dihapus.']);
    }

    public function changeStatus(ChangeTransactionStatusRequest $request, LaundryTransaction $transaction): LaundryTransactionResource
    {
        $updated = $this->statuses->change(
            $transaction,
            $request->targetStatus(),
            $request->user(),
            $request->validated('note'),
        );

        return (new LaundryTransactionResource($this->loadDetail($updated)))
            ->additional(['message' => "Status diperbarui menjadi {$updated->status->label()}."]);
    }

    private function loadDetail(LaundryTransaction $transaction): LaundryTransaction
    {
        return $transaction->load([
            'receiver',
            'completer',
            'verification',
            'statusHistories.changer',
        ]);
    }
}