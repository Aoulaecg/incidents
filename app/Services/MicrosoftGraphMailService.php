<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class MicrosoftGraphMailService
{
    public function send(
        string|array $to,
        string $subject,
        string $htmlBody
    ): void {
        $tenantId = config('services.microsoft_graph.tenant_id');
        $clientId = config('services.microsoft_graph.client_id');
        $clientSecret = config('services.microsoft_graph.client_secret');
        $from = config('services.microsoft_graph.from_email');

        if (!$tenantId || !$clientId || !$clientSecret || !$from) {
            throw new RuntimeException(
                'Configuration Microsoft Graph incomplète.'
            );
        }

        // Accepter une adresse ou plusieurs adresses
        $emails = is_array($to) ? $to : [$to];

        $emails = array_values(array_unique($emails));

        if (empty($emails)) {
            throw new InvalidArgumentException('Aucun destinataire.');
        }

        foreach ($emails as $email) {
            if (!is_string($email) ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException(
                    'Adresse email destinataire invalide.'
                );
            }
        }

        $recipients = array_map(
            fn ($email) => [
                'emailAddress' => ['address' => $email],
            ],
            $emails
        );

        // Obtenir le jeton OAuth2
        $tokenResponse = Http::asForm()
            ->timeout(20)
            ->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]
            );

        $tokenResponse->throw();

        $accessToken = $tokenResponse->json('access_token');

        if (!$accessToken) {
            throw new RuntimeException(
                'Jeton Microsoft Graph introuvable.'
            );
        }

        // Envoyer un email à tous les destinataires
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->post(
                'https://graph.microsoft.com/v1.0/users/'
                . rawurlencode($from)
                . '/sendMail',
                [
                    'message' => [
                        'subject' => $subject,
                        'body' => [
                            'contentType' => 'HTML',
                            'content' => $htmlBody,
                        ],
                        'toRecipients' => $recipients,
                    ],
                    'saveToSentItems' => true,
                ]
            );

        $response->throw();
    }
}