<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\EmployeeActivityRequest;
use App\Http\Requests\Employees\EmployeeIndexRequest;
use App\Http\Requests\Employees\ResetEmployeePasswordRequest;
use App\Http\Requests\Employees\SetEmployeeStatusRequest;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\EmployeeResource;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employees)
    {
    }

    public function index(EmployeeIndexRequest $request): AnonymousResourceCollection
    {
        return EmployeeResource::collection($this->employees->paginate($request->validated()));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employees->create($request->payload(), $request->user());

        return (new EmployeeResource($this->present($employee)))
            ->additional(['message' => 'Karyawan berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateEmployeeRequest $request, User $employee): JsonResponse
    {
        $updated = $this->employees->update($employee, $request->payload(), $request->user());

        return (new EmployeeResource($this->present($updated)))
            ->additional(['message' => 'Data karyawan berhasil diperbarui.'])
            ->response();
    }

    public function changeStatus(SetEmployeeStatusRequest $request, User $employee): JsonResponse
    {
        $updated = $this->employees->setActive($employee, $request->boolean('is_active'), $request->user());

        return (new EmployeeResource($this->present($updated)))
            ->additional(['message' => $updated->is_active ? 'Karyawan diaktifkan.' : 'Karyawan dinonaktifkan.'])
            ->response();
    }

    public function resetPassword(ResetEmployeePasswordRequest $request, User $employee): JsonResponse
    {
        $this->employees->resetPassword($employee, $request->validated('password'), $request->user());

        return response()->json([
            'message' => 'Password karyawan berhasil diatur ulang.',
        ]);
    }

    public function activities(EmployeeActivityRequest $request, User $employee): AnonymousResourceCollection
    {
        return ActivityLogResource::collection(
            $this->employees->activities(
                $employee,
                $request->validated('group'),
                (int) ($request->validated('per_page') ?? 15),
            )
        );
    }

    private function present(User $employee): User
    {
        return $employee
            ->loadCount(['receivedTransactions', 'completedTransactions'])
            ->loadMax('activityLogs', 'created_at');
    }
}