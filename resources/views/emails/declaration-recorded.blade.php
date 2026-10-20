<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isIncident ? 'Nouvel incident signalé' : 'Rien à signaler' }} — CGPark</title>
    <style>
        @media only screen and (max-width: 600px) {
            .email-content { padding: 24px 16px !important; }
            .email-heading { font-size: 23px !important; }
            .email-label { width: 95px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#f0f5f8;color:#233b50;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0f5f8;">
    <tr><td align="center" style="padding:24px 8px;">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;">
           
            <tr><td class="email-content" style="padding:12px 36px 36px;">
                <span style="display:inline-block;padding:8px 12px;border-radius:5px;font-size:13px;font-weight:bold;background:{{ $isIncident ? '#fff0e8' : '#e7f5ed' }};color:{{ $isIncident ? '#a33d17' : '#246b43' }};">{{ $isIncident ? 'INCIDENT SIGNALÉ' : 'RIEN À SIGNALER' }}</span>
                <h1 class="email-heading" style="font-size:27px;line-height:1.25;color:#006b8b;margin:20px 0;">{{ $isIncident ? 'Nouvel incident enregistré' : 'Confirmation quotidienne' }}</h1>
                <p style="line-height:1.7;">Bonjour,</p>
                <p style="line-height:1.7;">{{ $isIncident ? 'Un nouvel incident vient d’être enregistré dans le Journal d’Incidents CGPark.' : 'Le superviseur a confirmé qu’aucun incident n’est à signaler pour le parking suivant :' }}</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f8fa;border:1px solid #dce8ed;border-left:4px solid {{ $isIncident ? '#0094b8' : '#61a844' }};border-radius:6px;table-layout:fixed;">
                    @foreach(['Parking' => $details['parking'], 'Superviseur' => $details['supervisor'], 'Date' => $details['date']] as $label => $value)
                        <tr><th class="email-label" align="left" valign="top" style="width:110px;padding:12px 10px;font-size:14px;">{{ $label }}</th><td style="padding:12px 10px;font-size:14px;overflow-wrap:anywhere;word-break:break-word;">{{ $value }}</td></tr>
                    @endforeach
                    @if($isIncident)
                        <tr><th class="email-label" align="left" valign="top" style="width:110px;padding:12px 10px;font-size:14px;">Heure</th><td style="padding:12px 10px;font-size:14px;">{{ $details['time'] }}</td></tr>
                        <tr><td colspan="2" style="padding:12px 14px;font-size:14px;"><strong>Description</strong><div style="margin-top:10px;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;line-height:1.7;">{{ $details['description'] }}</div></td></tr>
                    @else
                        <tr><th class="email-label" align="left" valign="top" style="width:110px;padding:12px 10px;font-size:14px;">Statut</th><td style="padding:12px 10px;font-size:14px;color:#246b43;">Rien à signaler</td></tr>
                    @endif
                </table>
                <p style="line-height:1.7;">{{ $isIncident ? 'Vous pouvez consulter les détails de cet incident directement sur l’application Journal d’Incidents CGPark.' : 'Cette déclaration a été enregistrée dans le Journal d’Incidents CGPark.' }}</p>
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;"><tr><td bgcolor="#007d9c" style="border-radius:6px;"><a href="{{ $journalUrl }}" style="display:inline-block;padding:14px 22px;color:#ffffff;text-decoration:none;font-weight:bold;">Consulter le journal</a></td></tr></table>
                <p style="line-height:1.7;margin-bottom:0;">Cordialement,<br><strong>Journal d’Incidents CGPark</strong><br><span style="color:#687d8d;font-size:13px;"></span></p>
            </td></tr>
           
        </table>
    </td></tr>
</table>
</body>
</html>
