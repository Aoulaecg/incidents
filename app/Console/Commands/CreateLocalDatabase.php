<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateLocalDatabase extends Command
{
    protected $signature = 'app:create-local-database';

    protected $description = 'Crée la base MySQL locale configurée dans .env, si elle n’existe pas';

    public function handle(): int
    {
        if (! app()->environment('local') || config('database.default') !== 'mysql') {
            $this->error('Cette commande est réservée à MySQL en environnement local.');

            return self::FAILURE;
        }

        $connection = config('database.connections.mysql');
        $database = $connection['database'];

        if (! is_string($database) || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $database)) {
            $this->error('DB_DATABASE doit contenir uniquement des lettres, chiffres et underscores.');

            return self::FAILURE;
        }

        // Connect to the server without selecting a database first.
        $connection['database'] = null;
        $connection['url'] = null;
        config(['database.connections.mysql_setup' => $connection]);

        try {
            DB::connection('mysql_setup')->statement(
                "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } catch (\Throwable) {
            $this->error('Connexion MySQL impossible. Démarrez MySQL dans XAMPP et vérifiez les paramètres DB_* de .env ainsi que le droit CREATE DATABASE.');

            return self::FAILURE;
        } finally {
            DB::purge('mysql_setup');
        }

        $this->info("La base locale {$database} est prête.");

        return self::SUCCESS;
    }
}
