<?php

namespace App\Interfaces\Event;

use App\DTOs\Event\DTOsEvent;

interface IEventServices
{
    public function getAllEvents();
    public function getActiveEvents(); // Para clientes
    public function getEventById($id);
    public function getEventWithParticipants($id); // Para admin ver participantes
    public function createEvent(DTOsEvent $data);
    public function updateEvent(DTOsEvent $data, $id);
    public function deleteEvent($id);
    public function getAvailableNumbers($eventId); // Números disponibles
    public function selectWinner($eventId); // Sortear ganador
}
