<?php

namespace App\Http\Controllers\Api\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\DTOs\Purchase\DTOsPurchaseFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\CheckTicketAvailabilityRequest;
use App\Http\Requests\Purchase\CreateAdminMassivePurchaseRequest;
use App\Http\Requests\Purchase\CreateAdminPurchaseRequest;
use App\Http\Requests\Purchase\CreateAdminRandomPurchaseRequest;
use App\Http\Requests\Purchase\CreatePurchaseRequest;
use App\Http\Requests\Purchase\CreateSinglePurchaseRequest;
use App\Http\Requests\Purchase\GetPurchasesByIdentificacionRequest;
use App\Http\Requests\Purchase\GetPurchasesByWhatsAppRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use App\Interfaces\Purchase\IPurchaseServices;
use Illuminate\Http\Request;
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
    public function index(Request $request)
    {
        $filters = DTOsPurchaseFilter::fromRequest($request);

        $result = $this->PurchaseServices->getAllPurchases($filters);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json($result['data'], 200);
    }

    public function getPurchasesByEvent($eventId)
    {
        $result = $this->PurchaseServices->getPurchasesByEvent($eventId);
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
            'message' => $result['message'],
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
        $result = $this->PurchaseServices->getUserPurchases(Auth::user()->id);
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

    public function showByTransaction(string $transactionId)
    {
        $result = $this->PurchaseServices->getPurchaseByTransaction($transactionId);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
    public function approve(string $transactionId)
    {
        $result = $this->PurchaseServices->approvePurchase($transactionId);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 200);
    }

    /**
     * Rechazar una orden de compra por transaction_id
     */
    public function reject(string $transactionId, Request $request)
    {
        $reason = $request->input('reason', null);

        $result = $this->PurchaseServices->rejectPurchase($transactionId, $reason);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 200);
    }
    public function storeSingle(CreateSinglePurchaseRequest $request)
    {
        $result = $this->PurchaseServices->createSinglePurchase(
            DTOsPurchase::fromSinglePurchaseRequest($request)
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
    public function storeAdmin(CreateAdminPurchaseRequest $request)
    {
        $autoApprove = $request->input('auto_approve', true);

        $result = $this->PurchaseServices->createAdminPurchase(
            DTOsPurchase::fromAdminPurchaseRequest($request),
            $autoApprove
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
    public function storeAdminRandom(CreateAdminRandomPurchaseRequest $request)
    {

        $autoApprove = $request->input('auto_approve', true);

        $result = $this->PurchaseServices->createAdminRandomPurchase(
            DTOsPurchase::fromAdminRandomPurchaseRequest($request),
            $autoApprove
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
    public function getByWhatsApp(GetPurchasesByWhatsAppRequest $request, string $whatsapp)
    {

        $result = $this->PurchaseServices->getPurchasesByWhatsApp($whatsapp);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message'],
                'data' => $result['data'] ?? []
            ], $result['data'] ? 200 : 404);
        }

        return response()->json($result['data'], 200);
    }
    public function getByIdentificacion(GetPurchasesByIdentificacionRequest $request, string $identificacion)
    {
        $result = $this->PurchaseServices->getPurchasesByIdentificacion($identificacion);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message'],
                'data' => $result['data'] ?? []
            ], $result['data'] ? 200 : 404);
        }

        return response()->json($result['data'], 200);
    }
    public function checkTicketAvailability(CheckTicketAvailabilityRequest $request)
    {
        $validated = $request->validated();

        $result = $this->PurchaseServices->checkTicketAvailability(
            $validated['event_id'],
            $validated['ticket_number']
        );

        if ($result['success']) {
            return response()->json($result, 200);
        }

        return response()->json([
            'error' => $result['message'],
            'data' => $result['data'] ?? []
        ], 422);
    }
    public function storeAdminMassive(CreateAdminMassivePurchaseRequest $request)
    {
        $autoApprove = $request->input('auto_approve', true);

        $result = $this->PurchaseServices->createAdminRandomPurchase(
            DTOsPurchase::fromAdminMassivePurchaseRequest($request),
            $autoApprove
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
}
