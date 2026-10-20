<?php

use App\Support\ParkingName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $parkings = DB::table('parkings')->orderBy('id')->get(['id', 'nom']);
        $duplicates = $parkings->groupBy(fn ($parking) => ParkingName::key($parking->nom))->filter(fn ($group) => $group->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Noms de parkings ambigus (casse/espaces), migration annulée avant modification : '.$duplicates->map(fn ($group) => $group->pluck('id')->implode(', '))->implode(' ; '));
        }
        Schema::table('parkings', function (Blueprint $table) {
            $table->string('nom_key', 64)->nullable()->unique();
        });
        // Backfill only the new column: preserve existing names, status and timestamps.
        foreach ($parkings as $parking) {
            DB::table('parkings')->where('id', $parking->id)->update(['nom_key' => ParkingName::key($parking->nom)]);
        }
    }

    public function down(): void
    {
        Schema::table('parkings', function (Blueprint $table) {
            $table->dropUnique(['nom_key']);
            $table->dropColumn('nom_key');
        });
    }
};
