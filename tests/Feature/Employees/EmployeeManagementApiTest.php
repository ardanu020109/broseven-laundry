<?php

namespace Tests\Feature\Employees;

use App\Enums\UserRole;
use App\Models\LaundryTransaction;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/employees';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Admin Utama']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Siti Aminah',
            'username' => 'siti.aminah',
            'email' => 'siti@broseven.test',
            'phone' => '0812-3456-7890',
            'password' => 'Sandi12345',
        ], $overrides);
    }

    private function employee(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /* ------------------------------ Akses ------------------------------ */

    public function test_guests_and_employees_are_rejected_even_for_unknown_ids(): void
    {
        $calls = [
            ['getJson', self::URL],
            ['postJson', self::URL],
            ['putJson', self::URL.'/999999'],
            ['patchJson', self::URL.'/999999/status'],
            ['postJson', self::URL.'/999999/password'],
            ['getJson', self::URL.'/999999/activities'],
        ];

        foreach ($calls as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        // 403 (bukan 404) walau ID tidak ada: otorisasi berjalan sebelum route model binding.
        $this->actingAs($this->employee());

        foreach ($calls as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    /* ------------------------------ Tambah ------------------------------ */

    public function test_admin_creates_an_employee_and_can_never_create_admins(): void
    {
        $response = $this->actingAs($this->admin)->postJson(self::URL, $this->payload([
            'name' => '  <b>Siti</b>   Aminah ',
            'username' => '  Siti.Aminah ',
            'email' => ' SITI@Broseven.Test ',
            'role' => 'admin',        // harus diabaikan
            'is_active' => false,     // harus diabaikan
        ]))->assertCreated()
            ->assertJsonPath('message', 'Karyawan berhasil ditambahkan.')
            ->assertJsonPath('data.name', 'Siti Aminah')
            ->assertJsonPath('data.username', 'siti.aminah')
            ->assertJsonPath('data.email', 'siti@broseven.test')
            ->assertJsonPath('data.phone', '6281234567890')
            ->assertJsonPath('data.role', 'employee')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.transactions_received', 0)
            ->assertJsonMissingPath('data.password');

        $user = User::findOrFail($response->json('data.id'));

        $this->assertSame(UserRole::Employee, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotSame('Sandi12345', $user->password);
        $this->assertTrue(Hash::check('Sandi12345', $user->password));

        $this->assertDatabaseHas('laundry_activity_logs', [
            'action' => 'employee.created',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_username_and_phone_are_optional(): void
    {
        $this->actingAs($this->admin);

        $this->postJson(self::URL, $this->payload(['username' => null, 'phone' => null, 'email' => 'a@broseven.test']))
            ->assertCreated()
            ->assertJsonPath('data.username', null)
            ->assertJsonPath('data.phone', null);

        $this->postJson(self::URL, $this->payload(['username' => null, 'phone' => null, 'email' => 'b@broseven.test']))
            ->assertCreated();
    }

    public function test_creation_validation(): void
    {
        $this->actingAs($this->admin);

        User::factory()->create(['email' => 'ada@broseven.test', 'username' => 'ada']);
        User::factory()->create(['email' => 'lama@broseven.test', 'username' => 'lama'])->delete();

        $cases = [
            [['name' => ''], 'name'],
            [['email' => ''], 'email'],
            [['email' => 'bukan-email'], 'email'],
            [['email' => 'ada@broseven.test'], 'email'],
            [['email' => 'lama@broseven.test'], 'email'],   // akun yang dihapus tetap memegang email
            [['username' => 'ad'], 'username'],
            [['username' => 'pakai spasi'], 'username'],
            [['username' => 'a@b.com'], 'username'],
            [['username' => 'ada'], 'username'],
            [['username' => 'lama'], 'username'],
            [['phone' => '0812'], 'phone'],
            [['phone' => 'abc'], 'phone'],
            [['password' => ''], 'password'],
            [['password' => 'Pendek1'], 'password'],
            [['password' => 'hanyahuruf'], 'password'],
            [['password' => '1234567890'], 'password'],
        ];

        foreach ($cases as [$override, $field]) {
            $this->postJson(self::URL, $this->payload($override))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $this->assertSame(3, User::withTrashed()->count());
    }

    /* ------------------------------ Daftar ------------------------------ */

    public function test_index_lists_only_employees_with_filters_and_counts(): void
    {
        $siti = $this->employee(['name' => 'Siti Aminah', 'email' => 'siti@broseven.test', 'username' => 'siti', 'phone' => '6281111111111']);
        $budi = $this->employee(['name' => 'Budi Santoso', 'email' => 'budi@broseven.test', 'username' => 'budi', 'phone' => '6282222222222', 'is_active' => false]);

        LaundryTransaction::factory()->count(2)->create(['received_by' => $siti->id]);
        LaundryTransaction::factory()->create(['received_by' => $budi->id, 'completed_by' => $siti->id]);

        app(ActivityLogService::class)->log('auth.login', 'Login ke aplikasi', null, [], $siti);

        $this->actingAs($this->admin);

        $response = $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('meta.total', 2); // admin tidak ikut terdaftar

        // Aktif lebih dulu, lalu urut nama.
        $this->assertSame(['Siti Aminah', 'Budi Santoso'], collect($response->json('data'))->pluck('name')->all());

        $first = $response->json('data.0');
        $this->assertSame(2, $first['transactions_received']);
        $this->assertSame(1, $first['transactions_completed']);
        $this->assertNotNull($first['last_activity_at']);
        $this->assertArrayNotHasKey('password', $first);

        $second = $response->json('data.1');
        $this->assertSame(1, $second['transactions_received']);
        $this->assertSame(0, $second['transactions_completed']);
        $this->assertNull($second['last_activity_at']);

        $this->getJson(self::URL.'?status=active')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Siti Aminah');
        $this->getJson(self::URL.'?status=inactive')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Budi Santoso');
        $this->getJson(self::URL.'?search=budi')->assertJsonPath('meta.total', 1);
        $this->getJson(self::URL.'?search=siti@')->assertJsonPath('meta.total', 1);
        $this->getJson(self::URL.'?search=0822')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Budi Santoso');
        $this->getJson(self::URL.'?search=tidak-ada')->assertJsonPath('meta.total', 0);

        $this->getJson(self::URL.'?status=archived')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson(self::URL.'?per_page=500')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    /* ------------------------------ Ubah ------------------------------ */

    public function test_admin_updates_an_employee_but_not_role_status_or_password(): void
    {
        $employee = $this->employee(['name' => 'Siti', 'email' => 'siti@broseven.test', 'username' => 'siti']);

        $this->actingAs($this->admin)->putJson(self::URL.'/'.$employee->id, [
            'name' => 'Siti Aminah',
            'username' => 'Siti.A',
            'email' => 'Siti.Baru@Broseven.Test',
            'phone' => '0813-9999-8888',
            'role' => 'admin',          // diabaikan
            'is_active' => false,       // diabaikan
            'password' => 'Abaikan123', // diabaikan
        ])->assertOk()
            ->assertJsonPath('message', 'Data karyawan berhasil diperbarui.')
            ->assertJsonPath('data.name', 'Siti Aminah')
            ->assertJsonPath('data.username', 'siti.a')
            ->assertJsonPath('data.email', 'siti.baru@broseven.test')
            ->assertJsonPath('data.phone', '6281399998888');

        $employee->refresh();

        $this->assertSame(UserRole::Employee, $employee->role);
        $this->assertTrue($employee->is_active);
        $this->assertTrue(Hash::check('password', $employee->password)); // password bawaan factory, tidak berubah

        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'employee.updated', 'user_id' => $this->admin->id]);
    }

    public function test_update_keeps_uniqueness_and_allows_clearing_optional_fields(): void
    {
        $employee = $this->employee(['email' => 'siti@broseven.test', 'username' => 'siti', 'phone' => '6281111111111']);
        $this->employee(['email' => 'lain@broseven.test', 'username' => 'lain']);

        $this->actingAs($this->admin);

        // Memakai email/username sendiri tetap boleh.
        $this->putJson(self::URL.'/'.$employee->id, ['email' => 'siti@broseven.test', 'username' => 'siti'])->assertOk();

        $this->putJson(self::URL.'/'.$employee->id, ['email' => 'lain@broseven.test'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->putJson(self::URL.'/'.$employee->id, ['username' => 'lain'])
            ->assertUnprocessable()->assertJsonValidationErrors('username');

        $this->putJson(self::URL.'/'.$employee->id, ['username' => null, 'phone' => null])
            ->assertOk()
            ->assertJsonPath('data.username', null)
            ->assertJsonPath('data.phone', null);

        $this->putJson(self::URL.'/'.$employee->id, ['name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_admin_accounts_cannot_be_managed_through_this_module(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->admin);

        foreach ([$this->admin->id, $otherAdmin->id] as $id) {
            $this->putJson(self::URL.'/'.$id, ['name' => 'Diubah'])->assertNotFound();
            $this->patchJson(self::URL.'/'.$id.'/status', ['is_active' => false])->assertNotFound();
            $this->postJson(self::URL.'/'.$id.'/password', ['password' => 'Sandi12345'])->assertNotFound();
            $this->getJson(self::URL.'/'.$id.'/activities')->assertNotFound();
        }

        $this->assertTrue($otherAdmin->fresh()->is_active);
        $this->assertNotSame('Diubah', $this->admin->fresh()->name);
    }

    public function test_reactivation_and_repeated_toggles_are_not_logged_twice(): void
    {
        $employee = $this->employee(['is_active' => false]);

        $this->actingAs($this->admin);

        $this->patchJson(self::URL.'/'.$employee->id.'/status', ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('message', 'Karyawan diaktifkan.')
            ->assertJsonPath('data.is_active', true);

        $this->patchJson(self::URL.'/'.$employee->id.'/status', ['is_active' => true])->assertOk();

        $this->assertTrue($employee->fresh()->is_active);
        $this->assertDatabaseCount('laundry_activity_logs', 1);
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'employee.activated']);
    }

    public function test_status_validation(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->admin);

        $this->patchJson(self::URL.'/'.$employee->id.'/status', [])
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');

        $this->patchJson(self::URL.'/'.$employee->id.'/status', ['is_active' => 'mungkin'])
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');
    }

    public function test_password_reset_validation(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->admin);

        foreach (['', 'Pendek1', 'hanyahuruf', '1234567890'] as $weak) {
            $this->postJson(self::URL.'/'.$employee->id.'/password', ['password' => $weak])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }

        $this->assertTrue(Hash::check('password', $employee->fresh()->password));
    }

    /* ------------------------------ Riwayat aktivitas ------------------------------ */

    public function test_activity_history_is_scoped_filtered_and_privacy_friendly(): void
    {
        $employee = $this->employee();
        $other = $this->employee();

        $activity = app(ActivityLogService::class);
        $activity->log('transaction.created', 'Membuat transaksi BS-1', null, ['rahasia' => 'nilai-internal'], $employee);
        $activity->log('whatsapp.opened', 'Membuka WhatsApp', null, [], $employee);
        $activity->log('auth.login', 'Login ke aplikasi', null, [], $employee);
        $activity->log('transaction.created', 'Milik karyawan lain', null, [], $other);

        $this->actingAs($this->admin);

        $response = $this->getJson(self::URL.'/'.$employee->id.'/activities')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonMissingPath('data.0.properties')
            ->assertJsonMissingPath('data.0.user_agent');

        // Terbaru lebih dulu.
        $this->assertSame(
            ['Login ke aplikasi', 'Membuka WhatsApp', 'Membuat transaksi BS-1'],
            collect($response->json('data'))->pluck('description')->all()
        );

        $this->assertStringNotContainsString('nilai-internal', $response->getContent());
        $this->assertStringNotContainsString('Milik karyawan lain', $response->getContent());

        $this->getJson(self::URL.'/'.$employee->id.'/activities?group=transaction')->assertJsonPath('meta.total', 1);
        $this->getJson(self::URL.'/'.$employee->id.'/activities?group=whatsapp')->assertJsonPath('meta.total', 1);
        $this->getJson(self::URL.'/'.$employee->id.'/activities?group=auth')->assertJsonPath('meta.total', 1);
        $this->getJson(self::URL.'/'.$employee->id.'/activities?group=account')->assertJsonPath('meta.total', 0);
        $this->getJson(self::URL.'/'.$employee->id.'/activities?group=lainnya')->assertUnprocessable()->assertJsonValidationErrors('group');

        $this->getJson(self::URL.'/'.$employee->id.'/activities?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
    }
}
