<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SupervisorSeeder extends Seeder
{
    public function run(): void
    {
        // Compatibility entry point: never create fictitious accounts or reset passwords.
        $this->command?->info('Créez les comptes depuis l’administration ; premier administrateur : php artisan app:create-admin.');
    }
}
