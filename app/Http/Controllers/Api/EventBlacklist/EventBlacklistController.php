<?php

namespace App\Http\Controllers\Api\EventBlacklist;

use App\DTOs\EventBlacklist\DTOsEventBlacklist;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventBlacklist\CreateEventBlacklistRequest;
use App\Interfaces\EventBlacklist\IEventBlacklistServices;
use App\Models\Purchase;

class EventBlacklistController extends Controller
{
    protected IEventBlacklistServices $blacklistServices;

    public function __construct(IEventBlacklistServices $blacklistServicesInterface)
    {
        $this->blacklistServices = $blacklistServicesInterface;
    }

    /**
     * Agregar identificación a lista negra
     * POST /api/admin/events/{eventId}/blacklist
     */
    public function store(CreateEventBlacklistRequest $request)
    {
        $result = $this->blacklistServices->addToBlacklist(
            DTOsEventBlacklist::fromRequest($request)
        );

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 201);
    }

    /**
     * Listar identificaciones en lista negra de un evento
     * GET /api/admin/events/{eventId}/blacklist
     */
    public function index(string $eventId)
    {
        $result = $this->blacklistServices->getBlacklistByEvent((int) $eventId);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json($result['data'], 200);
    }

    /**
     * Eliminar identificación de lista negra
     * DELETE /api/admin/events/{eventId}/blacklist/{identificacion}
     */
    public function destroy(string $eventId, string $identificacion)
    {
        $result = $this->blacklistServices->removeFromBlacklist(
            (int) $eventId,
            $identificacion
        );

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json([
            'message' => $result['message']
        ], 200);
    }
}
