<?php

namespace Tests\Feature\Transactions;

use App\Enums\PackageUnit;
use App\Enums\TransactionStatus;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    private function package(array $attributes = []): LaundryPackage
    {
        return LaundryPackage::factory()->create(array_merge([
            'price' => 8000,
            'unit' => PackageUnit::Kg,
            'estimated_hours' => 48,
        ], $attributes));
    }

    private function payload(LaundryPackage $package, array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'package_id' => $package->id,
            'quantity' => 2.5,
        ], $overrides);
    }

    private function createTransaction(User $actor, LaundryPackage $package, array $overrides = []): int
    {
        return $this->actingAs($actor)
            ->postJson('/api/transactions', $this->payload($package, $overrides))
            ->assertCreated()
            ->json('data.id');
    }

    /* ------------------------------ Akses ------------------------------ */

    public function test_guest_cannot_access_transactions(): void
    {
        $this->getJson('/api/transactions')->assertUnauthorized();
        $this->postJson('/api/transactions', [])->assertUnauthorized();
        $this->getJson('/api/packages')->assertUnauthorized();
    }

    public function test_staff_can_list_active_packages_only(): void
    {
        $employee = User::factory()->create();
        $this->package(['name' => 'Aktif 1']);
        $this->package(['name' => 'Aktif 2']);
        $this->package(['name' => 'Nonaktif', 'is_active' => false]);

        $this->actingAs($employee)->getJson('/api/packages')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_statuses_endpoint_lists_all_statuses(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->getJson('/api/transactions/statuses')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.0.value', 'RECEIVED');
    }

    /* ------------------------------ Buat ------------------------------ */

    public function test_employee_creates_transaction_and_server_computes_everything(): void
    {
        $employee = User::factory()->create();
        $package = $this->package();

        $response = $this->actingAs($employee)->postJson('/api/transactions', $this->payload($package, [
            'customer_name' => '  Budi   Santoso ',
            'customer_phone' => '0812-3456-7890',
            'notes' => 'Pisahkan baju putih',
            // Field berikut HARUS diabaikan server.
            'total_price' => 1,
            'package_price' => 1,
            'status' => 'COMPLETED',
            'code' => 'HACK',
        ]));

        $response->assertCreated()
            ->assertJsonPath('message', 'Transaksi berhasil dibuat.')
            ->assertJsonPath('data.customer_name', 'Budi Santoso')
            ->assertJsonPath('data.customer_phone', '6281234567890')
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('data.can.update', true)
            ->assertJsonMissingPath('data.tracking_token');

        $this->assertEquals(20000, $response->json('data.total_price'));
        $this->assertEquals(8000, $response->json('data.package_price'));
        $this->assertMatchesRegularExpression('/^BS-\d{6}-0001$/', $response->json('data.code'));
        $this->assertStringContainsString('/tracking/', $response->json('data.tracking_url'));

        $this->assertDatabaseHas('laundry_transactions', [
            'customer_phone' => '6281234567890',
            'status' => 'RECEIVED',
            'received_by' => $employee->id,
        ]);
        $this->assertDatabaseCount('laundry_transaction_status_histories', 1);
        $this->assertDatabaseCount('laundry_transaction_verifications', 1);
        $this->assertDatabaseHas('laundry_activity_logs', [
            'action' => 'transaction.created',
            'user_id' => $employee->id,
        ]);
    }

    public function test_transaction_codes_increment_per_day(): void
    {
        $employee = User::factory()->create();
        $package = $this->package();

        $first = $this->actingAs($employee)->postJson('/api/transactions', $this->payload($package))->json('data.code');
        $second = $this->actingAs($employee)->postJson('/api/transactions', $this->payload($package))->json('data.code');

        $this->assertMatchesRegularExpression('/-0001$/', $first);
        $this->assertMatchesRegularExpression('/-0002$/', $second);
    }

    public function test_price_snapshot_is_kept_when_package_price_changes(): void
    {
        $employee = User::factory()->create();
        $package = $this->package(['price' => 8000]);
        $id = $this->createTransaction($employee, $package);

        $package->update(['price' => 15000]);

        $transaction = LaundryTransaction::findOrFail($id);
        $this->assertEquals(8000, $transaction->package_price);
        $this->assertEquals(20000, $transaction->total_price);
    }

    public function test_store_validation_rules(): void
    {
        $employee = User::factory()->create();
        $package = $this->package();
        $inactive = $this->package(['is_active' => false]);
        $perItem = $this->package(['unit' => PackageUnit::Item, 'price' => 30000]);

        $this->actingAs($employee)->postJson('/api/transactions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_phone', 'package_id', 'quantity']);

        $this->actingAs($employee)->postJson('/api/transactions', $this->payload($package, ['quantity' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->actingAs($employee)->postJson('/api/transactions', $this->payload($package, ['quantity' => 1.234]))
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->actingAs($employee)->postJson('/api/transactions', $this->payload($package, ['customer_phone' => 'abc']))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_phone');

        $this->actingAs($employee)->postJson('/api/transactions', $this->payload($package, ['customer_phone' => '0812']))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_phone');

        $this->actingAs($employee)->postJson('/api/transactions', $this->payload($inactive))
            ->assertUnprocessable()->assertJsonValidationErrors('package_id');

        $this->actingAs($employee)->postJson('/api/transactions', $this->payload($perItem, ['quantity' => 1.5]))
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->actingAs($employee)->postJson('/api/transactions', $this->payload($perItem, ['quantity' => 2]))
            ->assertCreated();

        $this->assertDatabaseCount('laundry_transactions', 1);
    }

    /* ------------------------------ Daftar ------------------------------ */

    public function test_index_supports_search_status_date_filter_and_pagination(): void
    {
        $employee = User::factory()->create();
        $package = $this->package();

        LaundryTransaction::factory()->forPackage($package)->create([
            'code' => 'BS-261101-0001',
            'customer_name' => 'Andi Wijaya',
            'customer_phone' => '6281111111111',
        ]);
        LaundryTransaction::factory()->forPackage($package)->status(TransactionStatus::Washing)->create([
            'code' => 'BS-261101-0002',
            'customer_name' => 'Siti Aminah',
            'customer_phone' => '6282222222222',
        ]);
        LaundryTransaction::factory()->forPackage($package)->status(TransactionStatus::Completed)->create([
            'code' => 'BS-261101-0003',
            'customer_name' => 'Budi Hartono',
            'customer_phone' => '6283333333333',
            'received_at' => now()->subDays(10),
        ]);

        $this->actingAs($employee);

        $this->getJson('/api/transactions')->assertOk()->assertJsonPath('meta.total', 3);
        $this->getJson('/api/transactions?search=siti')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/transactions?search=BS-261101-0003')->assertOk()->assertJsonPath('meta.total', 1);

        // 0812... harus menemukan nomor yang tersimpan sebagai 62812...
        $this->getJson('/api/transactions?search=081111')->assertOk()->assertJsonPath('meta.total', 1);

        $this->getJson('/api/transactions?status=WASHING')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/transactions?status=IN_PROGRESS')->assertOk()->assertJsonPath('meta.total', 2);

        $this->getJson('/api/transactions?date_from='.now()->subDays(4)->toDateString())
            ->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/transactions?date_to='.now()->subDays(5)->toDateString())
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->getJson('/api/transactions?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/transactions?status=SELESAI')->assertUnprocessable();
    }

    /* ------------------------------ Detail ------------------------------ */

    public function test_show_returns_histories_and_hides_tracking_token(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee, $this->package());

        $this->actingAs($employee)->getJson("/api/transactions/{$id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.status_histories')
            ->assertJsonCount(2, 'data.allowed_transitions')
            ->assertJsonPath('data.status_histories.0.to_status', 'RECEIVED')
            ->assertJsonPath('data.verification.used', false)
            ->assertJsonPath('data.received_by.id', $employee->id)
            ->assertJsonMissingPath('data.tracking_token');

        $this->actingAs($employee)->getJson('/api/transactions/999999')->assertNotFound();
    }

    /* ------------------------------ Edit ------------------------------ */

    public function test_changing_quantity_recomputes_total_on_the_server(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee, $this->package());

        $response = $this->actingAs($employee)->putJson("/api/transactions/{$id}", [
            'quantity' => 4,
            'total_price' => 1, // diabaikan
        ])->assertOk();

        $this->assertEquals(32000, $response->json('data.total_price'));
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'transaction.updated']);
    }

    public function test_changing_package_refreshes_the_price_snapshot(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee, $this->package(['price' => 8000]));
        $other = $this->package(['name' => 'Express', 'price' => 12000]);

        $response = $this->actingAs($employee)->patchJson("/api/transactions/{$id}", [
            'package_id' => $other->id,
        ])->assertOk();

        $response->assertJsonPath('data.package_name', 'Express');
        $this->assertEquals(12000, $response->json('data.package_price'));
        $this->assertEquals(30000, $response->json('data.total_price'));
    }

    public function test_cannot_switch_to_inactive_package(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee, $this->package());
        $inactive = $this->package(['is_active' => false]);

        $this->actingAs($employee)->putJson("/api/transactions/{$id}", ['package_id' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('package_id');
    }

    public function test_price_fields_are_locked_after_processing_starts(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee, $this->package());

        LaundryTransaction::findOrFail($id)->forceFill(['status' => TransactionStatus::Washing])->save();

        $this->actingAs($employee)->putJson("/api/transactions/{$id}", ['quantity' => 9])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transaction');

        $this->actingAs($employee)->putJson("/api/transactions/{$id}", ['notes' => 'Catatan baru'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Catatan baru');
    }

    public function test_final_transactions_cannot_be_edited(): void
    {
        $employee = User::factory()->create();
        $id = $this->createTransaction($employee, $this->package());

        LaundryTransaction::findOrFail($id)->forceFill(['status' => TransactionStatus::Completed])->save();

        $this->actingAs($employee)->putJson("/api/transactions/{$id}", ['notes' => 'Ubah'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Transaksi yang sudah selesai atau dibatalkan tidak dapat diubah.');
    }

    /* ------------------------------ Hapus ------------------------------ */

    public function test_delete_rules(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $package = $this->package();

        $id = $this->createTransaction($employee, $package);

        $this->actingAs($employee)->deleteJson("/api/transactions/{$id}")->assertForbidden();
        $this->assertNotSoftDeleted('laundry_transactions', ['id' => $id]);

        $this->actingAs($admin)->deleteJson("/api/transactions/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Transaksi berhasil dihapus.');
        $this->assertSoftDeleted('laundry_transactions', ['id' => $id]);

        $completedId = $this->createTransaction($employee, $package);
        LaundryTransaction::findOrFail($completedId)->forceFill(['status' => TransactionStatus::Completed])->save();

        $this->actingAs($admin)->deleteJson("/api/transactions/{$completedId}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Transaksi yang sudah selesai tidak dapat dihapus.');
        $this->assertNotSoftDeleted('laundry_transactions', ['id' => $completedId]);
    }
}