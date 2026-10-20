<?php
namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeclarationRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role === User::ROLE_SUPERVISOR; }
    public function rules(): array
    {
        $rules = [
            'parking_id' => ['required', 'integer', Rule::exists('parkings', 'id')->where('active', true)],
            'submission_token' => ['required', 'uuid'],
        ];
        if ($this->routeIs('supervisor.ras.store')) {
            $rules['confirm_ras'] = ['required', 'accepted'];
        } else {
            $rules += [
                'date_incident' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Africa/Casablanca')->toDateString()],
                'heure_incident' => ['required', 'date_format:H:i'],
                'description' => ['required', 'string', 'max:5000'],
            ];
        }
        return $rules;
    }
    public function messages(): array
    {
        return ['parking_id.exists' => 'Ce parking n’est pas disponible.', 'confirm_ras.accepted' => 'Veuillez confirmer qu’il n’y a rien à signaler.', 'confirm_ras.required' => 'Veuillez confirmer qu’il n’y a rien à signaler.'];
    }
}
