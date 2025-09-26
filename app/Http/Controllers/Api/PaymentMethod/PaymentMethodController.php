<?php

namespace App\Http\Controllers\Api\PaymentMethod;

use App\DTOs\PaymentMethod\DTOsPaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethod\CreatePaymentMethodRequest;
use App\Http\Requests\PaymentMethod\UpdatePaymentMethodRequest;
use App\Interfaces\PaymentMethod\IPaymentMethodServices;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller 
{
    protected IPaymentMethodServices $PaymentMethodServices;
    
    public function __construct(IPaymentMethodServices $PaymentMethodServicesInterface)
    {
        $this->PaymentMethodServices = $PaymentMethodServicesInterface;
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $result = $this->PaymentMethodServices->getAllPaymentMethods();
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
    public function store(CreatePaymentMethodRequest $request)
    {
        $result = $this->PaymentMethodServices->createPaymentMethod(DTOsPaymentMethod::fromRequest($request));
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
        $result = $this->PaymentMethodServices->getPaymentMethodById($id);
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
    public function update(UpdatePaymentMethodRequest $request, string $id)
    {
        $result = $this->PaymentMethodServices->updatePaymentMethod(DTOsPaymentMethod::fromUpdateRequest($request), $id);
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
        $result = $this->PaymentMethodServices->deletePaymentMethod($id);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
}
