<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CalendarSyncStatus;
use App\Models\OauthToken;
use App\Models\User;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullCalendarEventsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function handle(CalendarSyncService $syncService): void
    {
        $this->provisionMicrosoftUsers();

        $activeConnections = CalendarSyncStatus::where('status', 'active')
            ->with('user')
            ->get();

        foreach ($activeConnections as $syncStatus) {
            try {
                if ($syncStatus->user) {
                    $syncService->pullEvents($syncStatus->user);
                }
            } catch (\Exception $e) {
                Log::error('Pull failed for user', [
                    'user_id'  => $syncStatus->user_id,
                    'provider' => $syncStatus->provider,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Auto-create CalendarSyncStatus(microsoft, active) for every user in tenants
     * that have a Microsoft storage token. This allows calendar sync to work
     * automatically via application permissions without per-user OAuth.
     * Users who previously disconnected (status='paused') are not re-provisioned.
     */
    private function provisionMicrosoftUsers(): void
    {
        $tenantIds = OauthToken::where('provider', 'microsoft')
            ->where(fn ($q) => $q->where('purpose', 'storage')->orWhereNull('purpose'))
            ->pluck('tenant_id')
            ->unique();

        foreach ($tenantIds as $tenantId) {
            User::where('tenant_id', $tenantId)
                ->whereDoesntHave('calendarSyncStatus', fn ($q) => $q->where('provider', 'microsoft'))
                ->each(function (User $user) {
                    CalendarSyncStatus::create([
                        'tenant_id'   => $user->tenant_id,
                        'user_id'     => $user->id,
                        'provider'    => 'microsoft',
                        'status'      => 'active',
                        'error_count' => 0,
                    ]);
                });
        }
    }
}
