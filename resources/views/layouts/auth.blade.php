<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0094B8">
    <meta name="description" content="@yield('description', 'Journal d’Incidents CGPark — espace de connexion des superviseurs.')">
    <title>@yield('title', "Connexion — Journal d’Incidents CGPark")</title>
    <link rel="icon" type="image/png" href="{{ asset('images/cgpark-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <header class="page-header d-flex align-items-center justify-content-between">
        <a class="app-brand d-flex align-items-center gap-3" href="@yield('login-home', url('/'))" aria-label="Journal d’Incidents, accueil">
            <span class="app-mark"><x-icon name="journal" /></span>
            <span>Journal d’Incidents<span class="brand-description">Compagnie Générale des Parkings</span></span>
        </a>
        <span class="internal-badge"><span class="status-dot"></span> Espace interne</span>
    </header>
    <main id="main" class="auth-main" tabindex="-1">
        <div class="auth-content">
            <section class="auth-card" aria-labelledby="page-title">
                <div class="brand-block text-center">
                    <img class="company-logo" src="{{ asset('images/cgpark-logo.png') }}" alt="Compagnie Générale des Parkings — Groupe CDG" width="295" height="164">
                </div>
                @yield('content')
            </section>
            <p class="security-note d-flex align-items-center justify-content-center gap-2"><x-icon name="shield" /> @yield('security-note', 'Accès réservé aux superviseurs autorisés')</p>
        </div>
    </main>
    <!-- <footer class="page-footer d-flex flex-wrap justify-content-between gap-2">
        <span>© {{ date('Y') }} CGPark. Tous droits réservés.</span>
        <span>Journal d’Incidents <span class="footer-separator" aria-hidden="true">·</span> Plateforme interne</span>
    </footer> -->
</body>
</html>
