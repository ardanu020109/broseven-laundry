<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Menjalankan RoleMiddleware langsung dan mengembalikan kode status
     * (200 lolos, 401 belum login, 403 ditolak).
     */
    private function runRole(?User $user, string ...$roles): int
    {
        $request = Request::create('/api/uji', 'GET');
        $request->setUserResolver(fn () => $user);

        try {
            $response = (new RoleMiddleware())->handle(
                $request,
                fn () => new Response('ok'),
                ...$roles
            );

            return $response->getStatusCode();
        } catch (AuthenticationException) {
            return 401;
        } catch (AccessDeniedHttpException) {
            return 403;
        }
    }

    /* ----------------------- RoleMiddleware (langsung) ----------------------- */

    public function test_role_middleware_allows_admin_on_admin_and_staff_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertSame(200, $this->runRole($admin, 'admin'));
        $this->assertSame(200, $this->runRole($admin, 'admin', 'employee'));
    }

    public function test_role_middleware_allows_employee_on_staff_routes_only(): void
    {
        $employee = User::factory()->create();

        $this->assertSame(200, $this->runRole($employee, 'admin', 'employee'));
        $this->assertSame(200, $this->runRole($employee, 'employee'));
        $this->assertSame(403, $this->runRole($employee, 'admin'));
    }

    public function test_role_middleware_rejects_guests_with_401(): void
    {
        $this->assertSame(401, $this->runRole(null, 'admin'));
        $this->assertSame(401, $this->runRole(null, 'admin', 'employee'));
    }

    public function test_role_middleware_fails_closed_for_unknown_role_names(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertSame(403, $this->runRole($admin, 'superuser'));
        $this->assertSame(403, $this->runRole($admin));
    }

    public function test_role_middleware_denial_message_is_in_indonesian(): void
    {
        $employee = User::factory()->create();

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Anda tidak memiliki izin untuk mengakses fitur ini.');

        $request = Request::create('/api/uji', 'GET');
        $request->setUserResolver(fn () => $employee);

        (new RoleMiddleware())->handle($request, fn () => new Response('ok'), 'admin');
    }

    /* ----------------------------- Route asli ----------------------------- */

    public function test_guest_gets_401_on_protected_routes(): void
    {
        $this->getJson('/api/packages')->assertUnauthorized();
        $this->getJson('/api/transactions')->assertUnauthorized();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_staff_can_reach_staff_routes(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/packages')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->getJson('/api/packages')->assertOk();
    }

    public function test_inactive_user_is_rejected_with_401_on_real_routes(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->getJson('/api/packages')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');

        $this->actingAs($user)->getJson('/api/transactions')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    /* ------------------------- Abilities dan lainnya ------------------------- */

    public function test_me_endpoint_returns_abilities_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.abilities.manage-transactions', true)
            ->assertJsonPath('data.abilities.view-revenue', true)
            ->assertJsonPath('data.abilities.view-reports', true)
            ->assertJsonPath('data.abilities.manage-website', true)
            ->assertJsonPath('data.abilities.manage-employees', true);
    }

    public function test_me_endpoint_hides_admin_abilities_from_employee(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'employee')
            ->assertJsonPath('data.abilities.manage-transactions', true)
            ->assertJsonPath('data.abilities.view-revenue', false)
            ->assertJsonPath('data.abilities.view-reports', false)
            ->assertJsonPath('data.abilities.manage-website', false)
            ->assertJsonPath('data.abilities.manage-employees', false);
    }

    public function test_role_and_active_flags_are_not_mass_assignable(): void
    {
        $user = new User([
            'name' => 'Penyusup',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $this->assertArrayNotHasKey('role', $user->getAttributes());
        $this->assertArrayNotHasKey('is_active', $user->getAttributes());
        $this->assertSame('Penyusup', $user->name);
    }

    public function test_unknown_api_route_returns_json_404(): void
    {
        $this->getJson('/api/tidak-ada')
            ->assertNotFound()
            ->assertJsonPath('message', 'Data atau alamat yang diminta tidak ditemukan.');
    }
}
