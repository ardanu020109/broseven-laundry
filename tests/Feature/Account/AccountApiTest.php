<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function upload(User $user, UploadedFile $file)
    {
        return $this->actingAs($user)->post('/api/account/avatar', ['photo' => $file], ['Accept' => 'application/json']);
    }

    private function photo(int $width = 600, int $height = 400): UploadedFile
    {
        return UploadedFile::fake()->image('foto.jpg', $width, $height);
    }

    /* ------------------------------ Akses ------------------------------ */

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/account')->assertUnauthorized();
        $this->putJson('/api/account/profile')->assertUnauthorized();
        $this->putJson('/api/account/password')->assertUnauthorized();
        $this->deleteJson('/api/account/avatar')->assertUnauthorized();
        $this->post('/api/account/avatar', [], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->getJson('/api/avatars/'.str_repeat('a', 40).'.png')->assertUnauthorized();
    }

    public function test_admin_and_employee_see_their_own_profile_with_abilities(): void
    {
        $employee = User::factory()->create(['name' => 'Karyawan Uji']);
        $admin = User::factory()->admin()->create(['name' => 'Admin Uji']);

        $this->actingAs($employee)->getJson('/api/account')
            ->assertOk()
            ->assertJsonPath('data.name', 'Karyawan Uji')
            ->assertJsonPath('data.role', 'employee')
            ->assertJsonPath('data.abilities.manage-transactions', true)
            ->assertJsonPath('data.abilities.manage-employees', false);

        $this->actingAs($admin)->getJson('/api/account')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.abilities.manage-employees', true);
    }

    /* ------------------------------ Profil ------------------------------ */

    public function test_profile_update_sanitises_input_and_ignores_role_and_status(): void
    {
        $user = User::factory()->create(['name' => 'Lama', 'email' => 'lama@broseven.test', 'username' => 'lama']);

        $this->actingAs($user)->putJson('/api/account/profile', [
            'name' => '  <b>Nama</b>   Baru ',
            'phone' => '0812-3456-7891',
            'role' => 'admin',        // diabaikan
            'is_active' => false,     // diabaikan
            'username' => 'peretas',  // diabaikan
        ])->assertOk()
            ->assertJsonPath('message', 'Profil berhasil diperbarui.')
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.phone', '6281234567891')
            ->assertJsonPath('data.role', 'employee')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.username', 'lama');

        $user->refresh();

        $this->assertTrue($user->isEmployee());
        $this->assertTrue($user->is_active);
        $this->assertSame('lama', $user->username);
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'account.profile_updated', 'user_id' => $user->id]);
    }

    public function test_changing_email_requires_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'lama@broseven.test']);
        User::factory()->create(['email' => 'terpakai@broseven.test']);

        $this->actingAs($user);

        $this->putJson('/api/account/profile', ['email' => 'baru@broseven.test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password')
            ->assertJsonPath('errors.current_password.0', 'Masukkan password saat ini untuk mengubah email.');

        $this->putJson('/api/account/profile', ['email' => 'baru@broseven.test', 'current_password' => 'salah'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->putJson('/api/account/profile', ['email' => 'terpakai@broseven.test', 'current_password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame('lama@broseven.test', $user->fresh()->email);

        $this->putJson('/api/account/profile', ['email' => 'Baru@Broseven.Test', 'current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.email', 'baru@broseven.test');

        $this->assertSame('baru@broseven.test', $user->fresh()->email);
    }

    public function test_keeping_the_same_email_does_not_need_a_password(): void
    {
        $user = User::factory()->create(['email' => 'lama@broseven.test']);

        $this->actingAs($user)->putJson('/api/account/profile', ['name' => 'Nama Lain', 'email' => 'LAMA@broseven.test'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Lain');
    }

    public function test_profile_validation_and_clearing_the_phone(): void
    {
        $user = User::factory()->create(['phone' => '6281111111111']);

        $this->actingAs($user);

        $this->putJson('/api/account/profile', ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson('/api/account/profile', ['email' => ''])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson('/api/account/profile', ['email' => 'bukan-email'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->putJson('/api/account/profile', ['phone' => '0812'])->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->putJson('/api/account/profile', ['phone' => null])->assertOk()->assertJsonPath('data.phone', null);
    }


    /* ------------------------------ Foto profil ------------------------------ */

    public function test_avatar_is_cropped_reencoded_privately_and_served_to_staff_only(): void
    {
        $user = User::factory()->create();

        $response = $this->upload($user, $this->photo())
            ->assertOk()
            ->assertJsonPath('message', 'Foto profil berhasil diperbarui.');

        $url = $response->json('data.avatar_url');
        $this->assertMatchesRegularExpression('~^/api/avatars/[A-Za-z0-9]{40}\.png$~', $url);

        $path = 'avatars/'.basename($url);
        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $user->fresh()->avatar_path);

        // Disimpan ulang sebagai PNG persegi 256 x 256.
        $info = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame(256, $info[0]);
        $this->assertSame(256, $info[1]);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);

        // Staff yang login dapat melihat; tamu tidak.
        $served = $this->actingAs($user)->get($url)->assertOk();
        $this->assertStringContainsString('image/png', $served->headers->get('Content-Type'));
        $this->assertSame('nosniff', $served->headers->get('X-Content-Type-Options'));

        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();

        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'account.avatar_updated', 'user_id' => $user->id]);
    }

    public function test_replacing_and_removing_the_avatar_deletes_old_files(): void
    {
        $user = User::factory()->create();

        $first = basename($this->upload($user, $this->photo())->assertOk()->json('data.avatar_url'));
        $second = basename($this->upload($user->fresh(), $this->photo(300, 500))->assertOk()->json('data.avatar_url'));

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing('avatars/'.$first);
        Storage::disk('local')->assertExists('avatars/'.$second);

        $this->actingAs($user->fresh())->deleteJson('/api/account/avatar')
            ->assertOk()
            ->assertJsonPath('message', 'Foto profil dihapus.')
            ->assertJsonPath('data.avatar_url', null);

        Storage::disk('local')->assertMissing('avatars/'.$second);
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'account.avatar_removed']);

        // Menghapus lagi tidak menimbulkan error.
        $this->actingAs($user->fresh())->deleteJson('/api/account/avatar')->assertOk();
    }

    public function test_unsafe_or_oversized_photos_are_rejected_and_nothing_is_stored(): void
    {
        $user = User::factory()->create();

        $rejected = [
            UploadedFile::fake()->create('foto.svg', 10, 'image/svg+xml'),
            UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->createWithContent('palsu.png', 'ini bukan gambar'),
            UploadedFile::fake()->image('besar.png')->size(3000),
            UploadedFile::fake()->image('lebar.png', 3500, 100),
        ];

        foreach ($rejected as $file) {
            $this->upload($user, $file)->assertUnprocessable()->assertJsonValidationErrors('photo');
        }

        $this->actingAs($user)->postJson('/api/account/avatar', [])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_avatar_route_only_serves_uploaded_png_files(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->getJson('/api/avatars/'.str_repeat('a', 40).'.png')->assertNotFound();  // tidak ada
        $this->getJson('/api/avatars/'.str_repeat('a', 40).'.svg')->assertNotFound();  // ekstensi tidak diizinkan
        $this->getJson('/api/avatars/'.str_repeat('a', 39).'.png')->assertNotFound();  // nama terlalu pendek
        $this->getJson('/api/avatars/..%2F..%2F.env')->assertNotFound();               // path traversal
    }
}
