<?php

namespace App\Interfaces\Event;

use App\DTOs\Event\DTOsEvent;

interface IEventServices
{
    public function getAllEvents();
    public function getActiveEvents();
    public function getEventById($id);
    public function getEventWithParticipants($id);
    public function createEvent(DTOsEvent $data);
    public function updateEvent(DTOsEvent $data, $id);
    public function deleteEvent($id);
    public function getAvailableNumbers($eventId);
   public function selectWinner($eventId, int $winnerNumber);
}
