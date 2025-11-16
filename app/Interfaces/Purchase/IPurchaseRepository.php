<?php

namespace App\Interfaces\Event;

use App\DTOs\Event\DTOsEvent;
use App\Models\Event;

interface IEventRepository
{
    // ====================================================================
    // MÉTODOS BÁSICOS CRUD
    // ====================================================================

    /**
     * Obtener todos los eventos
     */
    public function getAllEvents();

    /**
     * Obtener evento por ID
     */
    public function getEventById($id): Event;

    /**
     * Crear un evento
     */
    public function createEvent(DTOsEvent $data): Event;

    /**
     * Actualizar evento (sin imagen)
     */
    public function updateEvent(DTOsEvent $data, Event $event): Event;

    /**
     * Eliminar evento
     */
    public function deleteEvent(Event $event): Event;

    // ====================================================================
    // MÉTODOS DE GESTIÓN DE IMÁGENES
    // ====================================================================

    /**
     * Actualizar solo la imagen del evento
     *
     * @param Event $event
     * @param string $imageUrl
     * @return Event
     */
    public function updateEventImage(Event $event, string $imageUrl): Event;

    /**
     * Eliminar imagen del evento (set null)
     *
     * @param Event $event
     * @return Event
     */
    public function removeEventImage(Event $event): Event;

    // ====================================================================
    // MÉTODOS DE CONSULTA
    // ====================================================================

    /**
     * Obtener eventos activos
     */
    public function getActiveEvents();

    /**
     * Obtener eventos por estado
     */
    public function getEventsByStatus(string $status);

    /**
     * Obtener eventos con paginación
     */
    public function getPaginatedEvents(int $perPage = 15);

    /**
     * Verificar si un evento tiene compras
     */
    public function hasEventPurchases(int $eventId): bool;

    /**
     * Obtener estadísticas del evento
     */
    public function getEventStatistics(int $eventId): array;
}
