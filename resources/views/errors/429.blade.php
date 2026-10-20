@extends('layouts.auth')
@section('title', 'Trop de tentatives — CGPark')
@section('content')
    <div class="intro text-center"><p class="eyebrow">SÉCURITÉ DU COMPTE</p><h1 id="page-title">Trop de tentatives</h1><p class="intro-description">Veuillez patienter une minute avant de réessayer.</p></div>
    <a class="btn btn-primary login-button w-100" href="{{ url('/') }}">Revenir à mon espace</a>
@endsection
