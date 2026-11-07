<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_valid_login_uses_the_existing_user_table_and_hash(): void
    {
        $user = $this->createUser('admin', 'correct-password', 'Administrator');

        $this->assertNotSame('correct-password', $user->password_hash);
        $this->assertTrue(Hash::check('correct-password', $user->password_hash));

        $this->post(route('login.store'), [
            'username' => 'admin',
            'password' => 'correct-password',
        ])->assertRedirect(route('products.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_or_inactive_credentials_are_rejected(): void
    {
        $this->createUser('inactive', 'correct-password', 'Administrator', false);

        $this->from(route('login'))->post(route('login.store'), [
            'username' => 'missing',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->from(route('login'))->post(route('login.store'), [
            'username' => 'inactive',
            'password' => 'correct-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $user = $this->createUser('admin', 'correct-password', 'Administrator');

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_anonymous_users_are_redirected_from_catalog_pages(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->get(route('categories.index'))->assertRedirect(route('login'));
    }

    public function test_existing_roles_grant_read_only_access_without_write_permission(): void
    {
        $user = $this->createUser('sales', 'correct-password', 'Sales User');

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('products.create'))
            ->assertForbidden();
    }

    public function test_admin_bootstrap_is_one_time_and_assigns_the_existing_role(): void
    {
        $this->artisan('app:admin-create')
            ->expectsQuestion('اسم المستخدم', 'first-admin')
            ->expectsQuestion('الاسم الظاهر', 'مدير النظام')
            ->expectsQuestion('كلمة المرور', 'a-long-secure-password')
            ->expectsQuestion('تأكيد كلمة المرور', 'a-long-secure-password')
            ->assertExitCode(0);

        $user = User::query()->where('username', 'first-admin')->firstOrFail();
        $role = Role::query()->where('name', 'Administrator')->firstOrFail();

        $this->assertTrue(Hash::check('a-long-secure-password', $user->password_hash));
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
        ]);

        $this->artisan('app:admin-create')->assertExitCode(1);
        $this->assertSame(1, User::query()->count());
    }

    public function test_product_deletion_route_is_not_available(): void
    {
        $this->assertFalse(Route::has('products.destroy'));
    }

    private function createUser(
        string $username,
        string $password,
        string $roleName,
        bool $isActive = true,
    ): User {
        $user = User::query()->create([
            'username' => $username,
            'display_name' => ucfirst($username),
            'password_hash' => Hash::make($password),
            'is_active' => $isActive,
        ]);

        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
