@extends('layouts.admin')
@section('content')
    <div class="admin-page-heading">
        <div><p class="eyebrow"></p><h1>Gestion des superviseurs</h1><p>Gérez les accès de vos équipes au Journal d’Incidents.</p></div>
        <a class="btn btn-primary admin-primary" href="{{ route('admin.supervisors.create') }}"><x-icon name="plus" /> Ajouter un superviseur</a>
    </div>
    <x-feedback />
    <div class="account-stats">
        @foreach ([['total', 'Comptes enregistrés', 'users'], ['active', 'Comptes actifs', 'check'], ['inactive', 'Comptes inactifs', 'pause'], ['admins', 'Administrateurs actifs', 'shield']] as [$key, $label, $icon])
            <div class="stat-card"><span class="stat-icon stat-{{ $key }}"><x-icon :name="$icon" /></span><div><strong>{{ $stats[$key] }}</strong><span>{{ $label }}</span></div></div>
        @endforeach
    </div>
    <section class="admin-panel" aria-label="Liste des comptes">
        <form class="account-filters" action="{{ route('admin.supervisors.index') }}" method="get">
            <div class="search-filter"><label class="form-label" for="q">Rechercher</label><div class="input-with-icon"><x-icon name="search" class="field-icon" /><input class="form-control" type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Nom, prénom ou email"></div></div>
            <div><label class="form-label" for="active">Statut</label><select class="form-select" id="active" name="active"><option value="">Tous les statuts</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Actif</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Inactif</option></select></div>
            <div><label class="form-label" for="role">Rôle</label><select class="form-select" id="role" name="role"><option value="">Tous les rôles</option><option value="supervisor" @selected(($filters['role'] ?? '') === 'supervisor')>Superviseur</option><option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Administrateur</option><option value="directeur" @selected(($filters['role'] ?? '') === 'directeur')>Directeur</option></select></div>
            <button class="btn btn-filter" type="submit"><x-icon name="filter" /> Filtrer</button>
            @if (array_filter($filters, fn ($value) => $value !== null && $value !== ''))<a class="filter-reset" href="{{ route('admin.supervisors.index') }}">Réinitialiser</a>@endif
        </form>
        <div class="table-heading"><h2>Comptes utilisateurs <span class="count-badge">{{ $supervisors->total() }}</span></h2><span>{{ $supervisors->total() }} résultat{{ $supervisors->total() > 1 ? 's' : '' }}</span></div>
        <div class="accounts-table-wrapper">
            <table class="table accounts-table mb-0">
                <thead><tr><th scope="col">ID</th><th scope="col">Nom complet</th><th scope="col">Email</th><th scope="col">Rôle</th><th scope="col">Parkings affectés</th><th scope="col">Statut</th><th scope="col">Date de création</th><th scope="col" class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($supervisors as $supervisor)
                        <tr>
                            <td data-label="ID"><span class="account-id">#{{ $supervisor->id }}</span></td>
                            <td data-label="Nom complet"><strong class="account-name">{{ $supervisor->full_name }}</strong>@if($supervisor->id === auth()->id())<span class="you-badge">Vous</span>@endif</td>
                            <td data-label="Email" class="account-email">{{ $supervisor->email }}</td>
                            <td data-label="Rôle"><span class="role-badge {{ $supervisor->role === 'admin' ? 'role-admin' : '' }}">{{ $supervisor->roleLabel() }}</span></td>
                            <td data-label="Parkings"><div class="assignment-names">@if($supervisor->role !== 'supervisor')<span>—</span>@elseif($supervisor->parkings->isEmpty())<span class="form-hint">Aucun parking affecté</span>@elseif($supervisor->parkings->count() > 3)<details><summary>{{ $supervisor->parkings->count() }} parkings</summary>@foreach($supervisor->parkings as $parking)<span>{{ $parking->nom }}@unless($parking->active) <small>(inactif)</small>@endunless</span>@endforeach</details>@else @foreach($supervisor->parkings as $parking)<span>{{ $parking->nom }}@unless($parking->active) <small>(inactif)</small>@endunless</span>@endforeach @endif</div></td>
                            <td data-label="Statut"><span class="account-status {{ $supervisor->active ? 'is-active' : 'is-inactive' }}"><span></span>{{ $supervisor->active ? 'Actif' : 'Inactif' }}</span></td>
                            <td data-label="Créé le" class="account-date">{{ $supervisor->created_at?->format('d/m/Y') ?? '—' }}</td>
                            <td data-label="Actions"><div class="row-actions">
                                @if($supervisor->role === 'supervisor')<a href="{{ route('admin.supervisors.parkings.edit', $supervisor) }}" class="row-action" title="Parkings de {{ $supervisor->full_name }}" aria-label="Parkings de {{ $supervisor->full_name }}"><x-icon name="parking" /><span>Parkings</span></a>@endif
                                <a href="{{ route('admin.supervisors.edit', $supervisor) }}" class="row-action" title="Modifier {{ $supervisor->full_name }}" aria-label="Modifier {{ $supervisor->full_name }}"><x-icon name="edit" /><span>Modifier</span></a>
                                @if ($supervisor->active)
                                    @if ($supervisor->id === auth()->id())
                                        <button class="row-action" type="button" disabled title="Vous ne pouvez pas désactiver votre propre compte" aria-label="Désactiver votre compte : action indisponible"><x-icon name="pause" /><span>Désactiver</span></button>
                                    @else
                                        <a class="row-action action-disable" href="{{ route('admin.supervisors.deactivate', $supervisor) }}" title="Désactiver {{ $supervisor->full_name }}" aria-label="Désactiver {{ $supervisor->full_name }}"><x-icon name="pause" /><span>Désactiver</span></a>
                                    @endif
                                @else
                                    <form action="{{ route('admin.supervisors.status', $supervisor) }}" method="post">@csrf @method('PATCH')<input type="hidden" name="active" value="1"><button class="row-action action-enable" type="submit" title="Réactiver {{ $supervisor->full_name }}" aria-label="Réactiver {{ $supervisor->full_name }}"><x-icon name="play" /><span>Réactiver</span></button></form>
                                @endif
                                <a class="row-action" href="{{ route('admin.supervisors.password.edit', $supervisor) }}" title="Réinitialiser le mot de passe de {{ $supervisor->full_name }}" aria-label="Réinitialiser le mot de passe de {{ $supervisor->full_name }}"><x-icon name="key" /><span>Mot de passe</span></a>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-accounts"><x-icon name="users" /><strong>Aucun compte trouvé</strong><p>{{ $supervisors->total() === 0 && $stats['total'] > 0 ? 'Essayez une autre recherche ou modifiez vos filtres.' : 'Ajoutez un superviseur pour lui donner accès à la plateforme.' }}</p>@if($stats['total'] > 0)<a href="{{ route('admin.supervisors.index') }}">Effacer les filtres</a>@endif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="accounts-pagination">
            <span>
                @if($supervisors->total())
                    Affichage de {{ $supervisors->firstItem() }} à {{ $supervisors->lastItem() }} sur {{ $supervisors->total() }} comptes
                @else
                    Aucun résultat
                @endif
            </span>
            {{ $supervisors->onEachSide(1)->links() }}
        </div>
    </section>
@endsection
