<?php

namespace App\Http\Controllers\Api\Event;

use App\DTOs\Event\DTOsEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\CreateEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Interfaces\Event\IEventServices;
use Illuminate\Http\Request;

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
        // Admin ve con participantes, clientes solo info básica
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
    public function selectWinner(string $id)
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $result = $this->eventServices->selectWinner($id);
        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }
        return response()->json($result['data'], 200);
    }

    /**
     * Remove the specified event (Admin only)
     */
    public function destroy(string $id)
    {
        $result = $this->eventServices->deleteEvent($id);
        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }
        return response()->json($result['data'], 200);
    }
}
