<?php

namespace App\Interfaces\EventPrize;

use App\DTOs\EventPrize\DTOsEventPrize;
use App\Models\EventPrize;

interface IEventPrizeRepository
{
    public function getAllEventPrizes();
    public function getEventPrizeById($id): EventPrize;
    public function getEventPrizesByEventId($eventId);
    public function getMainPrizeByEventId($eventId): ?EventPrize;
    public function createEventPrize(DTOsEventPrize $data): EventPrize;
    public function updateEventPrize(DTOsEventPrize $data, EventPrize $eventPrize): EventPrize;
    public function deleteEventPrize(EventPrize $eventPrize): EventPrize;
    public function removeAllMainPrizesFromEvent($eventId): void;
    public function getAllMainPrizes();
}
