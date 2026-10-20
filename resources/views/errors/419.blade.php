@extends('layouts.auth')
@section('title', 'Session expirée — CGPark')
@section('content')
    <div class="intro text-center"><p class="eyebrow">SÉCURITÉ DE LA SESSION</p><h1 id="page-title">La session a expiré</h1><p class="intro-description">Rechargez la page puis réessayez. Vos modifications n’ont pas été enregistrées.</p></div>
    <a class="btn btn-primary login-button w-100" href="{{ url('/') }}">Revenir à mon espace</a>
@endsection
