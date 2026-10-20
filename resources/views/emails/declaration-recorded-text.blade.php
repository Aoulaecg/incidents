Bonjour Monsieur,

@if($isIncident)
Un nouvel incident vient d’être enregistré dans le Journal d’Incidents CGPark.
@else
Le superviseur a confirmé qu’aucun incident n’est à signaler pour le parking suivant :
@endif

Parking : {!! $details['parking'] !!}
Superviseur : {!! $details['supervisor'] !!}
Date : {!! $details['date'] !!}
@if($isIncident)
Heure : {!! $details['time'] !!}
Description : {!! $details['description'] !!}
@else
Statut : Rien à signaler
Cette déclaration a été enregistrée dans le Journal d’Incidents CGPark.
@endif

Consulter le journal (espace Directeur après authentification) :
{!! $journalUrl !!}

Cordialement,
Journal d’Incidents CGPark
Notification automatique
