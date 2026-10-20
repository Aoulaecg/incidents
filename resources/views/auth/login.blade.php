@extends('layouts.auth')

@section('content')
    <div class="intro text-center">
        <!-- <p class="eyebrow">ESPACE SUPERVISEUR</p> -->
        <h1 id="page-title">Journal d’Incidents</h1>
        <p class="intro-description">Plateforme de suivi et de gestion<br class="desktop-break"> des incidents – CGPark</p>
    </div>
    @if (session('status'))
        <div class="alert alert-success status-message" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger status-message" role="alert" tabindex="-1" data-error-summary>
            <span class="d-block fw-semibold mb-1">La connexion n’a pas abouti.</span>
            @foreach ($errors->all() as $error)
                <span class="d-block">{{ $error }}</span>
            @endforeach
        </div>
    @endif
    @if ($users->isEmpty())
        <div class="alert alert-info status-message" role="status">Aucun superviseur n’est disponible pour le moment. Veuillez contacter votre administrateur.</div>
    @endif
    <form action="{{ route('login.store') }}" method="post" class="login-form" data-login-form>
        @csrf
        <div class="field-group">
            <label for="user_id" class="form-label">Utilisateur</label>
            <div class="input-with-icon">
                <x-icon name="user" class="field-icon" />
                <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required @disabled($users->isEmpty()) @error('user_id') aria-invalid="true" aria-describedby="user-error" @enderror>
                    <option value="" disabled @selected(! old('user_id'))>Sélectionner votre utilisateur</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->full_name }}</option>
                    @endforeach
                </select>
            </div>
            @error('user_id')<p id="user-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="field-group">
            <label for="password" class="form-label">Mot de passe</label>
            <div class="input-with-icon password-wrapper">
                <x-icon name="lock" class="field-icon" />
                <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" placeholder="Saisissez votre mot de passe" autocomplete="current-password" required maxlength="1024" @disabled($users->isEmpty()) @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button type="button" class="password-toggle" aria-label="Afficher le mot de passe" aria-controls="password" aria-pressed="false" data-password-toggle hidden>
                    <x-icon name="eye" data-eye-show />
                    <x-icon name="eye-off" data-eye-hide hidden />
                </button>
            </div>
            @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn-primary login-button w-100 d-flex align-items-center justify-content-center gap-2" @disabled($users->isEmpty()) data-submit-button>
            <span data-submit-label>Se connecter</span><x-icon name="arrow" />
        </button>
    </form>
    <div class="card-help text-center"><span>Un problème de connexion ?</span><p>Contactez votre administrateur.</p></div>
@endsection
