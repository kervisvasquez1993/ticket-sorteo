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
    protected IPaymentMethodServices $paymentMethodServices;

    public function __construct(IPaymentMethodServices $paymentMethodServicesInterface)
    {
        $this->paymentMethodServices = $paymentMethodServicesInterface;
    }

    /**
     * Display a listing of all payment methods.
     */
    public function index()
    {
        $result = $this->paymentMethodServices->getAllPaymentMethods();

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json($result['data'], 200);
    }

    /**
     * Display a listing of active payment methods.
     */
    public function active()
    {
        $result = $this->paymentMethodServices->getActivePaymentMethods();

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json($result['data'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreatePaymentMethodRequest $request)
    {
        $result = $this->paymentMethodServices->createPaymentMethod(
            DTOsPaymentMethod::fromRequest($request)
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $result = $this->paymentMethodServices->getPaymentMethodById($id);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 404);
        }

        return response()->json($result['data'], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentMethodRequest $request, string $id)
    {
        $result = $this->paymentMethodServices->updatePaymentMethod(
            DTOsPaymentMethod::fromUpdateRequest($request),
            $id
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $result = $this->paymentMethodServices->deletePaymentMethod($id);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 200);
    }

    /**
     * Toggle active status of payment method.
     */
    public function toggleActive(string $id)
    {
        $result = $this->paymentMethodServices->toggleActive($id);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 200);
    }
}
