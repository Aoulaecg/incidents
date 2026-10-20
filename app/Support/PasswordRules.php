<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    public static function confirmed(): array
    {
        return [
            'required', 'string', 'confirmed',
            Password::min(12)->mixedCase()->numbers()->symbols(),
            function (string $attribute, mixed $value, \Closure $fail) {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('Le mot de passe ne doit pas dépasser 72 octets.');
                }
            },
        ];
    }
}
