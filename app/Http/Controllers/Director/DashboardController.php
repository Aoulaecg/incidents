<?php
namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\{Incident, Parking};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'parking_id' => ['nullable', 'integer', 'exists:parkings,id'],
            'date' => ['nullable', 'date_format:Y-m-d'], 'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in([Incident::INCIDENT, Incident::RAS])],
            'sort' => ['nullable', Rule::in(['recent', 'oldest'])],
        ]);
        $date = $filters['date'] ?? now('Africa/Casablanca')->toDateString();
        $base = Incident::whereDate('date_incident', $date)->when($filters['parking_id'] ?? null, fn ($q, $id) => $q->where('parking_id', $id));
        $missing = Parking::where('active', true)->when($filters['parking_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->whereDoesntHave('incidents', fn ($q) => $q->whereDate('date_incident', $date)->where('status', 'active'))->orderBy('nom')->get();
        $query = (clone $base)->with(['user:id,prenom,nom', 'parking:id,nom']);
        if (! empty($filters['type'])) $query->where('type_declaration', $filters['type']);
        if (! empty($filters['q'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['q'])).'%';
            $query->where(fn ($q) => $q->whereRaw("description LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('parking', fn ($p) => $p->whereRaw("nom LIKE ? ESCAPE '!'", [$pattern]))
                ->orWhereHas('user', fn ($u) => $u->where(fn ($names) => $names->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("prenom LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("nom LIKE ? ESCAPE '!'", [$pattern]))));
        }
        $direction = ($filters['sort'] ?? 'recent') === 'oldest' ? 'asc' : 'desc';
        return view('director.dashboard', [
            'filters' => $filters, 'date' => $date, 'parkings' => Parking::orderBy('nom')->get(), 'missingParkings' => $missing,
            'stats' => [
                'incidents' => (clone $base)->where('type_declaration', Incident::INCIDENT)->where('status', 'active')->count(),
                'ras' => (clone $base)->where('type_declaration', Incident::RAS)->where('status', 'active')->count(), 'missing' => $missing->count(),
            ],
            'declarations' => $query->orderByRaw('COALESCE(heure_incident, TIME(validated_at)) '.$direction)->orderBy('id', $direction)->paginate(20)->withQueryString(),
        ]);
    }
}
