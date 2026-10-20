<?php

namespace Tests\Feature;

use App\Models\{Incident, Parking, User};
use App\Services\{ParkingService, UserAccountService};
use App\Support\ParkingName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParkingManagementTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->withoutVite(); }
    private function admin(): User { return User::factory()->create(['role' => User::ROLE_ADMIN]); }
    private function account(User $user, array $extra = []): array
    {
        return array_replace(['prenom' => $user->prenom, 'nom' => $user->nom, 'email' => $user->email, 'role' => User::ROLE_SUPERVISOR, 'active' => 1], $extra);
    }
    private function incident(Parking $parking): array
    {
        return ['parking_id' => $parking->id, 'date_incident' => now()->toDateString(), 'heure_incident' => '10:30', 'description' => 'Historique à conserver', 'submission_token' => (string) Str::uuid()];
    }

    public function test_only_active_administrators_can_access_parking_and_assignment_actions(): void
    {
        $parking = Parking::create(['nom' => 'Parking sécurisé']);
        $target = User::factory()->create();
        $this->get(route('admin.parkings.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.parkings.store'), ['nom' => 'Intrusion', 'active' => 1])->assertRedirect(route('admin.login'));
        foreach ([User::ROLE_SUPERVISOR, User::ROLE_DIRECTOR] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['index', 'create', 'edit'] as $action) $this->get(route('admin.parkings.'.$action, $action === 'edit' ? $parking : []))->assertForbidden();
            $this->post(route('admin.parkings.store'), ['nom' => 'Intrusion', 'active' => 1])->assertForbidden();
            $this->put(route('admin.parkings.update', $parking), ['nom' => 'Intrusion', 'active' => 0])->assertForbidden();
            $this->put(route('admin.supervisors.parkings.update', $target), ['parking_ids' => [$parking->id]])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => false]))->get(route('admin.parkings.index'))->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('parkings', 1);
        $this->assertDatabaseCount('parking_user', 0);
    }

    public function test_creating_a_parking_normalizes_spaces_and_immediately_populates_assignment_forms(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.parkings.store'), ['nom' => "  Parking\u{00A0}  Rabat\tCentre  ", 'active' => 1, 'nom_key' => 'forged'])->assertRedirect(route('admin.parkings.index'))->assertSessionHas('status', 'Parking ajouté avec succès');
        $parking = Parking::firstOrFail();
        $this->assertSame('Parking Rabat Centre', $parking->nom);
        $this->assertSame(ParkingName::key($parking->nom), $parking->nom_key);
        $this->get(route('admin.parkings.index'))->assertSee($parking->nom);
        $response = $this->get(route('admin.supervisors.create'))->assertOk()->assertSee($parking->nom);
        $this->assertSame([$parking->id], $response->viewData('parkings')->modelKeys());
    }

    public function test_parking_validation_rejects_empty_invalid_and_normalized_duplicate_names(): void
    {
        $parking = Parking::create(['nom' => 'Aéroport Marrakech']);
        $other = Parking::create(['nom' => 'Casa Marina A8']);
        $this->actingAs($this->admin());
        $this->post(route('admin.parkings.store'), ['nom' => "  \u{00A0}  ", 'active' => 'invalid'])->assertSessionHasErrors(['nom', 'active']);
        $this->post(route('admin.parkings.store'), ['nom' => str_repeat('a', 256), 'active' => 1])->assertSessionHasErrors('nom');
        $this->post(route('admin.parkings.store'), ['nom' => ['malformed'], 'active' => 1])->assertSessionHasErrors('nom');
        $this->post(route('admin.parkings.store'), ['nom' => '  AÉROPORT   marrakech ', 'active' => 1])->assertSessionHasErrors('nom');
        $this->put(route('admin.parkings.update', $other), ['nom' => 'AÉROPORT Marrakech', 'active' => 1])->assertSessionHasErrors('nom');
        $this->put(route('admin.parkings.update', $parking), ['nom' => 'Aéroport Marrakech', 'active' => 1])->assertRedirect(route('admin.parkings.index'));
        $this->assertDatabaseCount('parkings', 2);
    }

    public function test_database_unique_constraint_rejects_concurrent_normalized_duplicates(): void
    {
        Parking::create(['nom' => 'Parking Rabat']);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Parking::create(['nom' => 'PARKING   RABAT']);
    }

    public function test_parking_list_combines_search_status_pagination_and_real_supervisor_names(): void
    {
        $user = User::factory()->create(['prenom' => 'Rabie', 'nom' => 'Benmahmoud']);
        $parking = Parking::create(['nom' => 'Taghazout']); $user->parkings()->attach($parking);
        Parking::create(['nom' => 'Taghazout Annexe', 'active' => false]);
        $this->actingAs($this->admin());
        $response = $this->get(route('admin.parkings.index', ['q' => 'Taghazout', 'active' => '1']))->assertOk()->assertSee($user->full_name)->assertDontSee('Taghazout Annexe');
        $this->assertSame(1, $response->viewData('parkings')->total());
        $this->assertSame(['total' => 2, 'active' => 1, 'inactive' => 1], $response->viewData('stats'));
        $this->get(route('admin.parkings.index', ['q' => '%']))->assertSee('Aucun parking trouvé');
        for ($i = 0; $i < 18; $i++) Parking::create(['nom' => 'Parking additionnel '.$i]);
        $response = $this->get(route('admin.parkings.index', ['active' => '1', 'page' => 2]))->assertOk();
        $this->assertSame(19, $response->viewData('parkings')->total());
        $this->assertSame(4, $response->viewData('parkings')->count());
        $this->assertStringContainsString('active=1', $response->viewData('parkings')->url(1));
    }

    public function test_new_supervisor_requires_one_active_parking_and_saves_multiple_assignments(): void
    {
        $one = Parking::create(['nom' => 'Premier']); $two = Parking::create(['nom' => 'Deuxième']);
        $inactive = Parking::create(['nom' => 'Inactif', 'active' => false]);
        $password = Str::password(24);
        $data = ['prenom' => 'Nouveau', 'nom' => 'Superviseur', 'email' => 'nouveau@example.test', 'role' => User::ROLE_SUPERVISOR, 'active' => 1, 'password' => $password, 'password_confirmation' => $password];
        $this->actingAs($this->admin());
        foreach ([[], ['parking_ids' => []]] as $extra) $this->post(route('admin.supervisors.store'), array_merge($data, $extra))->assertSessionHasErrors('parking_ids');
        $this->post(route('admin.supervisors.store'), array_merge($data, ['parking_ids' => [$inactive->id]]))->assertSessionHasErrors('parking_ids.0');
        $this->assertDatabaseCount('users', 1);
        $this->post(route('admin.supervisors.store'), array_merge($data, ['parking_ids' => [$one->id, $two->id]]))->assertRedirect(route('admin.supervisors.index'));
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $user->parkings()->pluck('parkings.id')->all());
        $this->assertDatabaseCount('parking_user', 2);
    }

    public function test_non_supervisor_creation_ignores_assignments_and_role_conversion_requires_a_parking(): void
    {
        $admin = $this->admin(); $this->actingAs($admin);
        foreach ([User::ROLE_ADMIN, User::ROLE_DIRECTOR] as $role) {
            $password = Str::password(24);
            $data = ['prenom' => 'Autre', 'nom' => $role, 'email' => $role.'@example.test', 'role' => $role, 'active' => 1, 'password' => $password, 'password_confirmation' => $password, 'parking_ids' => [999999]];
            $this->post(route('admin.supervisors.store'), $data)->assertRedirect(route('admin.supervisors.index'));
            $user = User::where('email', $data['email'])->firstOrFail();
            $this->assertSame(0, $user->parkings()->count());
            $this->put(route('admin.supervisors.update', $user), $this->account($user))->assertSessionHasErrors('parking_ids');
        }
        $this->assertDatabaseCount('parking_user', 0);
    }

    public function test_edit_form_prechecks_assignments_and_sync_preserves_inactive_links_password_and_history(): void
    {
        $one = Parking::create(['nom' => 'Premier']); $two = Parking::create(['nom' => 'Deuxième']);
        $inactive = Parking::create(['nom' => 'Historique', 'active' => false]);
        $user = User::factory()->create(); $user->parkings()->attach([$one->id, $inactive->id]); $hash = $user->password;
        $this->actingAs($user)->post(route('supervisor.incidents.store'), $this->incident($one))->assertRedirect(route('dashboard'));
        $this->actingAs($this->admin());
        $response = $this->get(route('admin.supervisors.edit', $user))->assertOk()->assertSee('Affectations inactives conservées')->assertSee($inactive->nom);
        $this->assertEqualsCanonicalizing([$one->id, $inactive->id], $response->viewData('assigned'));
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $response->viewData('parkings')->modelKeys());
        $this->put(route('admin.supervisors.update', $user), $this->account($user, ['parking_ids' => [$two->id]]))->assertRedirect(route('admin.supervisors.index'));
        $this->assertEqualsCanonicalizing([$two->id, $inactive->id], $user->parkings()->pluck('parkings.id')->all());
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('incidents', 1);
        $director = User::factory()->create(['role' => User::ROLE_DIRECTOR]);
        $this->actingAs($director)->get(route('director.dashboard', ['parking_id' => $one->id]))->assertOk()->assertSee('Historique à conserver');
    }

    public function test_invalid_assignment_cancels_all_account_changes_and_cannot_attach_an_inactive_parking(): void
    {
        $inactive = Parking::create(['nom' => 'Inactif', 'active' => false]); $user = User::factory()->create(); $original = $user->nom;
        $this->actingAs($this->admin());
        $this->put(route('admin.supervisors.update', $user), $this->account($user, ['nom' => 'Non enregistré', 'parking_ids' => [$inactive->id]]))->assertSessionHasErrors('parking_ids.0');
        $this->put(route('admin.supervisors.parkings.update', $user), ['parking_ids' => [$inactive->id]])->assertSessionHasErrors('parking_ids.0');
        $this->assertSame($original, $user->fresh()->nom);
        $this->assertDatabaseCount('parking_user', 0);
        try { app(UserAccountService::class)->assignParkings(auth()->user(), $user, [$inactive->id]); $this->fail('Inactive parking must be rejected by the service too.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('parking_ids', $exception->errors()); }
    }

    public function test_supervisor_reload_follows_assignments_and_removed_parking_rejects_incident_and_ras(): void
    {
        $one = Parking::create(['nom' => 'Premier']); $two = Parking::create(['nom' => 'Deuxième']);
        $user = User::factory()->create(); $user->parkings()->attach($one); $admin = $this->admin();
        $this->actingAs($user)->get(route('dashboard'))->assertSee($one->nom)->assertDontSee($two->nom);
        $this->actingAs($admin)->put(route('admin.supervisors.update', $user), $this->account($user, ['parking_ids' => [$two->id]]))->assertRedirect(route('admin.supervisors.index'));
        $this->actingAs($user)->get(route('dashboard'))->assertSee($two->nom)->assertDontSee($one->nom);
        $this->post(route('supervisor.incidents.store'), $this->incident($one))->assertSessionHasErrors('parking_id');
        $this->post(route('supervisor.ras.store'), ['parking_id' => $one->id, 'confirm_ras' => 1, 'submission_token' => (string) Str::uuid()])->assertSessionHasErrors('parking_id');
        $this->assertDatabaseCount('incidents', 0);
        $this->actingAs($admin)->put(route('admin.supervisors.update', $user), $this->account($user, ['parking_ids' => '']))->assertRedirect(route('admin.supervisors.index'));
        $this->actingAs($user)->get(route('dashboard'))->assertSee('Aucun parking ne vous est actuellement affecté. Veuillez contacter votre administrateur.')->assertDontSee('data-declaration-form', false);
    }

    public function test_parking_deactivation_preserves_history_and_associations_but_blocks_both_declarations(): void
    {
        $parking = Parking::create(['nom' => 'Parking suivi']); $user = User::factory()->create(); $user->parkings()->attach($parking);
        $this->actingAs($user)->post(route('supervisor.incidents.store'), $this->incident($parking))->assertRedirect(route('dashboard'));
        $incident = Incident::first()->getAttributes();
        $admin = $this->admin(); $this->actingAs($admin)->put(route('admin.parkings.update', $parking), ['nom' => 'Parking renommé', 'active' => 0])->assertRedirect(route('admin.parkings.index'));
        $this->assertDatabaseHas('parking_user', ['user_id' => $user->id, 'parking_id' => $parking->id]);
        $this->assertSame($incident, Incident::first()->getAttributes());
        $this->actingAs($user)->get(route('dashboard'))->assertSee('Aucun parking ne vous est actuellement affecté.')->assertSee('Historique à conserver');
        $this->post(route('supervisor.incidents.store'), $this->incident($parking))->assertSessionHasErrors('parking_id');
        $this->post(route('supervisor.ras.store'), ['parking_id' => $parking->id, 'confirm_ras' => 1, 'submission_token' => (string) Str::uuid()])->assertSessionHasErrors('parking_id');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DIRECTOR]))->get(route('director.dashboard', ['parking_id' => $parking->id]))->assertOk()->assertSee('Parking renommé')->assertSee('Historique à conserver');
        $this->actingAs($admin)->put(route('admin.parkings.update', $parking), ['nom' => 'Parking renommé', 'active' => 1])->assertRedirect(route('admin.parkings.index'));
        $this->actingAs($user)->get(route('dashboard'))->assertSee('data-declaration-form', false);
    }

    public function test_catalog_initialization_is_idempotent_and_preserves_existing_names_status_assignments(): void
    {
        $parking = Parking::create(['nom' => 'TAGHAZOUT', 'active' => false]); $user = User::factory()->create(); $user->parkings()->attach($parking);
        $attributes = $parking->fresh()->getAttributes();
        $this->artisan('app:init-parkings')->assertSuccessful(); $this->assertDatabaseCount('parkings', 1);
        $this->artisan('app:init-parkings --apply')->assertSuccessful();
        $this->artisan('app:init-parkings --apply')->assertSuccessful();
        $this->assertDatabaseCount('parkings', 14); $this->assertDatabaseCount('parking_user', 1); $this->assertDatabaseCount('users', 1);
        $this->assertSame($attributes, $parking->fresh()->getAttributes());
    }

    public function test_ambiguous_catalog_aborts_initialization_and_migration_before_mutating_any_data(): void
    {
        DB::table('parkings')->insert([['nom' => 'Taghazout', 'nom_key' => null], ['nom' => 'TAGHAZOUT  ', 'nom_key' => null]]);
        $this->artisan('app:init-parkings --apply')->assertFailed(); $this->assertDatabaseCount('parkings', 2);
        $migration = require database_path('migrations/2026_10_13_000000_add_normalized_name_to_parkings.php');
        try { $migration->up(); $this->fail('Ambiguous migration must fail before schema changes.'); }
        catch (\RuntimeException $exception) { $this->assertStringContainsString('ambigus', $exception->getMessage()); }
        $this->assertDatabaseCount('parkings', 2);
    }

    public function test_parking_writes_recheck_fresh_admin_authority_and_do_not_offer_physical_deletion(): void
    {
        $admin = $this->admin(); $parking = Parking::create(['nom' => 'À conserver']);
        $stale = clone $admin; $admin->forceFill(['role' => User::ROLE_SUPERVISOR])->save();
        $this->actingAs($stale)->post(route('admin.parkings.store'), ['nom' => 'Non autorisé', 'active' => 1])->assertForbidden();
        $this->put(route('admin.parkings.update', $parking), ['nom' => 'Non autorisé', 'active' => 1])->assertForbidden();
        $this->actingAs($this->admin())->delete(route('admin.parkings.update', $parking))->assertStatus(405);
        $this->assertDatabaseCount('parkings', 1); $this->assertSame('À conserver', $parking->fresh()->nom);
    }

    public function test_csrf_and_html_escaping_protect_parking_management(): void
    {
        $parking = Parking::create(['nom' => '<script>alert(1)</script>']); $this->actingAs($this->admin());
        $this->get(route('admin.parkings.index'))->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('admin.supervisors.create'))->assertDontSee('<script>alert(1)</script>', false);
        $this->app->detectEnvironment(fn () => 'local');
        $this->post(route('admin.parkings.store'), ['nom' => 'Nouveau', 'active' => 1])->assertStatus(419);
        $this->put(route('admin.parkings.update', $parking), ['nom' => 'Modifié', 'active' => 0])->assertStatus(419);
        $this->assertDatabaseCount('parkings', 1);
    }

    public function test_parking_views_are_rendered_for_browser_review_with_active_and_inactive_assignments(): void
    {
        $user = User::factory()->create(['prenom' => 'Rabie', 'nom' => 'Benmahmoud']);
        $parkings = collect(config('parking_assignments'))->flatten()->map(fn ($name) => Parking::create(['nom' => $name]));
        $user->parkings()->attach($parkings->take(2)->pluck('id')->all());
        $inactive = Parking::create(['nom' => 'Parking historique désactivé', 'active' => false]); $user->parkings()->attach($inactive);
        $many = User::factory()->create(['prenom' => 'Abdelali', 'nom' => 'Inbach']); $many->parkings()->attach($parkings->slice(4)->pluck('id')->all());
        $this->actingAs($this->admin());
        $views = ['parking-list' => route('admin.parkings.index'), 'parking-create' => route('admin.parkings.create'), 'parking-edit' => route('admin.parkings.edit', $parkings->first()), 'parking-supervisor-create' => route('admin.supervisors.create'), 'parking-supervisor-edit' => route('admin.supervisors.edit', $user), 'parking-assignment-edit' => route('admin.supervisors.parkings.edit', $user), 'parking-supervisor-list' => route('admin.supervisors.index')];
        foreach ($views as $name => $url) {
            $response = $this->get($url)->assertOk();
            if (getenv('EXPORT_REVIEW_VIEWS') === '1') { if (! is_dir(base_path('.artifacts'))) mkdir(base_path('.artifacts'), 0777, true); file_put_contents(base_path('.artifacts/'.$name.'.html'), $response->getContent()); }
        }
        $this->get(route('admin.supervisors.index'))->assertSee('10 parkings');
    }
}
