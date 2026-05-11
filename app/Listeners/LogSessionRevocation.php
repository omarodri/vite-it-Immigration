<?php

namespace App\Listeners;

use App\Events\SessionRevoked;
use App\Models\SessionRevocation;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogSessionRevocation implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(SessionRevoked $event): void
    {
        SessionRevocation::create([
            'victim_user_id'      => $event->victim->id,
            'revoking_ip'         => $event->revokingIp,
            'revoking_user_agent' => $event->revokingUserAgent,
            'stop_reason'         => $event->stopReason,
        ]);

        activity('security')
            ->causedBy($event->victim)
            ->performedOn($event->victim)
            ->withProperties([
                'revoking_ip'         => $event->revokingIp,
                'revoking_user_agent' => $event->revokingUserAgent,
                'stop_reason'         => $event->stopReason,
            ])
            ->log('session_revoked');
    }
}
