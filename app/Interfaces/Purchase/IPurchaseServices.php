<?php

namespace App\Interfaces\Event;

use App\DTOs\Event\DTOsEvent;

interface IEventServices
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
    public function getEventById($id);

    /**
     * Crear un evento
     */
    public function createEvent(DTOsEvent $data);

    /**
     * Actualizar evento (sin imagen)
     */
    public function updateEvent(DTOsEvent $data, $id);

    /**
     * Eliminar evento
     */
    public function deleteEvent($id);

    // ====================================================================
    // MÉTODOS DE GESTIÓN DE IMÁGENES
    // ====================================================================

    /**
     * Actualizar solo la imagen del evento
     *
     * @param \Illuminate\Http\Request $request Request con archivo 'image'
     * @param string $id ID del evento
     * @return array ['success' => bool, 'data' => array|null, 'message' => string]
     */
    public function updateEventImage($request, string $id): array;

    /**
     * Eliminar imagen del evento
     *
     * @param string $id ID del evento
     * @return array ['success' => bool, 'data' => array|null, 'message' => string]
     */
    public function deleteEventImage(string $id): array;

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
     * Obtener estadísticas del evento
     */
    public function getEventStatistics(int $eventId);
}
