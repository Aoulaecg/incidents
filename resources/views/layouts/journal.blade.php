<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#0094B8"><title>Déclaration quotidienne — Journal d’Incidents CGPark</title><link rel="icon" href="{{ asset('images/cgpark-logo.png') }}">@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="admin-page supervisor-page">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <header class="journal-header"><a href="{{ route('dashboard') }}"><img src="{{ asset('images/cgpark-logo.png') }}" alt="Compagnie Générale des Parkings — Groupe CDG" width="295" height="164"></a><span class="journal-date"><x-icon name="calendar" /> {{ now('Africa/Casablanca')->format('d/m/Y') }}</span><div class="journal-user"><span><strong>{{ auth()->user()->full_name }}</strong><small>Superviseur</small></span><form action="{{ route('logout') }}" method="post">@csrf<button class="icon-button" type="submit" title="Déconnexion" aria-label="Se déconnecter"><x-icon name="logout" /></button></form></div></header>
    <main class="journal-main" id="main" tabindex="-1">@yield('content')</main>
</body></html>
