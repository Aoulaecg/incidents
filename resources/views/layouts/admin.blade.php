<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0094B8">
    <meta name="description" content="Administration des comptes — Journal d’Incidents CGPark.">
    <title>@yield('title', 'Gestion des superviseurs') — CGPark</title>
    <link rel="icon" type="image/png" href="{{ asset('images/cgpark-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-page">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <button class="sidebar-overlay" type="button" data-menu-close aria-label="Fermer la navigation" hidden></button>
    <aside class="admin-sidebar" id="admin-navigation" aria-label="Navigation principale">
        <a class="sidebar-brand" href="{{ route(auth()->user()->homeRoute()) }}"><img src="{{ asset('images/cgpark-logo.png') }}" alt="Compagnie Générale des Parkings — Groupe CDG" width="295" height="164"></a>
        <div class="sidebar-app-name">Journal d’Incidents</div>
        <p class="nav-section-label">ESPACE DE TRAVAIL</p>
        <nav>
            @can('manage-users')
            <a class="sidebar-link {{ request()->routeIs('admin.supervisors.*') ? 'is-current' : '' }}" href="{{ route('admin.supervisors.index') }}" @if(request()->routeIs('admin.supervisors.*')) aria-current="page" @endif><x-icon name="users" /> Gestion des superviseurs</a>
            <a class="sidebar-link {{ request()->routeIs('admin.parkings.*') ? 'is-current' : '' }}" href="{{ route('admin.parkings.index') }}" @if(request()->routeIs('admin.parkings.*')) aria-current="page" @endif><x-icon name="parking" /> Gestion des parkings</a>
            @else
            <a class="sidebar-link is-current" href="{{ route('director.dashboard') }}" aria-current="page"><x-icon name="journal" /> Suivi des incidents</a>
            @endcan
            <!-- <a class="sidebar-link" href="{{ route('password.change') }}"><x-icon name="lock" /> Mon mot de passe</a> -->
        </nav>
        <div class="sidebar-bottom"><x-icon name="shield" /><span>{{ auth()->user()->roleLabel() }}<br><small>CGPark · Plateforme interne</small></span></div>
    </aside>
    <div class="admin-workspace">
        <header class="admin-topbar">
            <div class="d-flex align-items-center gap-3"><button type="button" class="menu-toggle" aria-label="Ouvrir la navigation" aria-controls="admin-navigation" aria-expanded="false" data-menu-toggle hidden><x-icon name="menu" /></button><span class="topbar-context"><span aria-hidden="true"></span> <strong>{{ request()->routeIs('admin.parkings.*') ? 'Gestion des parkings' : (auth()->user()->isAdministrator() ? 'Comptes utilisateurs' : 'Suivi des déclarations') }}</strong></span></div>
            <div class="topbar-user"><span class="admin-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->prenom, 0, 1).mb_substr(auth()->user()->nom, 0, 1)) }}</span><span class="topbar-user-name">{{ auth()->user()->full_name }}<small>{{ auth()->user()->roleLabel() }}</small></span><form action="{{ route('logout') }}" method="post">@csrf<button class="icon-button" type="submit" aria-label="Se déconnecter" title="Se déconnecter"><x-icon name="logout" /></button></form></div>
        </header>
        <main class="admin-main" id="main" tabindex="-1">
            @yield('content')
        </main>
    </div>
</body>
</html>
