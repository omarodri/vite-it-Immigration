<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService implements CalendarProviderInterface
{
    private const BASE_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    public function createEvent(string $accessToken, Event $event): ?array
    {
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->post(self::BASE_URL, $this->toGoogleEvent($event));

        if (!$response->successful()) {
            Log::error('Google Calendar createEvent failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        return ['id' => $data['id'], 'etag' => $data['etag'] ?? null];
    }

    public function updateEvent(string $accessToken, string $externalId, Event $event): ?array
    {
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->put(self::BASE_URL . '/' . $externalId, $this->toGoogleEvent($event));

        if (!$response->successful()) {
            Log::error('Google Calendar updateEvent failed', [
                'status' => $response->status(),
                'external_id' => $externalId,
            ]);
            return null;
        }

        $data = $response->json();
        return ['id' => $data['id'], 'etag' => $data['etag'] ?? null];
    }

    public function deleteEvent(string $accessToken, string $externalId): bool
    {
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->delete(self::BASE_URL . '/' . $externalId);

        return $response->successful() || $response->status() === 404;
    }

    public function listEvents(string $accessToken, \DateTimeInterface $since, ?\DateTimeInterface $until = null): array
    {
        $events = [];
        $pageToken = null;

        do {
            $params = [
                'updatedMin' => $since->format(\DateTimeInterface::RFC3339),
                'singleEvents' => 'true',
                'maxResults' => 250,
                'showDeleted' => 'true',
            ];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->get(self::BASE_URL, $params);

            if (!$response->successful()) {
                throw new \RuntimeException("Google Calendar API error: {$response->status()}");
            }

            $data = $response->json();

            foreach ($data['items'] ?? [] as $item) {
                $events[] = $this->normalizeEvent($item);
            }

            $pageToken = $data['nextPageToken'] ?? null;
        } while ($pageToken);

        return $events;
    }

    private function toGoogleEvent(Event $event): array
    {
        if ($event->all_day) {
            return [
                'summary' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'start' => ['date' => $event->start_date->toDateString()],
                'end' => ['date' => $event->end_date->addDay()->toDateString()],
            ];
        }

        return [
            'summary' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'start' => [
                'dateTime' => $event->start_date->toIso8601String(),
                'timeZone' => config('app.timezone', 'UTC'),
            ],
            'end' => [
                'dateTime' => $event->end_date->toIso8601String(),
                'timeZone' => config('app.timezone', 'UTC'),
            ],
        ];
    }

    private function normalizeEvent(array $googleEvent): array
    {
        $isAllDay = isset($googleEvent['start']['date']);

        return [
            'external_id' => $googleEvent['id'],
            'title' => $googleEvent['summary'] ?? '(Sin titulo)',
            'description' => $googleEvent['description'] ?? null,
            'location' => $googleEvent['location'] ?? null,
            'start_date' => $isAllDay
                ? Carbon::parse($googleEvent['start']['date'])->startOfDay()
                : Carbon::parse($googleEvent['start']['dateTime']),
            'end_date' => $isAllDay
                ? Carbon::parse($googleEvent['end']['date'])->subDay()->startOfDay()
                : Carbon::parse($googleEvent['end']['dateTime']),
            'all_day' => $isAllDay,
            'etag' => $googleEvent['etag'] ?? null,
            'status' => $googleEvent['status'] ?? 'confirmed',
            'sync_source' => 'google',
        ];
    }
}
