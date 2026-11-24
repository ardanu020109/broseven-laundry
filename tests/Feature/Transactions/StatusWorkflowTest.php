<?php

namespace Tests\Feature\Transactions;

use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createTransaction(User $actor): int
    {
        $package = LaundryPackage::factory()->create(['price' => 8000, 'estimated_hours' => 48]);

        return $this->actingAs($actor)->postJson('/api/transactions', [
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'package_id' => $package->id,
            'quantity' => 3,
        ])->assertCreated()->json('data.id');
    }

    private function changeStatus(User $actor, int $id, string $status, ?string $note = null)
    {
        return $this->actingAs($actor)->postJson("/api/transactions/{$id}/status", array_filter([
            'status' => $status,
            'note' => $note,
        ], fn ($value) => $value !== null));
    }

    public function test_guest_cannot_change_status(): void
    {
        $this->postJson('/api/transactions/1/status', ['status' => 'WASHING'])->assertUnauthorized();
    }

    public function test_full_workflow_from_received_to_completed(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $id = $this->createTransaction($admin);

        foreach (['WASHING', 'DRYING', 'IRONING', 'READY'] as $status) {
            $this->changeStatus($employee, $id, $status)
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        $this->changeStatus($employee, $id, 'COMPLETED')
            ->assertOk()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('message', 'Status diperbarui menjadi Selesai.')
            ->assertJsonPath('data.verification.used', true);

        $transaction = LaundryTransaction::with(['verification', 'statusHistories'])->findOrFail($id);

        $this->assertNotNull($transaction->ready_at);
        $this->assertNotNull($transaction->completed_at);
        $this->assertSame($employee->id, $transaction->completed_by);
        $this->assertNotNull($transaction->verification->used_at);
        $this->assertSame($employee->id, $transaction->verification->used_by);

        // 1 histori awal + 5 perubahan status
        $this->assertCount(6, $transaction->statusHistories);
        $last = $transaction->statusHistories->last();
        $this->assertSame('READY', $last->from_status->value);
        $this->assertSame('COMPLETED', $last->to_status->value);
        $this->assertSame($employee->id, $last->changed_by);

        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'transaction.status_changed']);
    }

    public function test_ironing_can_be_skipped(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee);

        $this->changeStatus($employee, $id, 'WASHING')->assertOk();
        $this->changeStatus($employee, $id, 'DRYING')->assertOk();
        $this->changeStatus($employee, $id, 'READY')->assertOk();
    }

    public function test_invalid_jumps_are_rejected(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee);

        $this->changeStatus($employee, $id, 'READY')
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->changeStatus($employee, $id, 'COMPLETED')
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->changeStatus($employee, $id, 'RECEIVED')
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame('RECEIVED', LaundryTransaction::findOrFail($id)->status->value);

        $this->changeStatus($employee, $id, 'WASHING')->assertOk();
        $this->changeStatus($employee, $id, 'WASHING')->assertUnprocessable();
    }

    public function test_unknown_status_value_is_rejected(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee);

        $this->changeStatus($employee, $id, 'SELESAI')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->changeStatus($employee, $id, 'washing')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_cancel_requires_a_reason_and_is_final(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee);

        $this->changeStatus($employee, $id, 'CANCELLED')
            ->assertUnprocessable()->assertJsonValidationErrors('note');

        $this->changeStatus($employee, $id, 'CANCELLED', 'Customer membatalkan pesanan')
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED')
            ->assertJsonPath('data.cancel_reason', 'Customer membatalkan pesanan');

        $transaction = LaundryTransaction::findOrFail($id);
        $this->assertNotNull($transaction->cancelled_at);
        $this->assertNull($transaction->completed_at);

        // Status final: tidak bisa diubah lagi.
        $this->changeStatus($employee, $id, 'WASHING')
            ->assertForbidden()
            ->assertJsonPath('message', 'Transaksi yang sudah selesai atau dibatalkan tidak dapat diubah.');
    }

    public function test_completed_transaction_cannot_be_completed_twice(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee);

        foreach (['WASHING', 'DRYING', 'READY', 'COMPLETED'] as $status) {
            $this->changeStatus($employee, $id, $status)->assertOk();
        }

        $this->changeStatus($employee, $id, 'COMPLETED')->assertForbidden();
        $this->changeStatus($employee, $id, 'CANCELLED', 'Coba batalkan')->assertForbidden();

        $this->assertSame('COMPLETED', LaundryTransaction::findOrFail($id)->status->value);
        $this->assertDatabaseCount('laundry_transaction_status_histories', 5);
    }
}