<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
        if ($this->exists('parking_ids') && $this->input('parking_ids') === null) $this->merge(['parking_ids' => []]);
    }

    public function rules(): array
    {
        $supervisor = $this->route('supervisor');
        $rules = [
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($supervisor?->id)],
            'role' => ['required', Rule::in(User::ROLES)],
            'active' => ['required', 'boolean'],
            'parking_ids' => [Rule::excludeIf($this->input('role') !== User::ROLE_SUPERVISOR), Rule::requiredIf($this->input('role') === User::ROLE_SUPERVISOR && (! $supervisor || $supervisor->role !== User::ROLE_SUPERVISOR)), 'array', 'max:1000'],
            'parking_ids.*' => ['integer', 'distinct', Rule::exists('parkings', 'id')->where('active', true)],
        ];

        if (! $supervisor) {
            $rules['password'] = PasswordRules::confirmed();
        } elseif ($supervisor->active && ! $this->boolean('active')) {
            $rules['confirm_deactivation'] = ['required', 'accepted'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['parking_ids.required' => 'Sélectionnez au moins un parking actif pour ce superviseur.', 'parking_ids.*.exists' => 'Sélectionnez uniquement des parkings actifs et existants.'];
    }
}
