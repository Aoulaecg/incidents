<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateFirstAdministrator extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Crée le premier administrateur réel par saisie interactive et masquée';

    public function handle(): int
    {
        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            $this->error('Un administrateur existe déjà. Gérez les nouveaux comptes depuis l’interface web.');

            return self::FAILURE;
        }

        $this->info('Création du premier administrateur CGPark. Aucun mot de passe ne sera affiché.');
        $data = [
            'prenom' => trim((string) $this->ask('Prénom')),
            'nom' => trim((string) $this->ask('Nom')),
            'email' => mb_strtolower(trim((string) $this->ask('Adresse email professionnelle'))),
            // Never fall back to visible input if a terminal cannot hide the password.
            'password' => $this->secret('Mot de passe (12 caractères, majuscule, minuscule, chiffre, symbole)', false),
            'password_confirmation' => $this->secret('Confirmer le mot de passe', false),
        ];

        $validator = Validator::make($data, [
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => PasswordRules::confirmed(),
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if (! $this->confirm('Créer ce compte administrateur réel ?', false)) {
            $this->info('Création annulée.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($data) {
                // The lock also protects simultaneous first-administrator attempts on MySQL.
                if (User::where('role', User::ROLE_ADMIN)->orderBy('id')->lockForUpdate()->get()->isNotEmpty()) {
                    throw ValidationException::withMessages(['role' => 'Un administrateur a déjà été créé.']);
                }
                $account = new User(collect($data)->only(['prenom', 'nom', 'email', 'password'])->all());
                $account->name = $account->full_name;
                $account->role = User::ROLE_ADMIN;
                $account->active = true;
                $account->must_change_password = false;
                $account->save();
            }, 3);
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first());

            return self::FAILURE;
        }

        $this->info('Le premier administrateur a été créé. Vous pouvez vous connecter à /administration/connexion.');

        return self::SUCCESS;
    }
}
