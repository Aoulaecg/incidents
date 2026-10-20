<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SeparateLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->withoutVite(); }

    private function portals(): array
    {
        return [User::ROLE_SUPERVISOR => 'login', User::ROLE_ADMIN => 'admin.login', User::ROLE_DIRECTOR => 'director.login'];
    }

    public function test_supervisor_dropdown_excludes_admins_directors_and_inactive_accounts(): void
    {
        $supervisor = User::factory()->create(['prenom' => 'Rabie', 'nom' => 'Benmahmoud']);
        $admin = User::factory()->create(['prenom' => 'Kenza', 'nom' => 'Raghay', 'role' => User::ROLE_ADMIN]);
        $director = User::factory()->create(['prenom' => 'Hamza', 'nom' => 'El Hajoui', 'role' => User::ROLE_DIRECTOR]);
        $inactive = User::factory()->create(['active' => false]);
        $response = $this->get(route('login'))->assertOk()->assertSee($supervisor->full_name)
            ->assertDontSee($admin->full_name)->assertDontSee($director->full_name)->assertDontSee($inactive->full_name)
            ->assertDontSee(route('admin.login'))->assertDontSee(route('director.login'));
        $this->assertSame([$supervisor->id], $response->viewData('users')->pluck('id')->all());
        $future = User::factory()->create();
        $this->get(route('login'))->assertSee($future->full_name);
    }

    public function test_privileged_login_pages_have_email_fields_without_user_lists_or_account_details(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach (['admin.login' => 'Espace Administrateur', 'director.login' => 'Journal d\'incidents'] as $route => $title) {
            $this->get(route($route))->assertOk()->assertSee($title)->assertSee('name="email"', false)
                ->assertSee('autocomplete="username"', false)->assertSee('name="password"', false)
                ->assertDontSee('<select', false)->assertDontSee($admin->email)->assertDontSee($admin->password);
        }
    }

    public function test_each_role_can_authenticate_only_through_its_own_portal(): void
    {
        foreach ($this->portals() as $accountRole => $_) {
            $account = User::factory()->create(['role' => $accountRole]);
            foreach ($this->portals() as $portalRole => $portal) {
                $data = ['password' => UserFactory::testPassword(), 'role' => $portalRole];
                $data[$portalRole === User::ROLE_SUPERVISOR ? 'user_id' : 'email'] = $portalRole === User::ROLE_SUPERVISOR ? $account->id : $account->email;
                $response = $this->from(route($portal))->post(route($portal.'.store'), $data);
                if ($accountRole === $portalRole) {
                    $response->assertRedirect(route($account->homeRoute()))->assertSessionHas('auth_version', 0);
                    $this->assertAuthenticatedAs($account);
                    $this->post(route('logout'))->assertRedirect(route($account->loginRoute()));
                } else {
                    $response->assertRedirect(route($portal))->assertSessionHasErrors(['password' => 'Connexion impossible. Vérifiez vos identifiants.']);
                    $this->assertGuest();
                }
            }
        }
    }

    public function test_inactive_unknown_and_wrong_password_accounts_have_the_same_generic_error(): void
    {
        foreach ([User::ROLE_ADMIN => 'admin.login', User::ROLE_DIRECTOR => 'director.login'] as $role => $portal) {
            $user = User::factory()->create(['role' => $role, 'active' => false]);
            foreach ([$user->email, 'missing@example.test'] as $email) {
                $this->from(route($portal))->post(route($portal.'.store'), ['email' => $email, 'password' => UserFactory::testPassword()])
                    ->assertSessionHasErrors(['password' => 'Connexion impossible. Vérifiez vos identifiants.']);
                $this->assertGuest();
            }
            $user->forceFill(['active' => true])->save();
            $this->post(route($portal.'.store'), ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors(['password' => 'Connexion impossible. Vérifiez vos identifiants.']);
        }
    }

    public function test_email_validation_preserves_email_but_never_passwords(): void
    {
        $this->post(route('admin.login.store'), [])->assertSessionHasErrors(['email', 'password']);
        $this->post(route('director.login.store'), ['email' => 'invalid', 'password' => 'wrong'])
            ->assertSessionHasErrors('email')->assertSessionHas('_old_input.email', 'invalid')->assertSessionMissing('_old_input.password');
    }

    public function test_authenticated_users_are_redirected_from_all_login_pages_to_their_own_space(): void
    {
        foreach ($this->portals() as $role => $_) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            foreach ($this->portals() as $portal) $this->get(route($portal))->assertRedirect(route($user->homeRoute()));
        }
    }

    public function test_guests_reach_the_matching_login_page_for_each_protected_space(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.supervisors.index'))->assertRedirect(route('admin.login'));
        $this->get(route('director.dashboard'))->assertRedirect(route('director.login'));
    }

    public function test_roles_cannot_open_other_roles_workspaces_even_with_pending_password_changes(): void
    {
        $spaces = [User::ROLE_SUPERVISOR => 'dashboard', User::ROLE_ADMIN => 'admin.supervisors.index', User::ROLE_DIRECTOR => 'director.dashboard'];
        foreach ($spaces as $role => $ownSpace) {
            foreach ([false, true] as $mustChange) {
                $user = User::factory()->create(['role' => $role, 'must_change_password' => $mustChange]);
                $this->actingAs($user);
                foreach ($spaces as $targetRole => $space) {
                    if ($targetRole !== $role) $this->get(route($space))->assertForbidden();
                }
            }
        }
    }

    public function test_logout_invalidates_session_and_redirects_to_the_previous_roles_portal(): void
    {
        foreach ($this->portals() as $role => $portal) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->withSession(['marker' => 'removed']);
            $id = session()->getId();
            $this->post(route('logout'))->assertRedirect(route($portal))->assertSessionMissing('marker');
            $this->assertGuest();
            $this->assertNotSame($id, session()->getId());
        }
    }

    public function test_disabled_and_revoked_privileged_sessions_return_to_their_own_portal(): void
    {
        foreach ([User::ROLE_ADMIN => 'admin.supervisors.index', User::ROLE_DIRECTOR => 'director.dashboard'] as $role => $space) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            $user->forceFill(['active' => false])->save();
            $this->get(route($space))->assertRedirect(route($user->loginRoute()));
            $this->assertGuest();
            $user->forceFill(['active' => true, 'auth_version' => 1])->save();
            $this->actingAs($user)->withSession(['auth_version' => 0])->get(route($space))->assertRedirect(route($user->loginRoute()));
        }
    }

    public function test_email_attempt_limits_are_shared_between_admin_and_direction_portals(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route($i % 2 === 0 ? 'admin.login.store' : 'director.login.store'), ['email' => $admin->email, 'password' => 'wrong']);
        }
        $this->post(route('admin.login.store'), ['email' => mb_strtoupper($admin->email), 'password' => UserFactory::testPassword()])->assertSessionHasErrors('password');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('password'));
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => UserFactory::testPassword()])->assertRedirect(route('admin.supervisors.index'));
    }

    public function test_ip_limit_is_shared_across_all_three_login_portals(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post(route($i % 2 === 0 ? 'admin.login.store' : 'director.login.store'), ['email' => "missing{$i}@example.test", 'password' => 'wrong']);
        }
        $user = User::factory()->create();
        $this->post(route('login.store'), ['user_id' => $user->id, 'password' => UserFactory::testPassword()])->assertSessionHasErrors('password');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('password'));
        $this->assertGuest();
    }

    public function test_privileged_login_pages_use_csrf_and_rotate_sessions_after_success(): void
    {
        foreach ([User::ROLE_ADMIN => 'admin.login', User::ROLE_DIRECTOR => 'director.login'] as $role => $portal) {
            $user = User::factory()->create(['role' => $role]);
            $this->app->detectEnvironment(fn () => 'local');
            $this->post(route($portal.'.store'), ['email' => $user->email, 'password' => UserFactory::testPassword()])->assertStatus(419);
            $this->app->detectEnvironment(fn () => 'testing');
            $this->withSession(['marker' => 'retained']); $id = session()->getId();
            $this->post(route($portal.'.store'), ['email' => $user->email, 'password' => UserFactory::testPassword()])
                ->assertRedirect(route($user->homeRoute()))->assertSessionHas('marker', 'retained');
            $this->assertNotSame($id, session()->getId());
            $this->post(route('logout'));
        }
    }
}
