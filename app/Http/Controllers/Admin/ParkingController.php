<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveParkingRequest;
use App\Models\{Parking, User};
use App\Services\ParkingService;
use App\Support\ParkingName;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParkingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'active' => ['nullable', Rule::in(['0', '1'])]]);
        $search = ParkingName::clean($filters['q'] ?? '');
        $query = Parking::with(['users' => fn ($query) => $query->where('role', User::ROLE_SUPERVISOR)->orderBy('nom')->orderBy('prenom')]);
        if ($search !== '') $query->whereRaw("nom LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']);
        if (isset($filters['active'])) $query->where('active', $filters['active']);
        return view('admin.parkings.index', ['parkings' => $query->orderBy('nom')->orderBy('id')->paginate(15)->withQueryString(), 'filters' => $filters, 'stats' => ['total' => Parking::count(), 'active' => Parking::where('active', true)->count(), 'inactive' => Parking::where('active', false)->count()]]);
    }

    public function create(): View { return view('admin.parkings.form', ['parking' => new Parking(['active' => true])]); }
    public function edit(Parking $parking): View { return view('admin.parkings.form', compact('parking')); }

    public function store(SaveParkingRequest $request, ParkingService $service): RedirectResponse
    {
        $service->save($request->user(), $request->validated());
        return redirect()->route('admin.parkings.index')->with('status', 'Parking ajouté avec succès');
    }

    public function update(SaveParkingRequest $request, Parking $parking, ParkingService $service): RedirectResponse
    {
        $service->save($request->user(), $request->validated(), $parking);
        return redirect()->route('admin.parkings.index')->with('status', 'Parking modifié avec succès. Les affectations et l’historique sont conservés.');
    }
}
