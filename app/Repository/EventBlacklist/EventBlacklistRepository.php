<?php

namespace App\Repository\EventBlacklist;

use App\DTOs\EventBlacklist\DTOsEventBlacklist;
use App\Interfaces\EventBlacklist\IEventBlacklistRepository;
use App\Models\EventBlacklistedIdentification;
use App\Models\Purchase;

class EventBlacklistRepository implements IEventBlacklistRepository
{
    public function create(DTOsEventBlacklist $data): EventBlacklistedIdentification
    {
        return EventBlacklistedIdentification::create($data->toArray());
    }

    public function delete(int $eventId, string $identificacion): bool
    {
        $normalized = Purchase::normalizeIdentificacion($identificacion);

        return EventBlacklistedIdentification::where('event_id', $eventId)
            ->where('identificacion', $normalized)
            ->delete() > 0;
    }

    public function getByEvent(int $eventId)
    {
        return EventBlacklistedIdentification::where('event_id', $eventId)
            ->with(['addedBy:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function exists(int $eventId, string $identificacion): bool
    {
        $normalized = Purchase::normalizeIdentificacion($identificacion);

        return EventBlacklistedIdentification::where('event_id', $eventId)
            ->where('identificacion', $normalized)
            ->exists();
    }
}
