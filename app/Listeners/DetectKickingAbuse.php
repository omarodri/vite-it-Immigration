<?php

namespace App\Listeners;

use App\Events\SessionRevoked;
use App\Models\SessionRevocation;
use Illuminate\Contracts\Queue\ShouldQueue;

class DetectKickingAbuse implements ShouldQueue
{
    public string $queue = 'high';

    private const WINDOW_MINUTES = 10;
    private const KICK_THRESHOLD = 5;
    private const LOCK_MINUTES   = 30;

    public function handle(SessionRevoked $event): void
    {
        $windowStart = now()->subMinutes(self::WINDOW_MINUTES);

        $recentKicks = SessionRevocation::where('victim_user_id', $event->victim->id)
            ->where('created_at', '>=', $windowStart)
            ->count();

        if ($recentKicks >= self::KICK_THRESHOLD) {
            $lockedUntil = now()->addMinutes(self::LOCK_MINUTES);

            $event->victim->forceFill([
                'security_locked_until' => $lockedUntil,
            ])->saveQuietly();

            activity('security')
                ->causedBy($event->victim)
                ->performedOn($event->victim)
                ->withProperties([
                    'reason'          => 'kicking_abuse_detected',
                    'kicks_in_window' => $recentKicks,
                    'locked_until'    => $lockedUntil->toIso8601String(),
                    'revoking_ip'     => $event->revokingIp,
                ])
                ->log('account_security_locked');

            logger()->warning(
                "KickingAbuse: user {$event->victim->id} locked until {$lockedUntil} " .
                "after {$recentKicks} kicks in " . self::WINDOW_MINUTES . " min from IP {$event->revokingIp}"
            );
        }
    }
}
