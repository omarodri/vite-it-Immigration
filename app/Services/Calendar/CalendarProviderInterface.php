<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Event;

interface CalendarProviderInterface
{
    public function createEvent(string $accessToken, Event $event): ?array;
    public function updateEvent(string $accessToken, string $externalId, Event $event): ?array;
    public function deleteEvent(string $accessToken, string $externalId): bool;
    public function listEvents(string $accessToken, \DateTimeInterface $since, ?\DateTimeInterface $until = null): array;
}
