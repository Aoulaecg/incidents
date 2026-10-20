<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_temporary_passwords_redirect_login_and_block_all_internal_pages(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $this->post(route('login.store'), ['user_id' => $user->id, 'password' => \Database\Factories\UserFactory::testPassword()])->assertRedirect(route('password.change'));
        $this->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->get(route('admin.supervisors.index'))->assertForbidden();
        $this->get(route('password.change'))->assertOk()->assertDontSee($user->password);
    }

    public function test_an_admin_with_a_temporary_password_cannot_bypass_the_change_step(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'must_change_password' => true]);
        $this->actingAs($admin)->post(route('admin.supervisors.store'), [])->assertRedirect(route('password.change'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_new_personal_password_is_hashed_and_current_session_is_rotated_and_retained(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $password = Str::password(24);
        $this->actingAs($user)->withSession(['auth_version' => 0]);
        $previousSessionId = session()->getId();
        $this->put(route('password.update'), [
            'current_password' => \Database\Factories\UserFactory::testPassword(), 'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect(route('dashboard'))->assertSessionHas('auth_version', 1);
        $this->assertNotSame($previousSessionId, session()->getId());
        $user->refresh();
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->withSession(['auth_version' => 0])->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_returns_to_administration_after_changing_temporary_password(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'must_change_password' => true]);
        $password = Str::password(24);
        $this->actingAs($admin)->put(route('password.update'), [
            'current_password' => \Database\Factories\UserFactory::testPassword(), 'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect(route('admin.supervisors.index'));
        $this->get(route('admin.supervisors.index'))->assertOk();
    }

    public function test_wrong_current_password_confirmation_and_weak_password_do_not_change_the_account(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $hash = $user->password;
        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'wrong', 'password' => 'weak', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['current_password', 'password'])
            ->assertSessionMissing('_old_input.current_password')->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_passwords_over_bcrypt_byte_limit_are_rejected(): void
    {
        $user = User::factory()->create();
        $password = 'Aa1!'.str_repeat('é', 40);
        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => \Database\Factories\UserFactory::testPassword(), 'password' => $password, 'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');
    }

    public function test_password_change_is_protected_by_csrf_and_request_throttling(): void
    {
        $user = User::factory()->create();
        $this->app->detectEnvironment(fn () => 'local');
        $this->actingAs($user)->put(route('password.update'), [])->assertStatus(419);
        $this->app->detectEnvironment(fn () => 'testing');
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->put(route('password.update'), [])->assertSessionHasErrors();
        }
        $this->put(route('password.update'), [])->assertStatus(429);
    }
}
