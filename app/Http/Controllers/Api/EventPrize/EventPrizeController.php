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
    protected IEventPrizeServices $EventPrizeServices;
    
    public function __construct(IEventPrizeServices $EventPrizeServicesInterface)
    {
        $this->EventPrizeServices = $EventPrizeServicesInterface;
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $result = $this->EventPrizeServices->getAllEventPrizes();
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateEventPrizeRequest $request)
    {
        $result = $this->EventPrizeServices->createEventPrize(DTOsEventPrize::fromRequest($request));
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 201);
    }
    
    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $result = $this->EventPrizeServices->getEventPrizeById($id);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
    
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventPrizeRequest $request, string $id)
    {
        $result = $this->EventPrizeServices->updateEventPrize(DTOsEventPrize::fromUpdateRequest($request), $id);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $result = $this->EventPrizeServices->deleteEventPrize($id);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
}
