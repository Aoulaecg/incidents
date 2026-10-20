@extends('layouts.auth')
@section('title', 'Accès non autorisé — CGPark')
@section('content')
    <div class="intro text-center"><p class="eyebrow">ACCÈS PROTÉGÉ</p><h1 id="page-title">Accès non autorisé</h1><p class="intro-description">Votre compte ne dispose pas des droits nécessaires pour accéder à cette page.</p></div>
    <a class="btn btn-primary login-button w-100" href="{{ url('/') }}">Retour à mon espace</a>
@endsection
