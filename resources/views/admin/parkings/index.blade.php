@extends('layouts.admin')
@section('title', 'Gestion des parkings')
@section('content')
    <div class="admin-page-heading"><div><h1>Gestion des parkings</h1><p>Gérez les parkings CGPark et consultez leurs superviseurs affectés.</p></div><a class="btn btn-primary admin-primary" href="{{ route('admin.parkings.create') }}"><x-icon name="plus" /> Ajouter un parking</a></div>
    <x-feedback />
    <div class="account-stats parking-stats">
        @foreach([['total', 'Parkings enregistrés', 'parking'], ['active', 'Parkings actifs', 'check'], ['inactive', 'Parkings inactifs', 'pause']] as [$key, $label, $icon])
            <div class="stat-card"><span class="stat-icon stat-{{ $key }}"><x-icon :name="$icon" /></span><div><strong>{{ $stats[$key] }}</strong><span>{{ $label }}</span></div></div>
        @endforeach
    </div>
    <section class="admin-panel" aria-label="Liste des parkings">
        <form class="account-filters parking-filters" action="{{ route('admin.parkings.index') }}" method="get">
            <div class="search-filter"><label class="form-label" for="q">Rechercher un parking</label><div class="input-with-icon"><x-icon name="search" class="field-icon" /><input class="form-control" id="q" name="q" type="search" maxlength="255" value="{{ $filters['q'] ?? '' }}" placeholder="Nom du parking"></div></div>
            <div><label class="form-label" for="active">Statut</label><select class="form-select" id="active" name="active"><option value="">Tous les statuts</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Actif</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Inactif</option></select></div>
            <button class="btn btn-filter" type="submit"><x-icon name="filter" /> Filtrer</button>
            @if(array_filter($filters, fn ($value) => $value !== null && $value !== ''))<a class="filter-reset" href="{{ route('admin.parkings.index') }}">Réinitialiser</a>@endif
        </form>
        <div class="table-heading"><h2>Parkings <span class="count-badge">{{ $parkings->total() }}</span></h2><span>{{ $parkings->total() }} résultat{{ $parkings->total() > 1 ? 's' : '' }}</span></div>
        <div class="accounts-table-wrapper"><table class="table accounts-table mb-0"><thead><tr><th scope="col">ID</th><th scope="col">Nom du parking</th><th scope="col">Superviseurs affectés</th><th scope="col">Statut</th><th scope="col" class="text-end">Actions</th></tr></thead><tbody>
            @forelse($parkings as $parking)
                <tr><td data-label="ID"><span class="account-id">#{{ $parking->id }}</span></td><td data-label="Parking"><strong class="account-name">{{ $parking->nom }}</strong></td><td data-label="Superviseurs"><div class="assignment-names">@forelse($parking->users as $supervisor)<span>{{ $supervisor->full_name }}@unless($supervisor->active) <small>(compte inactif)</small>@endunless</span>@empty<span class="form-hint">Aucun superviseur affecté</span>@endforelse</div></td><td data-label="Statut"><span class="account-status {{ $parking->active ? 'is-active' : 'is-inactive' }}"><span></span>{{ $parking->active ? 'Actif' : 'Inactif' }}</span></td><td data-label="Actions"><div class="row-actions"><a class="row-action" href="{{ route('admin.parkings.edit', $parking) }}" aria-label="Modifier {{ $parking->nom }}"><x-icon name="edit" /><span>Modifier</span></a></div></td></tr>
            @empty
                <tr><td colspan="5" class="empty-accounts"><x-icon name="parking" /><strong>Aucun parking trouvé</strong><p>{{ $stats['total'] ? 'Modifiez votre recherche ou vos filtres.' : 'Ajoutez un parking pour pouvoir l’affecter à vos superviseurs.' }}</p></td></tr>
            @endforelse
        </tbody></table></div>
        <div class="accounts-pagination"><span>@if($parkings->total())Affichage de {{ $parkings->firstItem() }} à {{ $parkings->lastItem() }} sur {{ $parkings->total() }} parkings @else Aucun résultat @endif</span>{{ $parkings->onEachSide(1)->links() }}</div>
    </section>
@endsection
