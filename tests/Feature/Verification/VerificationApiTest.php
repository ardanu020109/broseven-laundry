<?php

namespace Tests\Feature\Verification;

use App\Enums\TransactionStatus;
use App\Models\LaundryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTransactions;
use Tests\TestCase;

class VerificationApiTest extends TestCase
{
    use CreatesTransactions, RefreshDatabase;

    public function test_guest_is_rejected(): void
    {
        $this->postJson('/api/verifications/lookup', ['code' => 'BS-261124-0001'])->assertUnauthorized();
        $this->postJson('/api/verifications/complete', ['code' => 'BS-261124-0001'])->assertUnauthorized();
        $this->getJson('/api/transactions/1/qr')->assertUnauthorized();
    }

    public function test_lookup_reports_state_without_leaking_the_qr_token(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->makeTransaction([TransactionStatus::Washing]);
        $token = $transaction->verification->token;

        $response = $this->actingAs($staff)->postJson('/api/verifications/lookup', ['code' => $token])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.can_complete', false)
            ->assertJsonPath('data.reason', 'NOT_READY')
            ->assertJsonPath('data.source', 'qr')
            ->assertJsonPath('data.transaction.code', $transaction->code);

        $this->assertStringNotContainsString($token, $response->getContent());

        $this->actingAs($staff)->postJson('/api/verifications/lookup', ['code' => $token])->assertOk();

        $verification = $transaction->verification()->first();
        $this->assertSame(2, $verification->scan_count);
        $this->assertNotNull($verification->last_scanned_at);
    }

    public function test_lookup_accepts_token_scanned_url_and_transaction_code(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->readyTransaction();
        $token = $transaction->verification->token;

        $this->actingAs($staff);

        $this->postJson('/api/verifications/lookup', ['code' => $token])
            ->assertOk()->assertJsonPath('data.source', 'qr');

        $this->postJson('/api/verifications/lookup', ['code' => "https://laundry.test/verify/{$token}?utm=1"])
            ->assertOk()->assertJsonPath('data.source', 'qr');

        $this->postJson('/api/verifications/lookup', ['code' => '  '.strtolower($transaction->code).'  '])
            ->assertOk()
            ->assertJsonPath('data.source', 'code')
            ->assertJsonPath('data.reason', 'READY')
            ->assertJsonPath('data.can_complete', true);
    }

    public function test_unknown_input_returns_not_found_result(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/verifications/lookup', ['code' => str_repeat('A', 48)])
            ->assertNotFound()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.reason', 'NOT_FOUND');

        $this->postJson('/api/verifications/lookup', ['code' => 'abc'])->assertNotFound();
        $this->postJson('/api/verifications/lookup', ['code' => 'BS-999999-9999'])->assertNotFound();
        $this->postJson('/api/verifications/lookup', [])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_deleted_transactions_are_treated_as_not_found(): void
    {
        $transaction = $this->readyTransaction();
        $token = $transaction->verification->token;
        $transaction->delete();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/verifications/lookup', ['code' => $token])
            ->assertNotFound();
    }

    public function test_ready_transaction_can_be_completed_exactly_once(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->readyTransaction();
        $token = $transaction->verification->token;

        $this->actingAs($staff)->postJson('/api/verifications/complete', ['code' => $token])
            ->assertOk()
            ->assertJsonPath('message', 'Cucian berhasil diserahkan kepada customer.')
            ->assertJsonPath('data.reason', 'COMPLETED')
            ->assertJsonPath('data.transaction.status', 'COMPLETED');

        $transaction->refresh();
        $verification = $transaction->verification()->first();

        $this->assertSame(TransactionStatus::Completed, $transaction->status);
        $this->assertSame($staff->id, $transaction->completed_by);
        $this->assertNotNull($transaction->completed_at);
        $this->assertNotNull($verification->used_at);
        $this->assertSame($staff->id, $verification->used_by);

        // Percobaan kedua ditolak.
        $this->actingAs($staff)->postJson('/api/verifications/complete', ['code' => $token])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->actingAs($staff)->postJson('/api/verifications/lookup', ['code' => $token])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.can_complete', false)
            ->assertJsonPath('data.reason', 'ALREADY_USED');

        // RECEIVED, WASHING, DRYING, READY, COMPLETED: tidak ada histori ganda.
        $this->assertCount(5, $transaction->statusHistories()->get());
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'transaction.picked_up']);
    }

    public function test_transaction_can_be_completed_with_the_manual_code_fallback(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->readyTransaction();

        $this->actingAs($staff)->postJson('/api/verifications/complete', ['code' => $transaction->code])
            ->assertOk()
            ->assertJsonPath('data.source', 'code');

        $this->assertSame(TransactionStatus::Completed, $transaction->fresh()->status);

        $this->actingAs($staff)->postJson('/api/verifications/complete', ['code' => $transaction->code])
            ->assertUnprocessable();
    }

    public function test_transaction_that_is_not_ready_cannot_be_completed(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->makeTransaction([TransactionStatus::Washing]);

        $this->actingAs($staff)
            ->postJson('/api/verifications/complete', ['code' => $transaction->verification->token])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertSame(TransactionStatus::Washing, $transaction->fresh()->status);
    }

    public function test_cancelled_transaction_is_invalid_and_cannot_be_completed(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->makeTransaction([TransactionStatus::Washing, TransactionStatus::Cancelled]);
        $token = $transaction->verification->token;

        $this->actingAs($staff)->postJson('/api/verifications/lookup', ['code' => $token])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.reason', 'CANCELLED');

        $this->actingAs($staff)->postJson('/api/verifications/complete', ['code' => $token])
            ->assertUnprocessable();

        $this->assertSame(TransactionStatus::Cancelled, $transaction->fresh()->status);
    }

    public function test_staff_can_fetch_the_qr_image_of_a_transaction(): void
    {
        $staff = User::factory()->create();
        $transaction = $this->makeTransaction();

        $response = $this->actingAs($staff)->get("/api/transactions/{$transaction->id}/qr")->assertOk();

        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('<svg', $response->getContent());

        $this->actingAs($staff)->getJson('/api/transactions/999999/qr')->assertNotFound();
    }

    public function test_qr_is_created_on_demand_for_transactions_without_verification(): void
    {
        $transaction = LaundryTransaction::factory()->create();
        $this->assertDatabaseCount('laundry_transaction_verifications', 0);

        $this->actingAs(User::factory()->create())
            ->get("/api/transactions/{$transaction->id}/qr")
            ->assertOk();

        $this->assertDatabaseCount('laundry_transaction_verifications', 1);
    }
}