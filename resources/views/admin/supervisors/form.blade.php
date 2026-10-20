@extends('layouts.admin')
@section('title', $supervisor->exists ? 'Modifier un compte' : 'Ajouter un superviseur')
@section('content')
    <a class="back-link" href="{{ route('admin.supervisors.index') }}"><x-icon name="arrow" /> Retour aux comptes</a>
    <div class="admin-page-heading"><div><p class="eyebrow">GESTION DES SUPERVISEURS</p><h1>{{ $supervisor->exists ? 'Modifier un compte' : 'Ajouter un superviseur' }}</h1><p>{{ $supervisor->exists ? 'Mettez à jour les informations et les droits de ce compte.' : 'Créez un accès personnel pour un membre de votre équipe.' }}</p></div></div>
    <div class="admin-form-panel admin-panel">
        <x-feedback />
        <form action="{{ $supervisor->exists ? route('admin.supervisors.update', $supervisor) : route('admin.supervisors.store') }}" method="post">
            @csrf
            @if($supervisor->exists) @method('PUT') @endif
            <div class="form-section-heading"><x-icon name="user" /><div><h2>Informations personnelles</h2><p>Les champs marqués d’un astérisque sont obligatoires.</p></div></div>
            <div class="row g-3">
                @foreach(['prenom' => 'Prénom', 'nom' => 'Nom', 'email' => 'Adresse email professionnelle'] as $field => $label)
                    <div class="{{ $field === 'email' ? 'col-12' : 'col-sm-6' }}"><div class="field-group"><label class="form-label" for="{{ $field }}">{{ $label }} *</label><input class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field, $supervisor->$field) }}" maxlength="{{ $field === 'email' ? 255 : 100 }}" autocomplete="{{ ['prenom' => 'given-name', 'nom' => 'family-name', 'email' => 'email'][$field] }}" required @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>@error($field)<p class="field-error" id="{{ $field }}-error">{{ $message }}</p>@enderror</div></div>
                @endforeach
            </div>
            <div class="form-section-heading section-divider"><x-icon name="shield" /><div><h2>Accès à la plateforme</h2><p>Définissez le rôle et la disponibilité du compte.</p></div></div>
            <div class="row g-3">
                <div class="col-sm-6"><div class="field-group"><label class="form-label" for="account_role">Rôle *</label><select class="form-select" id="account_role" name="role" required><option value="supervisor" @selected(old('role', $supervisor->role) === 'supervisor')>Superviseur</option><option value="admin" @selected(old('role', $supervisor->role) === 'admin')>Administrateur</option><option value="directeur" @selected(old('role', $supervisor->role) === 'directeur')>Directeur</option></select>@error('role')<p class="field-error">{{ $message }}</p>@enderror</div></div>
                <div class="col-sm-6"><div class="field-group"><label class="form-label" for="account_active">Statut *</label><select class="form-select" id="account_active" name="active" required @if($supervisor->exists && $supervisor->id === auth()->id()) disabled @endif data-account-status><option value="1" @selected((string) old('active', $supervisor->exists ? (int) $supervisor->active : 1) === '1')>Actif</option><option value="0" @selected((string) old('active', $supervisor->exists ? (int) $supervisor->active : 1) === '0')>Inactif</option></select>@if($supervisor->exists && $supervisor->id === auth()->id())<input type="hidden" name="active" value="1"><small class="form-hint">Votre propre compte doit rester actif.</small>@endif @error('active')<p class="field-error">{{ $message }}</p>@enderror</div></div>
            </div>
            <p class="form-hint">Un administrateur peut gérer les comptes et leurs droits. Un compte inactif ne peut pas se connecter.</p>
            <x-parking-selector :parkings="$parkings" :assigned="$assigned" :inactive-assigned="$inactiveAssigned" :conditional="true" :visible="old('role', $supervisor->role) === 'supervisor'" />
            @if($supervisor->exists && $supervisor->active && $supervisor->id !== auth()->id())
                <div class="deactivation-checkbox" data-deactivation-confirm><label class="d-flex align-items-start gap-2"><input class="form-check-input" type="checkbox" name="confirm_deactivation" value="1" @checked(old('confirm_deactivation'))> Je confirme la désactivation de ce compte et la fermeture de ses sessions.</label></div>
            @endif
            @unless($supervisor->exists)
                <div class="form-section-heading section-divider"><x-icon name="key" /><div><h2>Mot de passe initial</h2><p>Le titulaire devra le remplacer à sa première connexion.</p></div></div>
                <p class="password-guidance">12 caractères minimum, avec une majuscule, une minuscule, un chiffre et un caractère spécial. Maximum : 72 octets.</p>
                <div class="row g-3"><div class="col-sm-6"><x-password-field label="Mot de passe initial" /></div><div class="col-sm-6"><x-password-field name="password_confirmation" label="Confirmation du mot de passe" /></div></div>
            @else
                <div class="info-callout"><x-icon name="lock" /><p>Cette modification conserve le mot de passe actuel. Pour le remplacer, utilisez <a href="{{ route('admin.supervisors.password.edit', $supervisor) }}">la réinitialisation du mot de passe</a>.</p></div>
            @endunless
            <div class="form-actions"><a class="btn btn-light" href="{{ route('admin.supervisors.index') }}">Annuler</a><button class="btn btn-primary admin-primary" type="submit"><x-icon name="check" /> Enregistrer</button></div>
        </form>
    </div>
@endsection
