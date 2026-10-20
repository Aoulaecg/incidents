@extends('layouts.admin')
@section('title', $parking->exists ? 'Modifier un parking' : 'Ajouter un parking')
@section('content')
    <a class="back-link" href="{{ route('admin.parkings.index') }}"><x-icon name="arrow" /> Retour aux parkings</a>
    <div class="admin-page-heading"><div><h1>{{ $parking->exists ? 'Modifier un parking' : 'Ajouter un parking' }}</h1><p>Renseignez le nom et la disponibilité du parking.</p></div></div>
    <div class="admin-panel admin-form-panel"><x-feedback />
        <form method="post" action="{{ $parking->exists ? route('admin.parkings.update', $parking) : route('admin.parkings.store') }}">
            @csrf @if($parking->exists) @method('PUT') @endif
            <div class="field-group"><label class="form-label" for="nom">Nom du parking *</label><input class="form-control @error('nom') is-invalid @enderror" id="nom" name="nom" value="{{ old('nom', $parking->nom) }}" required maxlength="255" placeholder="Ex. : Parking Rabat Centre" @error('nom') aria-invalid="true" aria-describedby="nom-error" @enderror>@error('nom')<p class="field-error" id="nom-error">{{ $message }}</p>@enderror</div>
            <div class="field-group"><label class="form-label" for="active">Statut *</label><select class="form-select" id="active" name="active" required><option value="1" @selected((string) old('active', (int) $parking->active) === '1')>Actif</option><option value="0" @selected((string) old('active', (int) $parking->active) === '0')>Inactif</option></select>@error('active')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div class="info-callout"><x-icon name="shield" /><p>Un parking inactif ne peut pas recevoir de nouvelles affectations ou déclarations. Ses affectations existantes et son historique restent conservés.</p></div>
            <div class="form-actions"><a class="btn btn-light" href="{{ route('admin.parkings.index') }}">Annuler</a><button class="btn btn-primary admin-primary" type="submit"><x-icon name="check" /> Enregistrer le parking</button></div>
        </form>
    </div>
@endsection
