<?php

namespace App\Console\Commands;

use App\Models\DeclarationNotification;
use App\Services\DeclarationNotificationService;
use Illuminate\Console\Command;

class DispatchDeclarationNotifications extends Command
{
    protected $signature = 'notifications:dispatch {--retry-failed : Relancer aussi les notifications en échec}';

    protected $description = 'Remettre en file les notifications de déclaration non envoyées';

    public function handle(DeclarationNotificationService $service): int
    {
        $retry = (bool) $this->option('retry-failed');
        $count = 0;
        DeclarationNotification::whereIn('status', $retry
            ? [DeclarationNotification::PENDING, DeclarationNotification::FAILED] : [DeclarationNotification::PENDING])
            ->where(fn ($query) => $query->whereNull('queued_at')->orWhere('queued_at', '<=', now()->subMinutes(15)))
            ->chunkById(100, function ($notifications) use ($service, $retry, &$count) {
                foreach ($notifications as $notification) {
                    $count += (int) $service->enqueue($notification->id, $retry);
                }
            });
        $this->info($count.' notification(s) mise(s) en file.');

        return self::SUCCESS;
    }
}
