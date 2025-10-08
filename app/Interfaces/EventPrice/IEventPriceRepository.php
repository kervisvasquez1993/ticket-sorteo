<?php

namespace App\Interfaces\EventPrice;

use App\DTOs\EventPrice\DTOsEventPrice;
use App\DTOs\EventPrice\DTOsEventPriceFilter;
use App\Models\EventPrice;

interface IEventPriceRepository
{
    public function getAllEventPrices(?DTOsEventPriceFilter $filters = null);
    public function getEventPriceById($id): EventPrice;
    public function createEventPrice(DTOsEventPrice $data): EventPrice;
    public function updateEventPrice(DTOsEventPrice $data, EventPrice $EventPrice): EventPrice;
    public function deleteEventPrice(EventPrice $EventPrice): EventPrice;
    public function countEventPrices(int $eventId): int;
    public function removeDefaultFromEvent(int $eventId, ?int $exceptId = null): void;
    public function assignDefaultToFirstActive(int $eventId): void;
}
