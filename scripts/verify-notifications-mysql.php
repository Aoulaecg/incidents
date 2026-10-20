<?php

// Real MySQL verification with rolled-back writes and an in-memory mail transport.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Jobs\SendDeclarationNotification;
use App\Mail\DeclarationRecorded;
use App\Models\DeclarationNotification;
use App\Models\Incident;
use App\Models\Parking;
use App\Models\User;
use App\Services\DeclarationNotificationService;
use App\Services\DeclarationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

if (! in_array('--run', $argv, true)) {
    echo "Ajouter --run pour vérifier MySQL avec annulation des écritures et emails simulés.\n";
    exit(0);
}
if (DB::connection()->getDriverName() !== 'mysql') {
    throw new RuntimeException('MySQL requis.');
}
$tables = ['users', 'parkings', 'parking_user', 'incidents', 'incident_audits', 'declaration_notifications', 'jobs'];
$engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
    ->whereIn('TABLE_NAME', $tables)->get(['TABLE_NAME', 'ENGINE']);
if ($engines->count() !== count($tables) || $engines->contains(fn ($table) => strtoupper($table->ENGINE) !== 'INNODB')) {
    throw new RuntimeException('Toutes les tables doivent exister et utiliser InnoDB avant vérification.');
}
$supervisor = User::where('role', User::ROLE_SUPERVISOR)->where('active', true)->where('must_change_password', false)->firstOrFail();
app(DeclarationNotificationService::class)->director();
$snapshot = fn () => hash('sha256', serialize(array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->all(), $tables)));
$before = $snapshot();
$check = function (bool $condition, string $label): void {
    if (! $condition) {
        throw new RuntimeException($label);
    }
    echo 'PASS '.$label.PHP_EOL;
};
Mail::fake();
DB::beginTransaction();
try {
    $parking = Parking::create(['nom' => 'Vérification notifications '.Str::uuid()]);
    $supervisor->parkings()->attach($parking);
    $service = app(DeclarationService::class);
    $notifications = app(DeclarationNotificationService::class);
    $data = ['parking_id' => $parking->id, 'submission_token' => (string) Str::uuid()];
    $check($service->store($supervisor, $data, Incident::RAS), 'confirmation RAS enregistrée dans MySQL');
    $check(! $service->store($supervisor, $data, Incident::RAS), 'RAS rejoué sans doublon');
    $ras = DeclarationNotification::where('type', Incident::RAS)->latest('id')->firstOrFail();
    $data = array_replace($data, [
        'submission_token' => (string) Str::uuid(), 'date_incident' => now('Africa/Casablanca')->toDateString(),
        'heure_incident' => '09:30', 'description' => 'Vérification transactionnelle, email simulé.',
    ]);
    $check($service->store($supervisor, $data, Incident::INCIDENT), 'incident enregistré dans MySQL');
    $incident = DeclarationNotification::where('type', Incident::INCIDENT)->latest('id')->firstOrFail();
    $check(DeclarationNotification::whereIn('id', [$ras->id, $incident->id])->count() === 2, 'une notification par déclaration');
    $check($ras->incident->refresh()->status === 'superseded', 'historique RAS conservé après incident');
    foreach ([$ras, $incident] as $notification) {
        $job = new SendDeclarationNotification($notification->id);
        $job->handle($notifications);
        $job->handle($notifications);
        $check($notification->refresh()->status === 'sent' && $notification->attempts === 1, 'traitement simulé idempotent avec verrou MySQL');
    }
    Mail::assertSent(DeclarationRecorded::class, 2);
    echo "PASS deux emails capturés en mémoire, aucun envoi SMTP\n";
} finally {
    DB::rollBack();
}
$check($snapshot() === $before, 'toutes les données conservées après annulation');
