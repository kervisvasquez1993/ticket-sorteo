<?php

namespace App\Interfaces\EventPrice;

use App\DTOs\EventPrice\DTOsEventPrice;

interface IEventPriceServices 
{
    public function getAllEventPrices();
    public function getEventPriceById($id);
    public function createEventPrice(DTOsEventPrice $data);
    public function updateEventPrice(DTOsEventPrice $data, $id);
    public function deleteEventPrice($id);
}
