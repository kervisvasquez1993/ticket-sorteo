<?php

namespace App\Interfaces\EventBlacklist;

use App\DTOs\EventBlacklist\DTOsEventBlacklist;

interface IEventBlacklistServices
{
     public function addToBlacklist(DTOsEventBlacklist $data);
    public function removeFromBlacklist(int $eventId, string $identificacion);
    public function getBlacklistByEvent(int $eventId);
}
