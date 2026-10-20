<?php

// Real MySQL integration checks. All writes stay inside an outer transaction,
// which is always rolled back. This script never runs migrations or seeders.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Incident, Parking, User};
use App\Services\{DeclarationService, ParkingService, UserAccountService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

if (! in_array('--run', $argv, true)) { echo "Vérification MySQL avec écritures temporaires annulées : ajouter --run.\n"; exit(0); }
if (DB::connection()->getDriverName() !== 'mysql') throw new RuntimeException('Cette vérification nécessite MySQL.');
$tables = ['users', 'parkings', 'parking_user', 'incidents', 'incident_audits'];
$engines = DB::select("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users', 'parkings', 'parking_user', 'incidents', 'incident_audits')");
if (count($engines) !== count($tables) || collect($engines)->contains(fn ($table) => strtoupper($table->ENGINE) !== 'INNODB')) throw new RuntimeException('Toutes les tables doivent être transactionnelles (InnoDB). Aucune écriture effectuée.');
$admin = User::where('role', User::ROLE_ADMIN)->where('active', true)->firstOrFail();
$director = User::where('role', User::ROLE_DIRECTOR)->where('active', true)->firstOrFail();
$snapshot = function () use ($tables) {
    return hash('sha256', serialize(array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->all(), $tables)));
};
$before = $snapshot();
$check = function (bool $condition, string $label) { if (! $condition) throw new RuntimeException($label); echo 'PASS '.$label.PHP_EOL; };
$viewData = function (User $user, string $controller, array $query = []) {
    Auth::setUser($user);
    $request = Request::create('/', 'GET', $query);
    $request->setUserResolver(fn () => $user);
    return app($controller)->index($request)->getData();
};
$originalLevel = DB::transactionLevel();
DB::beginTransaction();
try {
    $service = app(ParkingService::class); $accounts = app(UserAccountService::class); $declarations = app(DeclarationService::class);
    $suffix = (string) Str::uuid();
    $one = $service->save($admin, ['nom' => 'Vérification parking '.$suffix, 'active' => true]);
    $two = $service->save($admin, ['nom' => 'Vérification annexe '.$suffix, 'active' => true]);
    $check(Parking::whereKey($one->id)->where('active', true)->exists(), 'création du parking enregistrée dans MySQL');
    $check(app(App\Http\Controllers\Admin\SupervisorController::class)->create()->getData()['parkings']->contains('id', $one->id), 'nouveau parking disponible pour les affectations');
    try { $service->save($admin, ['nom' => '  '.mb_strtoupper(str_replace(' ', '   ', $one->nom)).'  ', 'active' => true]); throw new RuntimeException('Doublon accepté.'); }
    catch (ValidationException $exception) { $check(isset($exception->errors()['nom']), 'doublons casse/espaces refusés par MySQL et le service'); }
    $password = Str::password(24);
    $user = $accounts->create($admin, ['prenom' => 'Vérification', 'nom' => 'Temporaire', 'email' => 'parking-review-'.$suffix.'@example.invalid', 'role' => User::ROLE_SUPERVISOR, 'active' => true, 'password' => $password, 'parking_ids' => [$one->id, $two->id]]);
    unset($password);
    $user->forceFill(['must_change_password' => false])->save();
    $check($user->parkings()->count() === 2, 'création d’un superviseur avec deux affectations en transaction');
    $data = $viewData($user, App\Http\Controllers\Supervisor\DeclarationController::class);
    $check($data['parkings']->count() === 2 && $data['parkings']->contains('id', $one->id), 'superviseur limité à ses seuls parkings actifs');
    $incidentData = ['parking_id' => $one->id, 'date_incident' => now('Africa/Casablanca')->toDateString(), 'heure_incident' => '10:30', 'description' => 'Vérification transactionnelle MySQL', 'submission_token' => (string) Str::uuid()];
    $check($declarations->store($user, $incidentData, Incident::INCIDENT), 'incident enregistré pour un parking autorisé');
    $accounts->update($admin, $user, ['parking_ids' => [$two->id]]);
    $check($user->parkings()->count() === 1 && ! $user->parkings()->whereKey($one->id)->exists(), 'sync retire l’affectation choisie');
    foreach ([Incident::INCIDENT, Incident::RAS] as $type) {
        try { $declarations->store($user, array_replace($incidentData, ['submission_token' => (string) Str::uuid()]), $type); throw new RuntimeException('Parking non affecté accepté.'); }
        catch (ValidationException $exception) { $check(isset($exception->errors()['parking_id']), 'déclaration '.$type.' refusée après retrait de l’affectation'); }
    }
    $data = $viewData($director, App\Http\Controllers\Director\DashboardController::class, ['parking_id' => $one->id, 'date' => $incidentData['date_incident']]);
    $check($data['declarations']->total() === 1, 'Directeur conserve l’incident après retrait de l’affectation');
    $accounts->assignParkings($admin, $user, [$one->id, $two->id]);
    $service->save($admin, ['nom' => 'Parking renommé '.$suffix, 'active' => false], $one);
    $check($user->parkings()->whereKey($one->id)->exists(), 'désactivation conserve les associations existantes');
    foreach ([Incident::INCIDENT, Incident::RAS] as $type) {
        try { $declarations->store($user, array_replace($incidentData, ['submission_token' => (string) Str::uuid()]), $type); throw new RuntimeException('Parking inactif accepté.'); }
        catch (ValidationException $exception) { $check(isset($exception->errors()['parking_id']), 'parking inactif refuse '.$type); }
    }
    $accounts->assignParkings($admin, $user, []);
    $check($user->parkings()->count() === 1 && $user->parkings()->whereKey($one->id)->exists(), 'sync conserve les affectations inactives');
    $check($viewData($user, App\Http\Controllers\Supervisor\DeclarationController::class)['parkings']->isEmpty(), 'aucun parking actif disponible après retrait/désactivation');
    $data = $viewData($director, App\Http\Controllers\Director\DashboardController::class, ['parking_id' => $one->id, 'date' => $incidentData['date_incident']]);
    $check($data['declarations']->total() === 1 && $data['declarations']->first()->parking->nom === 'Parking renommé '.$suffix, 'Directeur conserve l’historique du parking désactivé et renommé');
    $service->save($admin, ['nom' => 'Parking renommé '.$suffix, 'active' => true], $one);
    $check($viewData($user, App\Http\Controllers\Supervisor\DeclarationController::class)['parkings']->contains('id', $one->id), 'réactivation restaure le parking dans la liste du superviseur');
} finally {
    while (DB::transactionLevel() > $originalLevel) DB::rollBack();
    Auth::forgetUser();
    $check(hash_equals($before, $snapshot()), 'rollback vérifié : tous les comptes, mots de passe, parkings, affectations et incidents existants inchangés');
}
