<?php

namespace App\Mail;

use App\Models\DeclarationNotification;
use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeclarationRecorded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DeclarationNotification $notification) {}

    public function envelope(): Envelope
    {
        $details = $this->notification->details;

        return new Envelope(subject: $this->notification->type === Incident::INCIDENT
            ? ' Nouvel incident signalé – '.$details['parking']
            : ' Rien à signaler – '.$details['parking'].' – '.$details['date']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.declaration-recorded', text: 'emails.declaration-recorded-text', with: [
            'details' => $this->notification->details,
            'isIncident' => $this->notification->type === Incident::INCIDENT,
            'journalUrl' => route('director.dashboard', [
                'parking_id' => $this->notification->incident->parking_id,
                'date' => $this->notification->incident->date_incident->toDateString(),
            ]),
        ]);
    }
}
