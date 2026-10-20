@extends('layouts.admin')
@section('title', 'Réinitialiser le mot de passe')
@section('content')
    <a class="back-link" href="{{ route('admin.supervisors.index') }}"><x-icon name="arrow" /> Retour aux comptes</a>
    <div class="admin-page-heading"><div><p class="eyebrow">SÉCURITÉ DU COMPTE</p><h1>Réinitialiser le mot de passe</h1><p>Définissez un mot de passe temporaire pour {{ $supervisor->full_name }}.</p></div></div>
    <div class="admin-panel admin-form-panel compact-form">
        <x-feedback />
        <div class="info-callout"><x-icon name="shield" /><p>Les sessions du compte seront fermées. Le titulaire devra choisir un mot de passe personnel à sa prochaine connexion.@if($supervisor->id === auth()->id()) Vous serez également déconnecté.@endif</p></div>
        <p class="password-guidance">12 caractères minimum : majuscule, minuscule, chiffre et caractère spécial. Maximum : 72 octets.</p>
        <form action="{{ route('admin.supervisors.password.reset', $supervisor) }}" method="post">
            @csrf @method('PUT')
            <x-password-field label="Nouveau mot de passe temporaire" />
            <x-password-field name="password_confirmation" label="Confirmation du mot de passe" />
            <div class="form-actions"><a class="btn btn-light" href="{{ route('admin.supervisors.index') }}">Annuler</a><button class="btn btn-primary admin-primary" type="submit">Réinitialiser le mot de passe</button></div>
        </form>
    </div>
@endsection
