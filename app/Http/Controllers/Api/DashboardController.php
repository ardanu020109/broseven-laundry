<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LaundryTransaction;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function show(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LaundryTransaction::class);

        return response()->json(['data' => $this->dashboard->summary($request->user())]);
    }

    public function notifications(): JsonResponse
    {
        Gate::authorize('viewAny', LaundryTransaction::class);

        return response()->json(['data' => $this->dashboard->notifications()]);
    }
}