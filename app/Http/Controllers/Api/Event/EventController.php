<?php

namespace App\Http\Controllers\Api\Event;

use App\DTOs\Event\DTOsEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\CreateEventRequest;
use App\Http\Requests\Event\SelectWinnerRequest;
use App\Http\Requests\Event\UpdateEventImageRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Interfaces\Event\IEventServices;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    protected IEventServices $eventServices;

    public function __construct(IEventServices $eventServicesInterface)
    {
        $this->eventServices = $eventServicesInterface;
    }

    /**
     * Display a listing of all events (Admin only)
     */
    public function index()
    {
        // Admin ve todos los eventos
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $result = $this->eventServices->getAllEvents();
        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }
        return response()->json($result['data'], 200);
    }

    /**
     * Display active events (Public/Customer)
     */
    public function active()
    {
        $result = $this->eventServices->getActiveEvents();
        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }
        return response()->json($result['data'], 200);
    }

    /**
     * Store a newly created event (Admin only)
     */
    public function store(CreateEventRequest $request)
    {
        $result = $this->eventServices->createEvent(DTOsEvent::fromRequest($request));
        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }
        return response()->json($result['data'], 201);
    }

    /**
     * Display the specified event
     */
    public function show(string $id)
    {
        if (auth()->user()->isAdmin()) {
            $result = $this->eventServices->getEventWithParticipants($id);
        } else {
            $result = $this->eventServices->getEventById($id);
        }

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }
        return response()->json($result['data'], 200);
    }

    /**
     * Get available numbers for an event
     */
    public function availableNumbers(string $id)
    {
        $result = $this->eventServices->getAvailableNumbers($id);
        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }
        return response()->json($result['data'], 200);
    }

    /**
     * Update the specified event (Admin only)
     */
    public function update(UpdateEventRequest $request, string $id)
    {
        $result = $this->eventServices->updateEvent(DTOsEvent::fromUpdateRequest($request), $id);
        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }
        return response()->json($result['data'], 200);
    }

    /**
     * Select winner for event (Admin only)
     */
    public function selectWinner(SelectWinnerRequest $request, string $id)
    {
        $result = $this->eventServices->selectWinner(
            $id,
            $request->validated()['winner_number']
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message']
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 200);
    }

    /**
     * Remove the specified event (Admin only)
     */
    public function destroy(string $id)
    {
        // Verificar si el usuario autenticado es administrador
        if (!Auth::check() || !Auth::user()->isAdmin()) {
            return response()->json([
                'error' => 'No tienes permisos para realizar esta acción'
            ], 403);
        }

        $result = $this->eventServices->deleteEvent($id);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json($result['data'], 200);
    }
    // public function availableNumbers($id)
    // {
    //     try {
    //         $event = Event::findOrFail($id);

    //         if (!$event->isActive()) {
    //             return response()->json([
    //                 'error' => 'El evento no está activo'
    //             ], 422);
    //         }

    //         $availableNumbers = $event->getAvailableNumbers();

    //         return response()->json([
    //             'event_id' => $event->id,
    //             'event_name' => $event->name,
    //             'available_count' => count($availableNumbers),
    //             'available_numbers' => $availableNumbers,
    //             'range' => [
    //                 'start' => $event->start_number,
    //                 'end' => $event->end_number
    //             ]
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'error' => $e->getMessage()
    //         ], 422);
    //     }
    // }

    /**
     * Obtener estadísticas de un evento
     */
    public function statistics($id)
    {
        try {
            $event = Event::with(['prices', 'purchases'])->findOrFail($id);
            $statistics = $event->getStatistics();

            return response()->json([
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'status' => $event->status,
                    'start_date' => $event->start_date,
                    'end_date' => $event->end_date,
                ],
                'statistics' => $statistics
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Verificar si un número específico está disponible
     */
    public function checkNumber($id, $number)
    {
        try {
            $event = Event::findOrFail($id);

            if (!$event->isActive()) {
                return response()->json([
                    'error' => 'El evento no está activo'
                ], 422);
            }

            $isAvailable = $event->isNumberAvailable($number);

            return response()->json([
                'event_id' => $event->id,
                'number' => $number,
                'is_available' => $isAvailable,
                'message' => $isAvailable
                    ? 'El número está disponible'
                    : 'El número ya está ocupado'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener números ocupados de un evento (para mostrar en la interfaz)
     */
    public function occupiedNumbers($id)
    {
        try {
            $event = Event::findOrFail($id);

            $occupiedNumbers = $event->purchases()
                ->whereNotNull('ticket_number')
                ->where('status', '!=', 'failed')
                ->pluck('ticket_number')
                ->toArray();

            return response()->json([
                'event_id' => $event->id,
                'occupied_count' => count($occupiedNumbers),
                'occupied_numbers' => $occupiedNumbers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
    public function updateImage(UpdateEventImageRequest $request, string $id)
    {
        $result = $this->eventServices->updateEventImage($request, $id);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json($result, 200);
    }

    /**
     * Eliminar imagen del evento
     */
    public function deleteImage(string $id)
    {
        $result = $this->eventServices->deleteEventImage($id);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json($result, 200);
    }
}
