<?php

namespace App\Repository\EventPrize;

use App\DTOs\EventPrize\DTOsEventPrize;
use App\Interfaces\EventPrize\IEventPrizeRepository;
use App\Models\EventPrize;

class EventPrizeRepository implements IEventPrizeRepository
{
    public function getAllEventPrizes()
    {
        return EventPrize::with('event')->get();
    }

    public function getEventPrizeById($id): EventPrize
    {
        $eventPrize = EventPrize::with('event')->find($id);

        if (!$eventPrize) {
            throw new \Exception("No results found for EventPrize with ID {$id}");
        }

        return $eventPrize;
    }

    public function getEventPrizesByEventId($eventId)
    {
        return EventPrize::where('event_id', $eventId)
            ->orderBy('is_main', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getMainPrizeByEventId($eventId): ?EventPrize
    {
        return EventPrize::where('event_id', $eventId)
            ->where('is_main', true)
            ->first();
    }

    public function createEventPrize(DTOsEventPrize $data): EventPrize
    {
        // Si se marca como principal, remover otros principales del mismo evento
        if ($data->isMain()) {
            $this->removeAllMainPrizesFromEvent($data->getEventId());
        }

        $result = EventPrize::create($data->toArray());
        return $result;
    }

    public function updateEventPrize(DTOsEventPrize $data, EventPrize $eventPrize): EventPrize
    {
        // Si se marca como principal, remover otros principales del mismo evento
        if ($data->isMain() && !$eventPrize->is_main) {
            $this->removeAllMainPrizesFromEvent($data->getEventId());
        }

        $eventPrize->update($data->toArray());
        return $eventPrize->fresh();
    }

    public function deleteEventPrize(EventPrize $eventPrize): EventPrize
    {
        $eventPrize->delete();
        return $eventPrize;
    }

    /**
     * Remover el flag is_main de todos los premios de un evento
     */
    public function removeAllMainPrizesFromEvent($eventId): void
    {
        EventPrize::where('event_id', $eventId)
            ->where('is_main', true)
            ->update(['is_main' => false]);
    }
}
