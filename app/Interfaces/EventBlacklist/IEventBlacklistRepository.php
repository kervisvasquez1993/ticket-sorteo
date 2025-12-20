<?php

namespace App\Interfaces\EventBlacklist;

use App\DTOs\EventBlacklist\DTOsEventBlacklist;
use App\Models\EventBlacklist;
use App\Models\EventBlacklistedIdentification;

interface IEventBlacklistRepository
{
    public function create(DTOsEventBlacklist $data): EventBlacklistedIdentification;
    public function delete(int $eventId, string $identificacion): bool;
    public function getByEvent(int $eventId);
    public function exists(int $eventId, string $identificacion): bool;
}
