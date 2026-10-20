<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $loginRoute = $user?->loginRoute() ?? 'login';

        if (! $user?->active || ! in_array($user->role, User::ROLES, true)
            || (int) $request->session()->get('auth_version', 0) !== $user->auth_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($loginRoute)->with('status', 'Votre session a expiré. Veuillez vous reconnecter.');
        }

        return $next($request);
    }
}
