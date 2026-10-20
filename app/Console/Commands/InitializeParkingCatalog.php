<?php

namespace App\Console\Commands;

use App\Models\Parking;
use App\Support\ParkingName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitializeParkingCatalog extends Command
{
    protected $signature = 'app:init-parkings {--apply : Ajouter uniquement les parkings manquants}';
    protected $description = 'Vérifie le catalogue initial CGPark sans modifier les parkings existants ni leurs affectations';

    public function handle(): int
    {
        $names = collect(config('parking_assignments'))->flatten()->unique()->values();
        $existing = Parking::orderBy('id')->get()->groupBy(fn ($parking) => ParkingName::key($parking->nom));
        $conflicts = $existing->filter(fn ($group) => $group->count() > 1);
        if ($conflicts->isNotEmpty()) {
            $this->error('Noms ambigus : '.$conflicts->map(fn ($group) => $group->map(fn ($parking) => '#'.$parking->id.' '.$parking->nom)->implode(', '))->implode(' ; ').'. Aucune modification effectuée.');
            return self::FAILURE;
        }
        $missing = $names->filter(fn ($name) => ! $existing->has(ParkingName::key($name)));
        $this->info(($names->count() - $missing->count()).'/'.$names->count().' parkings initiaux déjà présents.');
        foreach ($missing as $name) $this->line('Manquant : '.$name);
        if (! $this->option('apply')) {
            $this->info('Vérification seule. Utilisez --apply pour ajouter les parkings manquants.');
            return self::SUCCESS;
        }
        DB::transaction(function () use ($missing) {
            foreach ($missing as $name) Parking::firstOrCreate(['nom_key' => ParkingName::key($name)], ['nom' => $name, 'active' => true]);
        }, 3);
        $this->info('Catalogue vérifié. Aucun parking existant, compte ou affectation modifié.');
        return self::SUCCESS;
    }
}
