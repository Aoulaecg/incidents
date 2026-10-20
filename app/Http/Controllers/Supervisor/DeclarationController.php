<?php
namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeclarationRequest;
use App\Models\Incident;
use App\Services\DeclarationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeclarationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === \App\Models\User::ROLE_SUPERVISOR, 403);
        $today = now('Africa/Casablanca');
        $ids = $request->user()->parkings()->pluck('parkings.id');
        return view('supervisor.declarations', [
            'parkings' => $request->user()->parkings()->where('parkings.active', true)->orderBy('nom')->get(),
            'declarations' => Incident::with(['user:id,prenom,nom', 'parking:id,nom'])
                ->whereIn('parking_id', $ids)->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->latest('created_at')->latest('id')->paginate(20),
            'today' => $today, 'submissionToken' => (string) Str::uuid(), 'rasToken' => (string) Str::uuid(),
        ]);
    }
    public function incident(StoreDeclarationRequest $request, DeclarationService $service)
    {
        $created = $service->store($request->user(), $request->validated(), Incident::INCIDENT);
        return redirect()->route('dashboard')->with('selected_parking', $request->integer('parking_id'))
            ->with('status', $created ? 'Incident enregistré avec succès' : 'Cet incident a déjà été enregistré.');
    }
    public function ras(StoreDeclarationRequest $request, DeclarationService $service)
    {
        $created = $service->store($request->user(), $request->validated(), Incident::RAS);
        return redirect()->route('dashboard')->with('selected_parking', $request->integer('parking_id'))
            ->with('status', $created ? 'Déclaration enregistrée avec succès' : 'Une déclaration « Rien à signaler » existe déjà pour ce parking aujourd’hui.');
    }
}
