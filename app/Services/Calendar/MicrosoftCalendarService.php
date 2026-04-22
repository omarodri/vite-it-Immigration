<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MicrosoftCalendarService implements CalendarProviderInterface
{
    private const BASE_URL = 'https://graph.microsoft.com/v1.0/me/events';

    private ?string $userEmail = null;

    /**
     * Return a clone of this service scoped to a specific user's mailbox.
     * When set, all calls use /users/{email}/events (application permissions).
     * Without it, falls back to /me/events (delegated, for backward compat).
     */
    public function withUser(string $email): static
    {
        $instance            = clone $this;
        $instance->userEmail = $email;
        return $instance;
    }

    private function getBaseUrl(): string
    {
        if ($this->userEmail) {
            return 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($this->userEmail) . '/events';
        }
        return self::BASE_URL;
    }

    public function createEvent(string $accessToken, Event $event): ?array
    {
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->post($this->getBaseUrl(), $this->toMicrosoftEvent($event));

        if (!$response->successful()) {
            Log::error('Microsoft Calendar createEvent failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        return ['id' => $data['id'], 'etag' => $data['@odata.etag'] ?? null];
    }

    public function updateEvent(string $accessToken, string $externalId, Event $event): ?array
    {
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->patch($this->getBaseUrl() . '/' . $externalId, $this->toMicrosoftEvent($event));

        if (!$response->successful()) {
            Log::error('Microsoft Calendar updateEvent failed', [
                'status' => $response->status(),
                'external_id' => $externalId,
            ]);
            return null;
        }

        $data = $response->json();
        return ['id' => $data['id'], 'etag' => $data['@odata.etag'] ?? null];
    }

    public function deleteEvent(string $accessToken, string $externalId): bool
    {
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->delete($this->getBaseUrl() . '/' . $externalId);

        return $response->successful() || $response->status() === 404;
    }

    public function listEvents(string $accessToken, \DateTimeInterface $since): array
    {
        $events = [];
        $url = $this->getBaseUrl();

        $params = [
            '$filter' => "lastModifiedDateTime ge {$since->format('Y-m-d\TH:i:s\Z')}",
            '$top' => 50,
            '$select' => 'id,subject,body,location,start,end,isAllDay,lastModifiedDateTime,isCancelled',
            '$orderby' => 'lastModifiedDateTime desc',
        ];

        do {
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->get($url, $params);

            if (!$response->successful()) {
                throw new \RuntimeException("Microsoft Graph API error: {$response->status()}");
            }

            $data = $response->json();

            foreach ($data['value'] ?? [] as $item) {
                $events[] = $this->normalizeEvent($item);
            }

            $url = $data['@odata.nextLink'] ?? null;
            $params = [];
        } while ($url);

        return $events;
    }

    private function toMicrosoftEvent(Event $event): array
    {
        $timezone = config('app.timezone', 'UTC');

        return [
            'subject' => $event->title,
            'body' => [
                'contentType' => 'text',
                'content' => $event->description ?? '',
            ],
            'location' => [
                'displayName' => $event->location ?? '',
            ],
            'start' => [
                'dateTime' => $event->start_date->format('Y-m-d\TH:i:s'),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $event->end_date->format('Y-m-d\TH:i:s'),
                'timeZone' => $timezone,
            ],
            'isAllDay' => $event->all_day,
        ];
    }

    private function normalizeEvent(array $msEvent): array
    {
        $startTz = $msEvent['start']['timeZone'] ?? 'UTC';
        $endTz = $msEvent['end']['timeZone'] ?? 'UTC';

        return [
            'external_id' => $msEvent['id'],
            'title' => $msEvent['subject'] ?? '(Sin titulo)',
            'description' => $msEvent['body']['content'] ?? null,
            'location' => $msEvent['location']['displayName'] ?? null,
            'start_date' => Carbon::parse($msEvent['start']['dateTime'], $startTz),
            'end_date' => Carbon::parse($msEvent['end']['dateTime'], $endTz),
            'all_day' => $msEvent['isAllDay'] ?? false,
            'etag' => $msEvent['@odata.etag'] ?? null,
            'status' => ($msEvent['isCancelled'] ?? false) ? 'cancelled' : 'confirmed',
            'sync_source' => 'outlook',
        ];
    }
}
