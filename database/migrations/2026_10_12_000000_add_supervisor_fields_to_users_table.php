<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nom')->default('');
            $table->string('prenom')->default('');
            $table->string('role', 32)->default('superviseur');
            $table->boolean('active')->default(true);
            $table->index(['active', 'role']);
        });

        // Preserve accounts created with the original Laravel schema.
        DB::table('users')->select('id', 'name')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $parts = preg_split('/\s+/u', trim($user->name), 2);

                DB::table('users')->where('id', $user->id)->update([
                    'prenom' => count($parts) > 1 ? $parts[0] : '',
                    'nom' => $parts[1] ?? $parts[0],
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['active', 'role']);
            $table->dropColumn(['nom', 'prenom', 'role', 'active']);
        });
    }
};
