@extends('layouts.admin')
@section('title', 'Historique des notifications')
@section('content')
    <div class="admin-page-heading"><div><h1>Historique des notifications</h1><p>« Envoyé » indique la prise en charge par le service de messagerie, sans confirmation de lecture.</p></div><a href="{{ route('director.dashboard') }}" class="btn btn-outline-primary">Consulter le journal</a></div>
    <section class="admin-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Déclaration</th><th>Parking</th><th>Destinataire</th><th>Type</th><th>Statut</th><th>Tentatives</th><th>Envoi (Casablanca)</th></tr></thead>
                <tbody>
                    @forelse($notifications as $notification)
                        <tr>
                            <td>#{{ $notification->incident_id }}</td>
                            <td>{{ $notification->details['parking'] }}<br><small>{{ $notification->details['date'] }}</small></td>
                            <td>{{ $notification->recipient_email ?? 'Directeur à identifier' }}</td>
                            <td>{{ $notification->type === \App\Models\Incident::INCIDENT ? 'Incident signalé' : 'Rien à signaler' }}</td>
                            <td><span class="badge {{ match($notification->status) { 'sent' => 'text-bg-success', 'failed' => 'text-bg-danger', default => 'text-bg-secondary' } }}">{{ $notification->statusLabel() }}</span></td>
                            <td>{{ $notification->attempts }}</td>
                            <td>{{ $notification->sent_at?->timezone('Africa/Casablanca')->format('d/m/Y H:i:s') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucune notification enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="accounts-pagination">{{ $notifications->links() }}</div>
    </section>
@endsection
