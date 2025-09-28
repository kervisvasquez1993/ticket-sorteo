<?php

namespace App\Http\Controllers\Api\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\CreatePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use App\Interfaces\Purchase\IPurchaseServices;
use Illuminate\Support\Facades\Auth;

class PurchaseController extends Controller
{
    protected IPurchaseServices $PurchaseServices;

    public function __construct(IPurchaseServices $PurchaseServicesInterface)
    {
        $this->PurchaseServices = $PurchaseServicesInterface;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $result = $this->PurchaseServices->getAllPurchases();
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
    public function store(CreatePurchaseRequest $request)
    {
        $result = $this->PurchaseServices->createPurchase(DTOsPurchase::fromRequest($request));
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json([
            'message' => $result['message'] ?? 'Compra creada exitosamente',
            'data' => $result['data']
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $result = $this->PurchaseServices->getPurchaseById($id);
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
    public function update(UpdatePurchaseRequest $request, string $id)
    {
        $result = $this->PurchaseServices->updatePurchase(DTOsPurchase::fromUpdateRequest($request), $id);
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
        $result = $this->PurchaseServices->deletePurchase($id);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }

    /**
     * Obtener compras del usuario autenticado
     */
    public function myPurchases()
    {
        $result = $this->PurchaseServices->getUserPurchases(Auth::id());
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
      public function purchaseSummary($transactionId)
    {
        $result = $this->PurchaseServices->getPurchaseSummary($transactionId);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
}
