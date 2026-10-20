<?php

namespace App\Jobs;

use App\Mail\DeclarationRecorded;
use App\Models\DeclarationNotification;
use App\Services\DeclarationNotificationService;
use App\Services\MicrosoftGraphMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDeclarationNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 60;
    public bool $failOnTimeout = true;

    public function __construct(public int $notificationId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(
        DeclarationNotificationService $service,
        MicrosoftGraphMailService $graph
    ): void {
        $notification = DB::transaction(function () {
            $notification = DeclarationNotification::whereKey($this->notificationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($notification->status === DeclarationNotification::SENT) {
                return null;
            }

            $notification->attempts = ($notification->attempts ?? 0) + 1;
            $notification->save();

            return $notification;
        });

        if ($notification === null) {
            return;
        }

        try {
            $directors = $service->directors();

            $emails = $directors
                ->pluck('email')
                ->unique()
                ->values()
                ->all();

            $mail = new DeclarationRecorded($notification);
            $subject = $mail->envelope()->subject;
            $htmlBody = $mail->render();

            // Un seul email adressé aux directeurs actifs.
            $graph->send($emails, $subject, $htmlBody);

            DeclarationNotification::whereKey($notification->id)
                ->update([
                    'status' => DeclarationNotification::SENT,
                    'sent_at' => now('Africa/Casablanca'),
                    'last_error' => null,
                ]);

        } catch (Throwable $exception) {
            DeclarationNotification::whereKey($notification->id)
                ->update([
                    'status' => DeclarationNotification::FAILED,
                    'last_error' => 'delivery_failed',
                ]);

            Log::error('declaration_notification_delivery_failed', [
                'notification_id' => $notification->id,
                'attempt' => $notification->attempts,
                'error_type' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        DeclarationNotification::whereKey($this->notificationId)
            ->where('status', '!=', DeclarationNotification::SENT)
            ->update([
                'status' => DeclarationNotification::FAILED,
                'last_error' => 'queue_attempts_exhausted',
                'queued_at' => null,
            ]);

        Log::error('declaration_notification_exhausted', [
            'notification_id' => $this->notificationId,
        ]);
    }
}