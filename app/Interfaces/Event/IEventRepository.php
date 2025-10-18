<?php

namespace App\Interfaces\Event;

use App\DTOs\Event\DTOsEvent;
use App\Models\Event;

interface IEventRepository
{
    public function getAllEvents();
    public function getActiveEvents();
    public function getEventById($id): Event;
    public function getEventWithParticipants($id): Event;
    public function createEvent(DTOsEvent $data): Event;
    public function updateEvent(DTOsEvent $data, Event $Event): Event;
    public function deleteEvent(Event $Event): Event;
    public function getAvailableNumbers(Event $event): array;
    public function selectWinner(Event $event, int $winnerNumber): ?object;
}
