@extends('layouts.journal')
@section('content')
    <div class="admin-page-heading"><div><h1>Journal d’Incidents — Déclaration quotidienne</h1><p>Signalez un incident ou confirmez que tout va bien pour votre parking.</p></div><span class="parking-count"><x-icon name="parking" /> {{ $parkings->count() }} parking{{ $parkings->count() > 1 ? 's' : '' }} affecté{{ $parkings->count() > 1 ? 's' : '' }}</span></div>
    <x-feedback />
    @if($parkings->isEmpty())
        <div class="alert alert-info status-message">Aucun parking ne vous est actuellement affecté. Veuillez contacter votre administrateur.</div>
    @else
        @php($selectedParking = old('parking_id', session('selected_parking', $parkings->count() === 1 ? $parkings->first()->id : '')))
        <section class="admin-panel declaration-panel" aria-labelledby="declaration-title">
            <div class="form-section-heading"><span class="declaration-icon"><x-icon name="journal" /></span><div><h2 id="declaration-title">Déclarer un incident</h2><p>Choisissez votre parking, puis renseignez les faits constatés.</p></div></div>
            <form action="{{ route('supervisor.incidents.store') }}" method="post" data-declaration-form>
                @csrf <input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">
                <div class="row g-3"><div class="col-md-6"><label class="form-label" for="parking_id">Parking *</label><select class="form-select" name="parking_id" id="parking_id" required data-primary-parking><option value="" disabled @selected(! $selectedParking)>Sélectionner un parking</option>@foreach($parkings as $parking)<option value="{{ $parking->id }}" @selected((string) $selectedParking === (string) $parking->id)>{{ $parking->nom }}</option>@endforeach</select></div><div class="col-sm-6 col-md-3"><label class="form-label" for="date_incident">Date *</label><input class="form-control" type="date" id="date_incident" name="date_incident" max="{{ $today->toDateString() }}" value="{{ old('date_incident', $today->toDateString()) }}" required></div><div class="col-sm-6 col-md-3"><label class="form-label" for="heure_incident">Heure *</label><input class="form-control" type="time" name="heure_incident" id="heure_incident" value="{{ old('heure_incident', $today->format('H:i')) }}" required></div></div>
                <div class="description-field"><label class="form-label" for="description">Description de l’incident *</label><textarea class="form-control" id="description" name="description" rows="4" maxlength="5000" placeholder="Décrivez brièvement l’incident constaté..." required>{{ old('description') }}</textarea><span class="form-hint"></span></div>
                <div class="incident-submit"><span class="form-hint"></span><button type="submit" class="btn btn-primary admin-primary"><x-icon name="plus" /> Enregistrer l’incident</button></div>
            </form>
        </section>
        <section class="ras-panel" aria-labelledby="ras-title"><span class="ras-mark"><x-icon name="check" /></span><div class="ras-copy"><h2 id="ras-title">Tout va bien aujourd’hui ?</h2><p>Confirmez l’absence d’incident pour le parking sélectionné.</p></div>
            <form id="ras-form" action="{{ route('supervisor.ras.store') }}" method="post" data-declaration-form>
                @csrf <input type="hidden" name="submission_token" value="{{ $rasToken }}">
                <div data-ras-fallback><label class="form-label" for="ras_parking_id">Parking concerné</label><select class="form-select" name="parking_id" id="ras_parking_id" required>@foreach($parkings as $parking)<option value="{{ $parking->id }}" @selected((string) $selectedParking === (string) $parking->id)>{{ $parking->nom }}</option>@endforeach</select><label class="ras-fallback-confirm"><input type="checkbox" name="confirm_ras" value="1" required> Je confirme qu’aucun incident n’est à signaler aujourd’hui pour ce parking.</label></div>
                <button class="btn ras-button" type="submit" data-ras-trigger><x-icon name="check" /> Rien à signaler aujourd’hui</button>
            </form>
        </section>
        <dialog class="ras-dialog" data-ras-dialog aria-labelledby="ras-dialog-title"><div class="confirmation-icon"><x-icon name="check" /></div><h2 id="ras-dialog-title">Rien à signaler aujourd’hui</h2><strong data-ras-parking-name></strong><p>Confirmez-vous qu’aucun incident n’est à signaler aujourd’hui pour ce parking ?</p><div class="form-actions"><button class="btn btn-light" type="button" data-ras-cancel>Annuler</button><button class="btn ras-button" type="button" data-ras-confirm>Confirmer</button></div></dialog>
    @endif
    <section class="admin-panel daily-declarations"><div class="table-heading"><h2>Déclarations du jour <span class="count-badge">{{ $declarations->total() }}</span></h2><span>{{ $today->format('d/m/Y') }}</span></div><x-declarations-table :declarations="$declarations" /><div class="accounts-pagination"><span>{{ $declarations->total() }} déclaration{{ $declarations->total() > 1 ? 's' : '' }} enregistrée{{ $declarations->total() > 1 ? 's' : '' }} aujourd’hui</span>{{ $declarations->onEachSide(1)->links() }}</div></section>
@endsection
