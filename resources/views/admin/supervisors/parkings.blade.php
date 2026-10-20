@extends('layouts.admin')
@section('title', 'Affecter des parkings')
@section('content')
    <a class="back-link" href="{{ route('admin.supervisors.index') }}"><x-icon name="arrow" /> Retour aux comptes</a><div class="admin-page-heading"><div><p class="eyebrow">GESTION DES AFFECTATIONS</p><h1>Parkings de {{ $supervisor->full_name }}</h1><p>Sélectionnez les parkings que ce superviseur pourra consulter et déclarer.</p></div></div>
    <section class="admin-panel admin-form-panel"><x-feedback /><form action="{{ route('admin.supervisors.parkings.update', $supervisor) }}" method="post">@csrf @method('PUT')
        <x-parking-selector :parkings="$parkings" :assigned="$assigned" :inactive-assigned="$inactiveAssigned" />
        <div class="form-actions"><a class="btn btn-light" href="{{ route('admin.supervisors.index') }}">Annuler</a><button class="btn btn-primary admin-primary" type="submit">Enregistrer les affectations</button></div>
    </form></section>
@endsection
