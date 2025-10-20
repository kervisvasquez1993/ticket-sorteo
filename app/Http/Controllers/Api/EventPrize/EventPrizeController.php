<?php

namespace App\Http\Controllers\Api\EventPrize;

use App\DTOs\EventPrize\DTOsEventPrize;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventPrize\CreateEventPrizeRequest;
use App\Http\Requests\EventPrize\UpdateEventPrizeRequest;
use App\Interfaces\EventPrize\IEventPrizeServices;
use Illuminate\Http\Request;

class EventPrizeController extends Controller
{
    protected IEventPrizeServices $eventPrizeServices;

    public function __construct(IEventPrizeServices $eventPrizeServicesInterface)
    {
        $this->eventPrizeServices = $eventPrizeServicesInterface;
    }

    /**
     * Display a listing of all prizes
     * GET /api/event-prizes
     */
    public function index()
    {
        $result = $this->eventPrizeServices->getAllEventPrizes();

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json($result['data'], 200);
    }

    /**
     * Get all prizes for a specific event
     * GET /api/events/{eventId}/prizes
     */
    public function getByEvent(string $eventId)
    {
        $result = $this->eventPrizeServices->getEventPrizesByEventId($eventId);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ], 200);
    }

    /**
     * Get only the main prize for a specific event
     * GET /api/events/{eventId}/main-prize
     */
    public function getMainPrize(string $eventId)
    {
        $result = $this->eventPrizeServices->getMainPrizeByEventId($eventId);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data']
        ], 200);
    }

    /**
     * Store a newly created prize
     * POST /api/event-prizes
     */
    public function store(CreateEventPrizeRequest $request)
    {
        $result = $this->eventPrizeServices->createEventPrize(
            DTOsEventPrize::fromRequest($request)
        );

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Prize created successfully',
            'data' => $result['data']
        ], 201);
    }

    /**
     * Display the specified prize
     * GET /api/event-prizes/{id}
     */
    public function show(string $id)
    {
        $result = $this->eventPrizeServices->getEventPrizeById($id);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json($result['data'], 200);
    }

    /**
     * Update the specified prize
     * PUT/PATCH /api/event-prizes/{id}
     */
    public function update(UpdateEventPrizeRequest $request, string $id)
    {
        // Obtener la imagen actual por si no se envía una nueva
        $currentPrize = $this->eventPrizeServices->getEventPrizeById($id);

        if (!$currentPrize['success']) {
            return response()->json([
                'error' => $currentPrize['message']
            ], 422);
        }

        $result = $this->eventPrizeServices->updateEventPrize(
            DTOsEventPrize::fromUpdateRequest($request, $currentPrize['data']->image_url),
            $id
        );

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Prize updated successfully',
            'data' => $result['data']
        ], 200);
    }

    /**
     * Remove the specified prize
     * DELETE /api/event-prizes/{id}
     */
    public function destroy(string $id)
    {
        $result = $this->eventPrizeServices->deleteEventPrize($id);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Prize deleted successfully',
            'data' => $result['data']
        ], 200);
    }

    /**
     * Set a prize as the main prize for its event
     * POST /api/event-prizes/{id}/set-main
     */
    public function setAsMain(string $id)
    {
        $result = $this->eventPrizeServices->setAsMainPrize($id);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Prize set as main successfully',
            'data' => $result['data']
        ], 200);
    }
}
