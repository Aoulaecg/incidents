@extends('layouts.auth')
@section('title', 'Changer mon mot de passe — CGPark')
@section('content')
    <div class="intro text-center"><p class="eyebrow">SÉCURITÉ DU COMPTE</p><h1 id="page-title">{{ $user->must_change_password ? 'Votre mot de passe personnel' : 'Changer mon mot de passe' }}</h1><p class="intro-description">{{ $user->must_change_password ? 'Remplacez votre mot de passe temporaire pour accéder à votre espace.' : 'Choisissez un nouveau mot de passe pour votre compte.' }}</p></div>
    <x-feedback />
    <form action="{{ route('password.update') }}" method="post">@csrf @method('PUT')
        <x-password-field name="current_password" label="Mot de passe actuel" autocomplete="current-password" />
        <x-password-field label="Nouveau mot de passe" />
        <x-password-field name="password_confirmation" label="Confirmation du mot de passe" />
        <p class="password-guidance">12 caractères minimum, avec une majuscule, une minuscule, un chiffre et un caractère spécial. Maximum : 72 octets.</p>
        <button class="btn btn-primary login-button w-100" type="submit">Enregistrer mon mot de passe</button>
    </form>
    <div class="card-help text-center">@unless($user->must_change_password)<a href="{{ route($user->homeRoute()) }}">Retour à mon espace</a>@endunless<form action="{{ route('logout') }}" method="post" class="mt-2">@csrf<button type="submit" class="text-button">Se déconnecter</button></form></div>
@endsection
