<?php
namespace App\Console\Commands;

use App\Models\{Parking, User};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignParkingMap extends Command
{
    protected $signature = 'app:assign-parking-map {--apply : Créer les parkings et ajouter les affectations vérifiées}';
    protected $description = 'Vérifie les identités des trois superviseurs puis prépare leurs affectations CGPark';
    private function normalize(string $name): string { return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($name)))); }
    public function handle(): int
    {
        $matched = [];
        foreach (config('parking_assignments') as $name => $parkings) {
            $users = User::where('role', User::ROLE_SUPERVISOR)->where('active', true)->get()->filter(fn ($u) => $this->normalize($u->full_name) === $this->normalize($name));
            if ($users->count() !== 1) {
                $this->error("{$name} : identité absente ou ambiguë. Aucune modification effectuée.");
                return self::FAILURE;
            }
            $user = $users->first();
            $matched[] = [$user->id, $name, $parkings];
            $this->info("Identité vérifiée : #{$user->id} {$name} — ".count($parkings).' parkings.');
        }
        if (! $this->option('apply')) {
            $this->info('Vérification seule. Ajouter --apply pour enregistrer ces affectations.');
            return self::SUCCESS;
        }
        DB::transaction(function () use ($matched) {
            foreach ($matched as [$id, $name, $names]) {
                $user = User::whereKey($id)->lockForUpdate()->firstOrFail();
                if (! $user->active || $user->role !== User::ROLE_SUPERVISOR || $this->normalize($user->full_name) !== $this->normalize($name)) {
                    throw new \RuntimeException('Identité modifiée pendant la vérification ; affectations annulées.');
                }
                $ids = [];
                foreach ($names as $parkingName) $ids[] = Parking::firstOrCreate(['nom' => $parkingName], ['active' => true])->id;
                $user->parkings()->syncWithoutDetaching($ids);
            }
        }, 3);
        $this->info('Les parkings et les affectations vérifiées sont enregistrés. Aucun compte existant n’a été modifié.');
        return self::SUCCESS;
    }
}
