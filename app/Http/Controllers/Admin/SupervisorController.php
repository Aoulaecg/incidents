<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\SaveSupervisorRequest;
use App\Models\User;
use App\Models\Parking;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', Rule::in(['0', '1'])],
            'role' => ['nullable', Rule::in(User::ROLES)],
        ]);
        $search = trim($filters['q'] ?? '');
        $query = User::query()->with(['parkings' => fn ($query) => $query->orderBy('nom')])->select(['id', 'prenom', 'nom', 'email', 'role', 'active', 'created_at']);

        if ($search !== '') {
            // Use bound LIKE patterns; escape wildcard characters for literal searches.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function ($query) use ($pattern) {
                $query->whereRaw("prenom LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("nom LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("name LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return view('admin.supervisors.index', [
            'supervisors' => $query->orderBy('nom')->orderBy('prenom')->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'total' => User::count(),
                'active' => User::where('active', true)->count(),
                'inactive' => User::where('active', false)->count(),
                'admins' => User::where('role', User::ROLE_ADMIN)->where('active', true)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return $this->accountForm(new User(['role' => User::ROLE_SUPERVISOR]));
    }

    public function store(SaveSupervisorRequest $request, UserAccountService $accounts): RedirectResponse
    {
        $accounts->create($request->user(), $request->validated());

        return redirect()->route('admin.supervisors.index')->with('status', 'Le compte a été créé avec succès');
    }

    public function edit(User $supervisor): View
    {
        return $this->accountForm($supervisor);
    }

    private function accountForm(User $supervisor): View
    {
        $assigned = $supervisor->exists ? $supervisor->parkings()->orderBy('nom')->get() : collect();
        return view('admin.supervisors.form', ['supervisor' => $supervisor, 'parkings' => Parking::where('active', true)->orderBy('nom')->get(), 'assigned' => $assigned->pluck('id')->all(), 'inactiveAssigned' => $assigned->where('active', false)]);
    }

    public function update(SaveSupervisorRequest $request, User $supervisor, UserAccountService $accounts): RedirectResponse
    {
        $updated = $accounts->update($request->user(), $supervisor, $request->validated());
        $destination = $request->user()->id === $updated->id && ! $updated->isAdministrator()
            ? $updated->homeRoute() : 'admin.supervisors.index';

        return redirect()->route($destination)->with('status', 'Le compte a été modifié avec succès.');
    }

    public function confirmDeactivation(User $supervisor): View
    {
        return view('admin.supervisors.deactivate', compact('supervisor'));
    }

    public function updateStatus(Request $request, User $supervisor, UserAccountService $accounts): RedirectResponse
    {
        $rules = ['active' => ['required', 'boolean']];
        if (! $request->boolean('active')) {
            $rules['confirm_deactivation'] = ['required', 'accepted'];
        }
        $request->validate($rules);
        $accounts->update($request->user(), $supervisor, ['active' => $request->boolean('active')]);

        return redirect()->route('admin.supervisors.index')->with('status', $request->boolean('active')
            ? 'Le compte a été réactivé avec succès.' : 'Le compte a été désactivé. Son historique est conservé.');
    }

    public function editPassword(User $supervisor): View
    {
        return view('admin.supervisors.password', compact('supervisor'));
    }

    public function resetPassword(ResetPasswordRequest $request, User $supervisor, UserAccountService $accounts): RedirectResponse
    {
        $accounts->resetPassword($request->user(), $supervisor, $request->validated('password'));

        if ($request->user()->id === $supervisor->id) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($supervisor->loginRoute())->with('status', 'Votre mot de passe a été réinitialisé. Reconnectez-vous avec le mot de passe temporaire.');
        }

        return redirect()->route('admin.supervisors.index')->with('status', 'Le mot de passe temporaire a été enregistré. Les sessions ont été révoquées ; le compte devra changer de mot de passe à sa prochaine connexion.');
    }
}
