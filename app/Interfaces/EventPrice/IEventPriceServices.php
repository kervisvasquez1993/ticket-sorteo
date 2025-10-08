<?php

namespace App\Interfaces\EventPrice;

use App\DTOs\EventPrice\DTOsEventPrice;
use App\DTOs\EventPrice\DTOsEventPriceFilter;

interface IEventPriceServices
{
    public function getAllEventPrices(?DTOsEventPriceFilter $filters = null);
    public function getEventPriceById($id);
    public function createEventPrice(DTOsEventPrice $data);
    public function updateEventPrice(DTOsEventPrice $data, $id);
    public function deleteEventPrice($id);
}
