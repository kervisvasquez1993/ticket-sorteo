<?php

namespace App\Http\Controllers\Api\EventPrice;

use App\DTOs\EventPrice\DTOsEventPrice;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventPrice\CreateEventPriceRequest;
use App\Http\Requests\EventPrice\UpdateEventPriceRequest;
use App\Interfaces\EventPrice\IEventPriceServices;
use Illuminate\Http\Request;

class EventPriceController extends Controller 
{
    protected IEventPriceServices $EventPriceServices;
    
    public function __construct(IEventPriceServices $EventPriceServicesInterface)
    {
        $this->EventPriceServices = $EventPriceServicesInterface;
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $result = $this->EventPriceServices->getAllEventPrices();
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
    public function store(CreateEventPriceRequest $request)
    {
        $result = $this->EventPriceServices->createEventPrice(DTOsEventPrice::fromRequest($request));
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
        $result = $this->EventPriceServices->getEventPriceById($id);
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
    public function update(UpdateEventPriceRequest $request, string $id)
    {
        $result = $this->EventPriceServices->updateEventPrice(DTOsEventPrice::fromUpdateRequest($request), $id);
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
        $result = $this->EventPriceServices->deleteEventPrice($id);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
}
