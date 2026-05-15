<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarSyncStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\OAuthCredentialService;
use App\Services\OAuthTokenService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalendarSyncService
{
    public static bool $isPulling = false;

    public function __construct(
        private readonly GoogleCalendarService $googleService,
        private readonly MicrosoftCalendarService $microsoftService,
        private readonly OAuthTokenService $tokenService,
        private readonly OAuthCredentialService $credentialService,
    ) {}

    public function pushEvent(Event $event, User $user): void
    {
        $provider = $this->getConnectedProvider($user);
        if (!$provider) return;

        [$service, $token] = $this->resolveServiceAndToken($provider, $user);
        if (!$token) return;

        try {
            if ($event->external_id) {
                $result = $service->updateEvent($token, $event->external_id, $event);
            } else {
                $result = $service->createEvent($token, $event);
            }

            if ($result) {
                $event->updateQuietly([
                    'external_id'       => $result['id'],
                    'external_etag'     => $result['etag'] ?? null,
                    'last_synced_at'    => now(),
                    'synced_by_user_id' => $user->id,
                ]);

                CalendarSyncStatus::where('user_id', $user->id)
                    ->where('provider', $provider)
                    ->update(['last_push_at' => now()]);
            }
        } catch (\Exception $e) {
            Log::error('Calendar pushEvent failed', [
                'event_id' => $event->id,
                'user_id'  => $user->id,
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function deleteExternalEvent(string $externalId, User $user): void
    {
        $provider = $this->getConnectedProvider($user);
        if (!$provider) return;

        [$service, $token] = $this->resolveServiceAndToken($provider, $user);
        if (!$token) return;

        $service->deleteEvent($token, $externalId);
    }

    public function pullEvents(User $user, ?string $provider = null): void
    {
        if ($provider === null) {
            foreach ($this->getConnectedProviders($user) as $p) {
                $this->pullEvents($user, $p);
            }
            return;
        }

        $syncStatus = CalendarSyncStatus::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if (!$syncStatus || $syncStatus->status === 'error') {
            return;
        }

        [$service, $token] = $this->resolveServiceAndToken($provider, $user);
        if (!$token) {
            return;
        }

        $tenant          = $user->tenant;
        $backfillMonths  = $tenant->calendarBackfillMonths();
        $lookaheadMonths = $tenant->calendarLookaheadMonths();
        $since           = $syncStatus->last_pull_at ?? now()->subMonths($backfillMonths);
        $until           = now()->addMonths($lookaheadMonths);

        try {
            self::$isPulling = true;
            $externalEvents  = $service->listEvents($token, $since, $until);
            $this->processExternalEvents($externalEvents, $user, $provider);

            $syncStatus->update([
                'last_pull_at' => now(),
                'status'       => 'active',
                'error_count'  => 0,
                'last_error'   => null,
            ]);
        } catch (\Throwable $e) {
            $this->handlePullError($syncStatus, $e);
            throw $e;
        } finally {
            self::$isPulling = false;
        }
    }

    private function handlePullError(CalendarSyncStatus $syncStatus, \Throwable $e): void
    {
        $newCount = $syncStatus->error_count + 1;
        $syncStatus->update([
            'error_count' => $newCount,
            'last_error'  => substr($e->getMessage(), 0, 500),
            'status'      => $newCount >= 5 ? 'error' : $syncStatus->status,
        ]);

        if ($newCount >= 5) {
            \Log::warning('Calendar sync circuit breaker opened', [
                'user_id'  => $syncStatus->user_id,
                'provider' => $syncStatus->provider,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function processExternalEvents(array $events, User $user, string $provider): void
    {
        foreach ($events as $ext) {
            // Skip deleted events (Google: status='cancelled')
            if (($ext['status'] ?? 'confirmed') === 'cancelled') {
                Event::where('external_id', $ext['external_id'])
                    ->where('tenant_id', $user->tenant_id)
                    ->whereNull('deleted_at')
                    ->each(fn ($ev) => $ev->delete());
                continue;
            }

            $existing = Event::where('external_id', $ext['external_id'])
                ->where('tenant_id', $user->tenant_id)
                ->withTrashed()
                ->first();

            if ($existing) {
                // Restore if soft-deleted
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->updateQuietly([
                    'title' => $ext['title'],
                    'description' => $ext['description'],
                    'location' => $ext['location'],
                    'start_date' => $ext['start_date'],
                    'end_date' => $ext['end_date'],
                    'all_day' => $ext['all_day'],
                    'external_etag' => $ext['etag'],
                    'last_synced_at' => now(),
                    'synced_by_user_id' => $user->id,
                ]);
            } else {
                Event::create([
                    'tenant_id' => $user->tenant_id,
                    'created_by' => $user->id,
                    'assigned_to_id' => $user->id,
                    'title' => $ext['title'],
                    'description' => $ext['description'],
                    'location' => $ext['location'],
                    'start_date' => $ext['start_date'],
                    'end_date' => $ext['end_date'],
                    'all_day' => $ext['all_day'],
                    'category' => 'personal',
                    'sync_source' => $provider === 'google' ? 'google' : 'outlook',
                    'external_id' => $ext['external_id'],
                    'external_etag' => $ext['etag'],
                    'last_synced_at' => now(),
                    'synced_by_user_id' => $user->id,
                ]);
            }
        }
    }

    /**
     * Returns all providers with active or paused sync for a user.
     * Replaces the singular getConnectedProvider() for multi-provider support.
     */
    public function getConnectedProviders(User $user): \Illuminate\Support\Collection
    {
        return CalendarSyncStatus::where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->pluck('provider');
    }

    /**
     * @deprecated Use getConnectedProviders() and iterate. Kept for backward compat.
     */
    public function getConnectedProvider(User $user): ?string
    {
        return $this->getConnectedProviders($user)->first();
    }

    /**
     * Return [service, accessToken] for the given provider and user.
     * Microsoft uses application-level token + user-scoped URL.
     * Google uses the per-user delegated OAuth token.
     */
    public function resolveServiceAndToken(string $provider, User $user): array
    {
        if ($provider === 'microsoft') {
            // Application permissions (Azure AD org accounts — no per-user OAuth needed).
            $appToken = $this->tokenService->getMicrosoftApplicationToken($user->tenant_id);
            if ($appToken) {
                return [$this->microsoftService->withUser($user->email), $appToken];
            }

            // Fallback: delegated per-user OAuth (personal @outlook.com / @hotmail.com accounts).
            // Uses /me/events — token is scoped to the authorizing user, no email routing needed.
            $delegatedToken = $this->tokenService->getValidUserCalendarToken($user, 'microsoft');
            return [$this->microsoftService, $delegatedToken];
        }

        // Google: per-user delegated OAuth token
        $token = $this->tokenService->getValidUserCalendarToken($user, $provider);
        return [$this->googleService, $token];
    }

    private function getProviderService(string $provider): CalendarProviderInterface
    {
        return match ($provider) {
            'google'    => $this->googleService,
            'microsoft' => $this->microsoftService,
            default     => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }
}
