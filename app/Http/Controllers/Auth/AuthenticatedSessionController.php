<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login', [
            'users' => User::query()
                ->where('active', true)
                ->where('role', User::ROLE_SUPERVISOR)
                ->orderBy('nom')
                ->orderBy('prenom')
                ->get(['id', 'nom', 'prenom']),
        ]);
    }

    public function createAdministrator(): View
    {
        return view('auth.role-login', [
            'spaceTitle' => 'Espace Administrateur', 'spaceDescription' => 'Gestion des comptes et des affectations – CGPark',
            'loginRoute' => 'admin.login', 'securityNote' => 'Accès réservé aux administrateurs autorisés',
        ]);
    }

    public function createDirector(): View
    {
        return view('auth.role-login', [
            'spaceTitle' => 'Journal d\'incidents', 'spaceDescription' => 'Suivi centralisé des incidents – CGPark',
            'loginRoute' => 'director.login', 'securityNote' => 'Accès réservé à la Direction',
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->put('auth_version', $request->user()->auth_version);
        $request->session()->regenerate();

        return redirect()->route($request->user()->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $loginRoute = $request->user()->loginRoute();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($loginRoute)->with('status', 'Vous avez été déconnecté en toute sécurité.');
    }
}
