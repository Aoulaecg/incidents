@extends('layouts.admin')
@section('title', 'Confirmer la désactivation')
@section('content')
    <a class="back-link" href="{{ route('admin.supervisors.index') }}"><x-icon name="arrow" /> Retour aux comptes</a>
    <div class="admin-page-heading"><div><p class="eyebrow">GESTION DES ACCÈS</p><h1>Confirmer la désactivation</h1><p>Vérifiez le compte concerné avant de continuer.</p></div></div>
    <div class="admin-panel admin-form-panel compact-form">
        <x-feedback />
        <div class="confirmation-icon"><x-icon name="pause" /></div>
        <h2 class="confirmation-title">Désactiver {{ $supervisor->full_name }} ?</h2>
        <p class="account-confirm-email">{{ $supervisor->email }}</p>
        <p class="confirmation-description">Ce compte ne pourra plus se connecter et ses sessions seront fermées. Ses informations et son historique seront conservés. Vous pourrez le réactiver à tout moment.</p>
        @if($supervisor->id === auth()->id())<p class="field-error">Vous ne pouvez pas désactiver votre propre compte.</p>@endif
        <form action="{{ route('admin.supervisors.status', $supervisor) }}" method="post">@csrf @method('PATCH')<input type="hidden" name="active" value="0"><input type="hidden" name="confirm_deactivation" value="1"><div class="form-actions"><a class="btn btn-light" href="{{ route('admin.supervisors.index') }}">Annuler</a><button class="btn btn-danger" type="submit" @disabled($supervisor->id === auth()->id())>Confirmer la désactivation</button></div></form>
    </div>
@endsection
