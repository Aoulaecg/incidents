<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Parking, User};
use App\Services\UserAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParkingAssignmentController extends Controller
{
    public function edit(User $supervisor)
    {
        abort_unless($supervisor->role === User::ROLE_SUPERVISOR, 403);
        $assigned = $supervisor->parkings()->orderBy('nom')->get();
        return view('admin.supervisors.parkings', ['supervisor' => $supervisor, 'parkings' => Parking::where('active', true)->orderBy('nom')->get(), 'assigned' => $assigned->pluck('id')->all(), 'inactiveAssigned' => $assigned->where('active', false)]);
    }
    public function update(Request $request, User $supervisor, UserAccountService $accounts)
    {
        $data = $request->validate(['parking_ids' => ['nullable', 'array', 'max:1000'], 'parking_ids.*' => ['integer', 'distinct', Rule::exists('parkings', 'id')->where('active', true)]]);
        $accounts->assignParkings($request->user(), $supervisor, $data['parking_ids'] ?? []);
        return redirect()->route('admin.supervisors.index')->with('status', 'Les affectations de parkings ont été mises à jour.');
    }
}
