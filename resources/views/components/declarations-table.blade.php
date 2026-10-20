@props(['declarations', 'showDate' => false])
<div class="accounts-table-wrapper"><table class="table accounts-table declaration-table mb-0"><thead><tr>@if($showDate)<th scope="col">Date</th>@endif<th scope="col">Heure</th><th scope="col">Superviseur</th><th scope="col">Parking</th><th scope="col">Type de déclaration</th><th scope="col">Description</th></tr></thead><tbody>
    @forelse($declarations as $declaration)
        <tr class="{{ $declaration->status === 'superseded' ? 'superseded-row' : '' }}">
            @if($showDate)<td data-label="Date">{{ $declaration->date_incident->format('d/m/Y') }}</td>@endif
            <td data-label="Heure">{{ $declaration->displayTime() }}@if($declaration->type_declaration === 'rien_a_signaler')<small class="declaration-meta">Validation</small>@endif</td>
            <td data-label="Superviseur"><strong class="account-name">{{ $declaration->user->full_name }}</strong></td><td data-label="Parking">{{ $declaration->parking->nom }}</td>
            <td data-label="Déclaration"><span class="declaration-badge {{ $declaration->type_declaration === 'incident' ? 'badge-incident' : 'badge-ras' }}"><x-icon :name="$declaration->type_declaration === 'incident' ? 'alert' : 'check'" />{{ $declaration->type_declaration === 'incident' ? 'Incident signalé' : 'Rien à signaler' }}</span></td>
            <td data-label="Description" class="declaration-description">{{ $declaration->description ?? '—' }}
                @if($declaration->status === 'superseded')<small class="declaration-meta cancellation-note">Confirmation annulée le {{ $declaration->superseded_at->format('d/m/Y à H:i') }} après l’incident #{{ $declaration->superseded_by_id }}. Historique conservé.</small>@endif
                @if(!$showDate && $declaration->date_incident->toDateString() !== now('Africa/Casablanca')->toDateString())<small class="declaration-meta">Événement du {{ $declaration->date_incident->format('d/m/Y') }}</small>@endif
            </td>
        </tr>
    @empty
        <tr><td colspan="{{ $showDate ? 6 : 5 }}" class="empty-accounts"><x-icon name="journal" /><strong>{{ $showDate ? 'Aucune déclaration pour ces filtres.' : 'Aucune déclaration enregistrée aujourd’hui.' }}</strong><p>{{ $showDate ? 'Modifiez la date, le parking ou votre recherche.' : 'Vos déclarations et celles des superviseurs de vos parkings apparaîtront ici.' }}</p></td></tr>
    @endforelse
</tbody></table></div>
