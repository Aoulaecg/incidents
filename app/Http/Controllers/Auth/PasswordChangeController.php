<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccountService;
use App\Support\PasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.change-password', ['user' => $request->user()]);
    }

    public function update(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => [...PasswordRules::confirmed(), 'different:current_password'],
        ]);

        $user = DB::transaction(function () use ($request, $data, $accounts) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            if ($user->auth_version !== (int) $request->session()->get('auth_version', 0)
                || ! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Votre session a changé. Veuillez vous reconnecter.']);
            }

            $user->password = $data['password'];
            $user->must_change_password = false;
            $user->auth_version++;
            $user->remember_token = Str::random(60);
            $user->save();
            $accounts->deleteSessions($user, $request->session()->getId());

            return $user;
        }, 3);

        Auth::login($user);
        $request->session()->put('auth_version', $user->auth_version);
        $request->session()->regenerate();

        return redirect()->route($user->homeRoute())->with('status', 'Votre mot de passe personnel a été enregistré avec succès.');
    }
}
