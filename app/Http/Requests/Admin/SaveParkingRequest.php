<?php

namespace App\Http\Requests\Admin;

use App\Support\ParkingName;
use Illuminate\Foundation\Http\FormRequest;

class SaveParkingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdministrator() ?? false; }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nom'))) $this->merge(['nom' => ParkingName::clean($this->input('nom'))]);
    }

    public function rules(): array
    {
        return ['nom' => ['bail', 'required', 'string', 'max:255', function ($attribute, $value, $fail) {
            if (\App\Models\Parking::where('nom_key', ParkingName::key($value))->when($this->route('parking'), fn ($query, $parking) => $query->whereKeyNot($parking->id))->exists()) {
                $fail('Un parking portant ce nom existe déjà.');
            }
        }], 'active' => ['required', 'boolean']];
    }
}
