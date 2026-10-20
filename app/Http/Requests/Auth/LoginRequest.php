<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'password' => ['required', 'string', 'max:1024'],
        ];

        if ($this->expectedRole() === User::ROLE_SUPERVISOR) {
            $rules['user_id'] = ['required', 'integer', 'min:1'];
        } else {
            $rules['email'] = ['required', 'string', 'email', 'max:255'];
        }

        return $rules;
    }

    public function expectedRole(): string
    {
        return match ($this->route()?->getName()) {
            'admin.login.store' => User::ROLE_ADMIN,
            'director.login.store' => User::ROLE_DIRECTOR,
            default => User::ROLE_SUPERVISOR,
        };
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Veuillez sélectionner votre utilisateur.',
            'user_id.integer' => 'Veuillez sélectionner un utilisateur valide.',
            'user_id.min' => 'Veuillez sélectionner un utilisateur valide.',
            'password.required' => 'Veuillez saisir votre mot de passe.',
            'password.string' => 'Veuillez saisir un mot de passe valide.',
            'password.max' => 'Le mot de passe saisi est trop long.',
            'email.required' => 'Veuillez saisir votre adresse email.',
            'email.string' => 'Veuillez saisir une adresse email valide.',
            'email.email' => 'Veuillez saisir une adresse email valide.',
            'email.max' => 'L’adresse email saisie est trop longue.',
        ];
    }

    public function authenticate(): void
    {
        $supervisorLogin = $this->expectedRole() === User::ROLE_SUPERVISOR;
        $identifier = $supervisorLogin ? (string) $this->integer('user_id') : 'email:'.$this->string('email')->toString();
        $accountKey = 'login:'.hash('sha256', $identifier.'|'.$this->ip());
        $ipKey = 'login-ip:'.hash('sha256', (string) $this->ip());

        if (RateLimiter::tooManyAttempts($accountKey, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            event(new Lockout($this));
            $seconds = max(RateLimiter::availableIn($accountKey), RateLimiter::availableIn($ipKey));

            throw ValidationException::withMessages([
                'password' => "Trop de tentatives. Réessayez dans {$seconds} secondes.",
            ]);
        }

        $credentials = [
            'password' => $this->string('password')->toString(),
            'active' => true,
            'role' => $this->expectedRole(),
        ];
        $credentials[$supervisorLogin ? 'id' : 'email'] = $supervisorLogin ? $this->integer('user_id') : $this->string('email')->toString();

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($accountKey, 60);
            RateLimiter::hit($ipKey, 60);

            // The same error is used for unknown, inactive and incorrect accounts.
            throw ValidationException::withMessages([
                'password' => 'Connexion impossible. Vérifiez vos identifiants.',
            ]);
        }

        RateLimiter::clear($accountKey);
    }
}
