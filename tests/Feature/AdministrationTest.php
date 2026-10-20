<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function administrator(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function accountData(array $overrides = []): array
    {
        $password = Str::password(24);

        return array_replace([
            'prenom' => 'Salma', 'nom' => 'Alaoui', 'email' => 'salma@example.test',
            'role' => User::ROLE_SUPERVISOR, 'active' => '1',
            'parking_ids' => [\App\Models\Parking::firstOrCreate(['nom' => 'Parking de test'])->id],
            'password' => $password, 'password_confirmation' => $password,
        ], $overrides);
    }

    public function test_guests_and_supervisors_cannot_access_any_administration_action(): void
    {
        $supervisor = User::factory()->create();
        $this->get(route('admin.supervisors.index'))->assertRedirect(route('admin.login'));
        $this->actingAs($supervisor);

        foreach (['index', 'create', 'edit', 'deactivate', 'password.edit'] as $action) {
            $this->get(route('admin.supervisors.'.$action, in_array($action, ['index', 'create']) ? [] : $supervisor))->assertForbidden();
        }
        $this->post(route('admin.supervisors.store'), $this->accountData(['role' => User::ROLE_ADMIN]))->assertForbidden();
        $this->put(route('admin.supervisors.update', $supervisor), ['role' => User::ROLE_ADMIN])->assertForbidden();
        $this->patch(route('admin.supervisors.status', $supervisor), ['active' => 0])->assertForbidden();
        $this->put(route('admin.supervisors.password.reset', $supervisor), $this->accountData())->assertForbidden();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame(User::ROLE_SUPERVISOR, $supervisor->fresh()->role);
    }

    public function test_administrator_login_and_root_redirect_to_administration(): void
    {
        $admin = $this->administrator();
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => \Database\Factories\UserFactory::testPassword()])
            ->assertRedirect(route('admin.supervisors.index'));
        $this->get('/')->assertRedirect(route('admin.supervisors.index'));
        $this->get(route('login'))->assertRedirect(route('admin.supervisors.index'));
    }

    public function test_a_stale_admin_identity_cannot_mutate_accounts_after_its_role_is_revoked(): void
    {
        $admin = $this->administrator();
        $this->administrator();
        $staleIdentity = clone $admin;
        $admin->forceFill(['role' => User::ROLE_SUPERVISOR])->save();
        $target = User::factory()->create();
        $this->actingAs($staleIdentity)->post(route('admin.supervisors.store'), $this->accountData())->assertForbidden();
        $this->put(route('admin.supervisors.update', $target), $this->accountData(['email' => $target->email]))->assertForbidden();
        $this->put(route('admin.supervisors.password.reset', $target), $this->accountData())->assertForbidden();
        $this->assertDatabaseCount('users', 3);
    }

    public function test_administrator_can_create_a_real_account_with_a_hashed_initial_password(): void
    {
        $this->actingAs($this->administrator());
        $data = $this->accountData(['email' => 'SALMA@example.test']);
        $this->post(route('admin.supervisors.store'), $data)->assertRedirect(route('admin.supervisors.index'))
            ->assertSessionHas('status', 'Le compte a été créé avec succès');
        $user = User::where('email', 'salma@example.test')->firstOrFail();
        $this->assertSame('Salma Alaoui', $user->name);
        $this->assertTrue($user->active);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertNotSame($data['password'], $user->password);
        $this->get(route('login')); // Guest middleware prevents showing the form while signed in.
        $this->post(route('logout'));
        $this->get(route('login'))->assertSee($user->full_name)->assertDontSee($user->email)->assertDontSee($user->password);
    }

    public function test_creation_validates_unique_email_password_strength_confirmation_and_role(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);
        $this->post(route('admin.supervisors.store'), $this->accountData([
            'email' => $admin->email, 'password' => 'weak', 'password_confirmation' => 'different', 'role' => 'owner',
        ]))->assertSessionHasErrors(['email', 'password', 'role'])->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_editing_account_details_preserves_password_and_ignores_security_fields(): void
    {
        $this->actingAs($this->administrator());
        $user = User::factory()->create();
        $hash = $user->password;
        $data = $this->accountData(['email' => $user->email, 'password' => Str::password(24), 'auth_version' => 500, 'must_change_password' => true]);
        $this->put(route('admin.supervisors.update', $user), $data)->assertRedirect(route('admin.supervisors.index'));
        $user->refresh();
        $this->assertSame($hash, $user->password);
        $this->assertSame('Salma Alaoui', $user->full_name);
        $this->assertSame($user->full_name, $user->name);
        $this->assertSame(0, $user->auth_version);
        $this->assertFalse($user->must_change_password);
    }

    public function test_administrator_can_create_an_administrator_but_not_an_unknown_role(): void
    {
        $this->actingAs($this->administrator());
        $this->post(route('admin.supervisors.store'), $this->accountData(['role' => User::ROLE_ADMIN]))
            ->assertRedirect(route('admin.supervisors.index'));
        $this->assertTrue(User::where('email', 'salma@example.test')->firstOrFail()->isAdministrator());
    }

    public function test_deactivation_confirmation_does_not_mutate_and_explicit_confirmation_is_required(): void
    {
        $this->actingAs($this->administrator());
        $user = User::factory()->create();
        $this->get(route('admin.supervisors.deactivate', $user))->assertOk()->assertSee('Confirmer la désactivation');
        $this->assertTrue($user->fresh()->active);
        $this->patch(route('admin.supervisors.status', $user), ['active' => 0])->assertSessionHasErrors('confirm_deactivation');
        $this->assertTrue($user->fresh()->active);
        $this->put(route('admin.supervisors.update', $user), $this->accountData(['email' => $user->email, 'active' => 0]))
            ->assertSessionHasErrors('confirm_deactivation');
        $this->assertTrue($user->fresh()->active);
    }

    public function test_deactivation_and_reactivation_preserve_the_account_and_revoke_old_access(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();
        $this->actingAs($admin)->patch(route('admin.supervisors.status', $user), ['active' => 0, 'confirm_deactivation' => 1])
            ->assertRedirect(route('admin.supervisors.index'));
        $this->assertFalse($user->fresh()->active);
        $this->assertDatabaseCount('users', 2);
        $this->get(route('login')); // Admin still has access to their own session.
        $this->actingAs($admin)->patch(route('admin.supervisors.status', $user), ['active' => 1])->assertRedirect(route('admin.supervisors.index'));
        $this->assertTrue($user->fresh()->active);
        $this->actingAs($user->fresh())->withSession(['auth_version' => 0])->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_administrator_cannot_disable_their_own_account_in_any_form(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin)->patch(route('admin.supervisors.status', $admin), ['active' => 0, 'confirm_deactivation' => 1])
            ->assertSessionHasErrors('active');
        $this->put(route('admin.supervisors.update', $admin), $this->accountData([
            'email' => $admin->email, 'role' => User::ROLE_ADMIN, 'active' => 0, 'confirm_deactivation' => 1,
        ]))->assertSessionHasErrors('active');
        $this->assertTrue($admin->fresh()->active);
    }

    public function test_last_active_administrator_cannot_be_demoted_even_if_an_inactive_admin_exists(): void
    {
        $admin = $this->administrator();
        User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => false]);
        $this->actingAs($admin)->put(route('admin.supervisors.update', $admin), $this->accountData(['email' => $admin->email]))
            ->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->isAdministrator());
    }

    public function test_an_administrator_can_demote_themselves_when_another_active_admin_remains(): void
    {
        $admin = $this->administrator();
        $this->administrator();
        $this->actingAs($admin)->put(route('admin.supervisors.update', $admin), $this->accountData(['email' => $admin->email]))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($admin->fresh())->get(route('admin.supervisors.index'))->assertForbidden();
    }

    public function test_reset_hashes_password_requires_change_and_deletes_target_database_sessions_only(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();
        foreach ([['target-session', $user->id], ['another-session', $user->id], ['admin-session', $admin->id]] as [$id, $userId]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
        }
        config(['session.driver' => 'database']);
        $password = Str::password(24);
        $this->actingAs($admin)->put(route('admin.supervisors.password.reset', $user), [
            'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect(route('admin.supervisors.index'));
        $user->refresh();
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertSame(1, $user->auth_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'another-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
    }

    public function test_password_reset_rejects_weak_passwords_and_never_reveals_existing_password(): void
    {
        $this->actingAs($this->administrator());
        $user = User::factory()->create();
        $hash = $user->password;
        $this->get(route('admin.supervisors.password.edit', $user))->assertOk()->assertDontSee($hash);
        $this->put(route('admin.supervisors.password.reset', $user), ['password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password');
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_resetting_own_password_logs_out_the_administrator(): void
    {
        $admin = $this->administrator();
        $data = $this->accountData();
        $this->actingAs($admin)->put(route('admin.supervisors.password.reset', $admin), $data)->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => $data['password']])
            ->assertRedirect(route('password.change'));
    }

    public function test_search_status_role_and_pagination_are_applied_in_the_database(): void
    {
        $admin = $this->administrator();
        $match = User::factory()->create(['prenom' => 'Salma', 'nom' => 'Alaoui', 'email' => 'salma@example.test']);
        $other = User::factory()->create(['active' => false]);
        $this->actingAs($admin)->get(route('admin.supervisors.index', ['q' => 'salma@', 'active' => '1', 'role' => 'supervisor']))
            ->assertOk()->assertSee($match->email)->assertDontSee($other->email);
        $this->get(route('admin.supervisors.index', ['q' => '%']))->assertOk()->assertSee('Aucun compte trouvé');
        User::factory()->count(18)->create();
        $response = $this->get(route('admin.supervisors.index', ['role' => 'supervisor', 'page' => 2]));
        $response->assertOk();
        $this->assertSame(20, $response->viewData('supervisors')->total());
        $this->assertSame(5, $response->viewData('supervisors')->count());
        $this->assertStringContainsString('role=supervisor', $response->viewData('supervisors')->url(1));
    }

    public function test_administration_views_escape_names_and_never_include_password_hashes(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create(['prenom' => '<script>alert(1)</script>']);
        $this->actingAs($admin)->get(route('admin.supervisors.index'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee($user->password)->assertDontSee($admin->password);
        foreach (['create', 'edit', 'deactivate', 'password.edit'] as $action) {
            $this->get(route('admin.supervisors.'.$action, $action === 'create' ? [] : $user))->assertOk()->assertDontSee($user->password);
        }
    }

    public function test_administration_mutations_reject_missing_csrf_tokens(): void
    {
        $admin = $this->administrator();
        $user = User::factory()->create();
        $this->app->detectEnvironment(fn () => 'local');
        $this->actingAs($admin)->post(route('admin.supervisors.store'), $this->accountData())->assertStatus(419);
        $this->put(route('admin.supervisors.update', $user), $this->accountData())->assertStatus(419);
        $this->patch(route('admin.supervisors.status', $user), ['active' => 0, 'confirm_deactivation' => 1])->assertStatus(419);
        $this->put(route('admin.supervisors.password.reset', $user), $this->accountData())->assertStatus(419);
        $this->assertDatabaseCount('users', 2);
        $this->assertTrue($user->fresh()->active);
    }

    public function test_admin_views_can_be_rendered_for_browser_review(): void
    {
        $admin = User::factory()->create(['prenom' => 'Nadia', 'nom' => 'Idrissi', 'role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['prenom' => 'Amine', 'nom' => 'El Mansouri']);
        User::factory()->create(['prenom' => 'Sara', 'nom' => 'Bennani']);
        User::factory()->create(['prenom' => 'Youssef', 'nom' => 'Alami', 'active' => false]);
        $this->actingAs($admin);
        $views = [
            'admin-list' => ['index', []], 'admin-create' => ['create', []],
            'admin-edit' => ['edit', $supervisor], 'admin-password' => ['password.edit', $supervisor],
            'admin-deactivate' => ['deactivate', $supervisor],
        ];

        foreach ($views as $name => [$action, $parameters]) {
            $response = $this->get(route('admin.supervisors.'.$action, $parameters))->assertOk();
            $this->assertStringNotContainsString('@else', $response->getContent());
            if (getenv('EXPORT_REVIEW_VIEWS') === '1') {
                if (! is_dir(base_path('.artifacts'))) mkdir(base_path('.artifacts'), 0777, true);
                file_put_contents(base_path('.artifacts/'.$name.'.html'), $response->getContent());
            }
        }
    }
}
