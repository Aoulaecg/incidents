@extends('layouts.auth')

@section('title', 'Espace superviseur — Journal d’Incidents CGPark')

@section('content')
    <x-feedback />
    <div class="intro text-center">
        <p class="eyebrow">ESPACE SUPERVISEUR</p>
        <span class="connection-check"><x-icon name="check" /></span>
        <h1 id="page-title">Bienvenue, {{ $user->prenom ?: $user->nom }}</h1>
        <p class="intro-description">Vous êtes connecté à votre espace personnel.</p>
    </div>
    <div class="connected-account d-flex align-items-center gap-3">
        <span class="account-avatar"><x-icon name="user" /></span>
        <div><strong class="d-block">{{ $user->full_name }}</strong><span>{{ $user->isAdministrator() ? 'Administrateur' : 'Superviseur' }} CGPark</span></div>
    </div>
    <p class="workspace-note text-center">Votre accès est prêt. Le tableau de bord et le journal des incidents seront disponibles lors de la prochaine étape.</p>
    @can('manage-users')<p class="text-center"><a href="{{ route('admin.supervisors.index') }}">Gestion des superviseurs</a></p>@endcan
    <p class="text-center"><a href="{{ route('password.change') }}">Changer mon mot de passe</a></p>
    <form action="{{ route('logout') }}" method="post">
        @csrf
        <button type="submit" class="btn btn-outline-primary w-100 logout-button d-flex align-items-center justify-content-center gap-2"><x-icon name="logout" /> Se déconnecter</button>
    </form>
@endsection
