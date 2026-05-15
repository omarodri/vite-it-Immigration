<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\CaseImportantDate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UpcomingMilestonesService
{
    public const WINDOW_DAYS_BACK    = 30;
    public const WINDOW_DAYS_FORWARD = 30;
    public const LIMIT               = 10;

    public function getUpcoming(User $user): Collection
    {
        $today = Carbon::today();
        $from  = $today->copy()->subDays(self::WINDOW_DAYS_BACK)->toDateString();
        $to    = $today->copy()->addDays(self::WINDOW_DAYS_FORWARD)->toDateString();

        return CaseImportantDate::query()
            ->select([
                'case_important_dates.id',
                'case_important_dates.case_id',
                'case_important_dates.label',
                'case_important_dates.due_date',
                'case_important_dates.sort_order',
            ])
            ->join('cases', 'case_important_dates.case_id', '=', 'cases.id')
            ->where('cases.tenant_id', $user->tenant_id)
            ->whereNull('cases.deleted_at')
            ->whereIn('cases.status', ['active', 'inactive'])
            ->whereNotNull('case_important_dates.due_date')
            ->whereBetween('case_important_dates.due_date', [$from, $to])
            ->with([
                'immigrationCase:id,case_number,client_id',
                'immigrationCase.client:id,first_name,last_name',
            ])
            ->orderBy('case_important_dates.due_date', 'asc')
            ->orderBy('case_important_dates.sort_order', 'asc')
            ->orderBy('case_important_dates.id', 'asc')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (CaseImportantDate $m) => $this->serialize($m, $today));
    }

    private function serialize(CaseImportantDate $m, Carbon $today): array
    {
        $dueDate  = $m->due_date;
        $daysDiff = (int) $today->diffInDays($dueDate, false);

        return [
            'id'             => $m->id,
            'case_id'        => $m->case_id,
            'case_number'    => $m->immigrationCase?->case_number,
            'client_name'    => $m->immigrationCase?->client?->full_name,
            'label'          => $m->label,
            'due_date'       => $dueDate?->format('Y-m-d'),
            'days_diff'      => $daysDiff,
            'urgency_bucket' => $this->urgencyBucket($daysDiff),
        ];
    }

    private function urgencyBucket(int $daysDiff): string
    {
        if ($daysDiff < -7)  return 'overdue_critical';
        if ($daysDiff < 0)   return 'overdue_recent';
        if ($daysDiff === 0) return 'today';
        if ($daysDiff <= 7)  return 'upcoming_week';
        return 'upcoming_month';
    }
}
