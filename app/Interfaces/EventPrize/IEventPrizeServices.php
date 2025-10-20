<?php

namespace App\Interfaces\EventPrize;

use App\DTOs\EventPrize\DTOsEventPrize;

interface IEventPrizeServices
{
    public function getAllEventPrizes();
    public function getEventPrizeById($id);
    public function getEventPrizesByEventId($eventId);
    public function getMainPrizeByEventId($eventId);
    public function createEventPrize(DTOsEventPrize $data);
    public function updateEventPrize(DTOsEventPrize $data, $id);
    public function deleteEventPrize($id);
    public function setAsMainPrize($id);
        public function getAllMainPrizes();
}
