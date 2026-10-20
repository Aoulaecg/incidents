@extends('layouts.admin')
@section('title', 'Suivi des incidents')
@section('content')
    <div class="admin-page-heading"><div><h1>Tableau de bord — Suivi des incidents CGPark</h1><p>Une vue centralisée des déclarations et du suivi de tous les parkings.</p></div><span class="parking-count"><x-icon name="calendar" /> {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</span></div>
    <x-feedback />
    <p><a href="{{ route('director.notifications') }}">Historique des notifications par email</a></p>
    <section class="admin-panel director-filter-panel">
        <form action="{{ route('director.dashboard') }}" method="get" class="account-filters">
            <div style="flex: 0 0 300px; max-width: 300px;">
                <label class="form-label" for="parking_id">Parking</label>
                <select class="form-select" id="parking_id" name="parking_id" onchange="this.form.submit()">
                    <option value="">Tous les parkings</option>
                    @foreach($parkings as $parking)
                        <option value="{{ $parking->id }}" @selected((string) ($filters['parking_id'] ?? '') === (string) $parking->id)>{{ $parking->nom }}{{ $parking->active ? '' : ' (inactif)' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="date">Date</label>
                <input type="date" class="form-control" id="date" name="date" value="{{ $date }}" required onchange="if (this.value) this.form.submit()">
            </div>
        </form>
    </section>
    <p class="summary-label">Synthèse quotidienne du périmètre sélectionné</p><div class="account-stats director-stats">@foreach([['incidents', 'Incidents signalés', 'alert'], ['ras', 'Rien à signaler', 'check']] as [$key, $label, $icon])<div class="stat-card"><span class="stat-icon stat-{{ $key }}"><x-icon :name="$icon" /></span><div><strong>{{ $stats[$key] }}</strong><span>{{ $label }}</span></div></div>@endforeach</div>
    
    <section class="admin-panel"><div class="table-heading"><h2>Toutes les déclarations <span class="count-badge">{{ $declarations->total() }}</span></h2><span>Consultation uniquement</span></div><x-declarations-table :declarations="$declarations" :show-date="true" /><div class="accounts-pagination"><span>{{ $declarations->total() }} résultat{{ $declarations->total() > 1 ? 's' : '' }}</span>{{ $declarations->onEachSide(1)->links() }}</div></section>
@endsection
