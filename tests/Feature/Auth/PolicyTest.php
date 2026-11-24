<?php

namespace Tests\Feature\Auth;

use App\Enums\TransactionStatus;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\User;
use App\Policies\LaundryPackagePolicy;
use App\Policies\LaundryTransactionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policies_are_auto_discovered(): void
    {
        $this->assertInstanceOf(
            LaundryTransactionPolicy::class,
            Gate::getPolicyFor(LaundryTransaction::class)
        );
        $this->assertInstanceOf(
            LaundryPackagePolicy::class,
            Gate::getPolicyFor(LaundryPackage::class)
        );
        $this->assertInstanceOf(UserPolicy::class, Gate::getPolicyFor(User::class));
    }

    public function test_gates_follow_the_user_role(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        foreach (['view-revenue', 'view-reports', 'manage-website', 'manage-employees'] as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability), "admin harus boleh: {$ability}");
            $this->assertTrue(Gate::forUser($employee)->denies($ability), "karyawan harus ditolak: {$ability}");
        }

        $this->assertTrue(Gate::forUser($admin)->allows('manage-transactions'));
        $this->assertTrue(Gate::forUser($employee)->allows('manage-transactions'));
    }

    public function test_inactive_users_are_denied_everything(): void
    {
        $inactiveAdmin = User::factory()->admin()->inactive()->create();

        foreach (['manage-transactions', 'view-revenue', 'view-reports', 'manage-website', 'manage-employees'] as $ability) {
            $this->assertTrue(Gate::forUser($inactiveAdmin)->denies($ability), "akun nonaktif harus ditolak: {$ability}");
        }

        $this->assertFalse($inactiveAdmin->can('viewAny', LaundryTransaction::class));
        $this->assertFalse($inactiveAdmin->can('create', LaundryPackage::class));
        $this->assertFalse($inactiveAdmin->can('viewAny', User::class));
    }

    public function test_transaction_policy_rules(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $received = LaundryTransaction::factory()->create();
        $completed = LaundryTransaction::factory()->status(TransactionStatus::Completed)->create();
        $cancelled = LaundryTransaction::factory()->status(TransactionStatus::Cancelled)->create();

        // Melihat dan membuat
        $this->assertTrue($employee->can('viewAny', LaundryTransaction::class));
        $this->assertTrue($employee->can('create', LaundryTransaction::class));
        $this->assertTrue($employee->can('view', $completed));

        // Edit dan ubah status hanya selama belum final
        $this->assertTrue($employee->can('update', $received));
        $this->assertFalse($employee->can('update', $completed));
        $this->assertFalse($admin->can('update', $cancelled));
        $this->assertTrue($employee->can('changeStatus', $received));
        $this->assertFalse($employee->can('changeStatus', $completed));
        $this->assertFalse($admin->can('changeStatus', $cancelled));

        // Hapus: hanya admin, COMPLETED tidak pernah boleh dihapus
        $this->assertFalse($employee->can('delete', $received));
        $this->assertTrue($admin->can('delete', $received));
        $this->assertTrue($admin->can('delete', $cancelled));
        $this->assertFalse($admin->can('delete', $completed));

        // Hapus permanen tidak pernah diizinkan
        $this->assertFalse($admin->can('forceDelete', $received));
    }

    public function test_package_policy_rules(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $package = LaundryPackage::factory()->create();

        $this->assertTrue($employee->can('viewAny', LaundryPackage::class));
        $this->assertTrue($employee->can('view', $package));

        $this->assertFalse($employee->can('create', LaundryPackage::class));
        $this->assertFalse($employee->can('update', $package));
        $this->assertFalse($employee->can('delete', $package));

        $this->assertTrue($admin->can('create', LaundryPackage::class));
        $this->assertTrue($admin->can('update', $package));
        $this->assertTrue($admin->can('delete', $package));
        $this->assertFalse($admin->can('forceDelete', $package));
    }

    public function test_user_policy_rules(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $otherEmployee = User::factory()->create();

        // Daftar dan pembuatan akun: hanya admin
        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertFalse($employee->can('viewAny', User::class));
        $this->assertTrue($admin->can('create', User::class));
        $this->assertFalse($employee->can('create', User::class));

        // Admin hanya mengelola akun karyawan
        $this->assertTrue($admin->can('update', $employee));
        $this->assertFalse($admin->can('update', $otherAdmin));

        // Karyawan tidak bisa mengelola siapa pun lewat policy ini
        $this->assertFalse($employee->can('update', $otherEmployee));
        $this->assertFalse($employee->can('update', $employee));

        // Melihat: diri sendiri atau admin
        $this->assertTrue($employee->can('view', $employee));
        $this->assertFalse($employee->can('view', $otherEmployee));
        $this->assertTrue($admin->can('view', $employee));

        // Hapus
        $this->assertTrue($admin->can('delete', $employee));
        $this->assertFalse($admin->can('delete', $admin));
        $this->assertFalse($admin->can('forceDelete', $employee));
    }
}