<?php

namespace Tests\Feature\Website;

use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/packages';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Cuci Kilat',
            'description' => 'Selesai dalam 6 jam',
            'price' => 15000,
            'unit' => 'kg',
            'estimated_hours' => 6,
            'features' => ['Prioritas', 'Dikemas rapi'],
        ], $overrides);
    }

    /* ------------------------------ Akses ------------------------------ */

    public function test_guests_and_employees_are_rejected(): void
    {
        $calls = [
            ['getJson', self::URL],
            ['postJson', self::URL],
            ['putJson', self::URL.'/1'],
            ['deleteJson', self::URL.'/1'],
            ['postJson', self::URL.'/reorder'],
            ['postJson', self::URL.'/1/restore'],
        ];

        foreach ($calls as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        $this->actingAs(User::factory()->create());

        foreach ($calls as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    /* ------------------------------ Buat ------------------------------ */

    public function test_admin_creates_a_package_with_sanitised_input(): void
    {
        $response = $this->actingAs($this->admin)->postJson(self::URL, $this->payload([
            'name' => '  <i>Cuci</i>   Kilat ',
            'features' => ['  Prioritas ', 'Prioritas', '', 'Dikemas rapi'],
        ]))->assertCreated()
            ->assertJsonPath('message', 'Paket berhasil dibuat.')
            ->assertJsonPath('data.name', 'Cuci Kilat')
            ->assertJsonPath('data.unit', 'kg')
            ->assertJsonPath('data.price_label', 'per kg')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.archived', false)
            ->assertJsonPath('data.sort_order', 1)
            ->assertJsonPath('data.features', ['Prioritas', 'Dikemas rapi'])
            ->assertJsonPath('data.transactions_count', 0);

        $this->assertEquals(15000, $response->json('data.price'));

        $this->assertDatabaseHas('laundry_packages', ['name' => 'Cuci Kilat', 'estimated_hours' => 6]);
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'package.created', 'user_id' => $this->admin->id]);

        $this->actingAs($this->admin)->postJson(self::URL, $this->payload(['name' => 'Paket Kedua']))
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 2);
    }

    public function test_package_validation_rules(): void
    {
        $this->actingAs($this->admin);

        $cases = [
            [['name' => ''], 'name'],
            [['price' => 0], 'price'],
            [['price' => 12.345], 'price'],
            [['price' => 20000000], 'price'],
            [['unit' => 'liter'], 'unit'],
            [['estimated_hours' => 0], 'estimated_hours'],
            [['estimated_hours' => 721], 'estimated_hours'],
            [['features' => array_map(fn ($i) => "Fasilitas {$i}", range(1, 13))], 'features'],
        ];

        foreach ($cases as [$override, $field]) {
            $this->postJson(self::URL, $this->payload($override))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertDatabaseCount('laundry_packages', 0);

        $this->postJson(self::URL, $this->payload())->assertCreated();
        $this->postJson(self::URL, $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Nama paket sudah digunakan paket lain.');
    }

    /* ------------------------------ Ubah ------------------------------ */

    public function test_updating_a_package_does_not_change_existing_transactions(): void
    {
        $package = LaundryPackage::factory()->create(['name' => 'Reguler', 'price' => 8000]);
        $transaction = LaundryTransaction::factory()->forPackage($package)->create(['quantity' => 2]);

        $this->assertEquals(16000, $transaction->total_price);

        $this->actingAs($this->admin)->putJson(self::URL.'/'.$package->id, ['price' => 12000])
            ->assertOk()
            ->assertJsonPath('message', 'Paket berhasil diperbarui.')
            ->assertJsonPath('data.transactions_count', 1);

        $this->assertEquals(12000, $package->fresh()->price);

        $transaction->refresh();
        $this->assertEquals(8000, $transaction->package_price);
        $this->assertEquals(16000, $transaction->total_price);

        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'package.updated']);
    }

    public function test_package_names_must_stay_unique_when_updating(): void
    {
        $this->actingAs($this->admin);

        $first = LaundryPackage::factory()->create(['name' => 'Reguler']);
        LaundryPackage::factory()->create(['name' => 'Express']);

        // Nama sendiri boleh dipertahankan.
        $this->putJson(self::URL.'/'.$first->id, ['name' => 'Reguler', 'estimated_hours' => 24])->assertOk();

        $this->putJson(self::URL.'/'.$first->id, ['name' => 'Express'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_deactivating_a_package_hides_it_from_the_website_and_new_transactions(): void
    {
        $package = LaundryPackage::factory()->create(['name' => 'Reguler']);

        $this->actingAs($this->admin)->putJson(self::URL.'/'.$package->id, ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/public/site')->assertJsonCount(0, 'data.packages');

        $this->actingAs(User::factory()->create())->getJson('/api/packages')->assertJsonCount(0, 'data');
    }

    /* ------------------------------ Arsip dan pulihkan ------------------------------ */

    public function test_archiving_keeps_transactions_intact_and_restoring_brings_the_package_back(): void
    {
        $package = LaundryPackage::factory()->create(['name' => 'Reguler']);
        $transaction = LaundryTransaction::factory()->forPackage($package)->create();

        $this->actingAs($this->admin);

        $this->deleteJson(self::URL.'/'.$package->id)
            ->assertOk()
            ->assertJsonPath('message', 'Paket diarsipkan. Transaksi yang sudah ada tidak terpengaruh.');

        $this->assertSoftDeleted('laundry_packages', ['id' => $package->id]);
        $this->assertDatabaseHas('laundry_packages', ['id' => $package->id, 'is_active' => false]);
        $this->assertSame('Reguler', $transaction->fresh()->package->name);
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'package.archived']);

        // Tampil hanya pada filter "diarsipkan".
        $this->getJson(self::URL)->assertJsonCount(0, 'data');
        $this->getJson(self::URL.'?status=archived')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.archived', true);

        // Arsip tidak bisa diubah atau diarsipkan lagi.
        $this->deleteJson(self::URL.'/'.$package->id)->assertNotFound();

        $this->postJson(self::URL.'/'.$package->id.'/restore')
            ->assertOk()
            ->assertJsonPath('data.archived', false)
            ->assertJsonPath('data.is_active', false);

        $this->assertNotSoftDeleted('laundry_packages', ['id' => $package->id]);

        // Paket yang tidak diarsipkan tidak bisa dipulihkan.
        $this->postJson(self::URL.'/'.$package->id.'/restore')->assertNotFound();
    }

    public function test_restoring_is_refused_when_the_name_is_taken(): void
    {
        $this->actingAs($this->admin);

        $old = LaundryPackage::factory()->create(['name' => 'Reguler']);
        $this->deleteJson(self::URL.'/'.$old->id)->assertOk();

        // Nama arsip boleh dipakai paket baru.
        $this->postJson(self::URL, $this->payload(['name' => 'Reguler']))->assertCreated();

        $this->postJson(self::URL.'/'.$old->id.'/restore')
            ->assertUnprocessable()->assertJsonValidationErrors('package');

        $this->assertSoftDeleted('laundry_packages', ['id' => $old->id]);
    }

    /* ------------------------------ Daftar dan urutan ------------------------------ */

    public function test_index_supports_status_and_search_filters(): void
    {
        LaundryPackage::factory()->create(['name' => 'Cuci Kilat']);
        LaundryPackage::factory()->create(['name' => 'Reguler']);
        LaundryPackage::factory()->inactive()->create(['name' => 'Lama']);

        $this->actingAs($this->admin);

        $this->getJson(self::URL)->assertOk()->assertJsonCount(3, 'data');
        $this->getJson(self::URL.'?status=active')->assertJsonCount(2, 'data');
        $this->getJson(self::URL.'?status=inactive')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Lama');
        $this->getJson(self::URL.'?search=kilat')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Cuci Kilat');
        $this->getJson(self::URL.'?status=apa-saja')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_packages_can_be_reordered(): void
    {
        $a = LaundryPackage::factory()->create(['name' => 'A', 'sort_order' => 1]);
        $b = LaundryPackage::factory()->create(['name' => 'B', 'sort_order' => 2]);
        $c = LaundryPackage::factory()->create(['name' => 'C', 'sort_order' => 3]);

        $this->actingAs($this->admin);

        $this->postJson(self::URL.'/reorder', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('message', 'Urutan paket diperbarui.');

        $this->assertSame([$c->id, $a->id, $b->id], LaundryPackage::query()->ordered()->pluck('id')->all());
        $this->getJson(self::URL)->assertJsonPath('data.0.name', 'C');
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'packages.reordered']);
    }

    public function test_reorder_validation(): void
    {
        $a = LaundryPackage::factory()->create(['name' => 'A']);
        $b = LaundryPackage::factory()->create(['name' => 'B']);
        $b->delete();

        $this->actingAs($this->admin);

        $this->postJson(self::URL.'/reorder', [])->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->postJson(self::URL.'/reorder', ['ids' => []])->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->postJson(self::URL.'/reorder', ['ids' => [999999]])->assertUnprocessable();
        $this->postJson(self::URL.'/reorder', ['ids' => [$a->id, $a->id]])->assertUnprocessable();
        $this->postJson(self::URL.'/reorder', ['ids' => [$a->id, $b->id]])->assertUnprocessable(); // paket diarsipkan
    }
}