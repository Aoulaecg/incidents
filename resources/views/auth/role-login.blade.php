@extends('layouts.auth')
@section('title', $spaceTitle.' — Journal d’Incidents CGPark')
@section('description', $spaceDescription)
@section('login-home', route($loginRoute))
@section('security-note', $securityNote)
@section('content')
    <div class="intro text-center">
        <!-- <p class="eyebrow">JOURNAL D’INCIDENTS</p> -->
        <h1 id="page-title">{{ $spaceTitle }}</h1>
        <p class="intro-description">{{ $spaceDescription }}</p>
    </div>
    <x-feedback />
    <form action="{{ route($loginRoute.'.store') }}" method="post" class="login-form" data-login-form>
        @csrf
        <div class="field-group">
            <label for="email" class="form-label">Adresse email</label>
            <div class="input-with-icon"><x-icon name="mail" class="field-icon" /><input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ is_string(old('email')) ? old('email') : '' }}" placeholder="Saisissez votre adresse email" autocomplete="username" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror></div>
            @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="password" class="form-label">Mot de passe</label>
            <div class="input-with-icon password-wrapper"><x-icon name="lock" class="field-icon" /><input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" placeholder="Saisissez votre mot de passe" autocomplete="current-password" maxlength="1024" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror><button type="button" class="password-toggle" aria-label="Afficher le mot de passe" aria-controls="password" aria-pressed="false" data-password-toggle hidden><x-icon name="eye" data-eye-show /><x-icon name="eye-off" data-eye-hide hidden /></button></div>
            @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn-primary login-button w-100 d-flex align-items-center justify-content-center gap-2" data-submit-button><span data-submit-label>Se connecter</span><x-icon name="arrow" /></button>
    </form>
    <div class="card-help text-center"><span>Un problème de connexion ?</span><p>Contactez votre administrateur.</p></div>
@endsection
