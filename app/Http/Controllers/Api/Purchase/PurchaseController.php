<?php

namespace App\Http\Controllers\Api\Purchase;

use App\DTOs\Purchase\DTOsAddTickets;
use App\DTOs\Purchase\DTOsAvailableNumbersFilter;
use App\DTOs\Purchase\DTOsPurchase;
use App\DTOs\Purchase\DTOsPurchaseFilter;
use App\DTOs\Purchase\DTOsUpdatePurchaseQuantity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\AddTicketsToTransactionRequest;
use App\Http\Requests\Purchase\CheckTicketAvailabilityRequest;
use App\Http\Requests\Purchase\CreateAdminMassivePurchaseRequest;
use App\Http\Requests\Purchase\CreateAdminPurchaseRequest;
use App\Http\Requests\Purchase\CreateAdminRandomPurchaseRequest;
use App\Http\Requests\Purchase\CreatePurchaseRequest;
use App\Http\Requests\Purchase\CreateSinglePurchaseRequest;
use App\Http\Requests\Purchase\GetAvailableNumbersRequest;
use App\Http\Requests\Purchase\GetPurchasesByIdentificacionRequest;
use App\Http\Requests\Purchase\GetPurchasesByWhatsAppRequest;
use App\Http\Requests\Purchase\UpdatePurchaseQuantityRequest;
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
     *
     * Obtiene un listado paginado de compras agrupadas por transaction_id con filtros avanzados.
     *
     * FILTROS DISPONIBLES:
     *
     * 1. FILTROS BÁSICOS:
     * - user_id (int): Filtrar por ID de usuario autenticado
     *   Ejemplo: ?user_id=5
     *
     * - event_id (int): Filtrar por ID de evento específico
     *   Ejemplo: ?event_id=1
     *
     * - status (string): Filtrar por estado de compra [pending, processing, completed, failed]
     *   Ejemplo: ?status=completed
     *
     * - currency (string): Filtrar por moneda [BS, USD]
     *   Ejemplo: ?currency=USD
     *
     * - payment_method_id (int): Filtrar por método de pago
     *   Ejemplo: ?payment_method_id=3
     *
     * 2. FILTROS DE BÚSQUEDA:
     * - transaction_id (string): Buscar por transaction_id parcial
     *   Ejemplo: ?transaction_id=TXN-20250110
     *
     * - ticket_number (string): Buscar por número de ticket específico
     *   Ejemplo: ?ticket_number=00123
     *
     * - fullname (string): Buscar por nombre completo del comprador (búsqueda exacta con LIKE)
     *   Ejemplo: ?fullname=Juan Perez
     *
     * - identificacion (string): Filtrar por cédula de identidad
     *   Ejemplo: ?identificacion=V-12345678
     *
     * - search (string): Búsqueda global (busca en: transaction_id, payment_reference, email,
     *   whatsapp, identificacion, ticket_number, fullname, nombre de usuario, nombre de evento)
     *   Ejemplo: ?search=rodriguez
     *
     * 3. FILTROS DE FECHA:
     * - date_from (date): Fecha inicial (formato: YYYY-MM-DD)
     *   Ejemplo: ?date_from=2025-01-01
     *
     * - date_to (date): Fecha final (formato: YYYY-MM-DD)
     *   Ejemplo: ?date_to=2025-01-31
     *
     * 4. FILTROS DE CANTIDAD:
     * - min_quantity (int): Filtrar compradores con mínimo X tickets en una transacción
     *   Ejemplo: ?min_quantity=50 (trae solo transacciones con 50+ tickets)
     *
     * 5. ORDENAMIENTO:
     * - sort_by (string): Campo por el cual ordenar
     *   Opciones: [quantity, total_customer_purchased, total_amount, status, created_at]
     *   Default: quantity
     *
     *   • quantity: Ordena por cantidad de tickets en la transacción actual
     *   • total_customer_purchased: Ordena por total histórico del cliente en el evento
     *   • total_amount: Ordena por monto total de la transacción
     *   • status: Ordena por estado (completed, pending, failed)
     *   • created_at: Ordena por fecha de creación
     *
     * - sort_order (string): Orden de clasificación [asc, desc]
     *   Default: desc
     *
     * 6. PAGINACIÓN:
     * - page (int): Número de página (default: 1)
     *   Ejemplo: ?page=2
     *
     * - per_page (int): Cantidad de resultados por página (default: 15)
     *   Ejemplo: ?per_page=50
     *
     * ============================================================================
     * EJEMPLOS DE USO PRÁCTICO:
     * ============================================================================
     *
     * 1. LISTAR TODAS LAS COMPRAS (ordenadas por cantidad de mayor a menor):
     *    GET /api/purchases
     *
     * 2. VER COMPRADORES VIP (mínimo 100 tickets históricos):
     *    GET /api/purchases?sort_by=total_customer_purchased&sort_order=desc&min_quantity=100
     *
     * 3. TOP 20 COMPRADORES DE UN EVENTO ESPECÍFICO:
     *    GET /api/purchases?event_id=1&sort_by=total_customer_purchased&sort_order=desc&per_page=20
     *
     * 4. BUSCAR TODAS LAS COMPRAS DE UN CLIENTE POR CÉDULA:
     *    GET /api/purchases?identificacion=V-12345678&sort_by=created_at&sort_order=desc
     *
     * 5. BUSCAR POR NOMBRE COMPLETO:
     *    GET /api/purchases?fullname=Juan Perez
     *
     * 6. COMPRAS COMPLETADAS DE UN EVENTO EN UN RANGO DE FECHAS:
     *    GET /api/purchases?event_id=1&status=completed&date_from=2025-01-01&date_to=2025-01-31
     *
     * 7. BUSCAR POR NÚMERO DE TICKET ESPECÍFICO:
     *    GET /api/purchases?ticket_number=00123
     *
     * 8. BUSCAR POR TRANSACTION_ID:
     *    GET /api/purchases?transaction_id=TXN-20250110-ABC123
     *
     * 9. COMPRAS PENDIENTES DE UN EVENTO:
     *    GET /api/purchases?event_id=1&status=pending&sort_by=created_at&sort_order=desc
     *
     * 10. BUSCAR POR EMAIL O WHATSAPP (búsqueda global):
     *     GET /api/purchases?search=juan@example.com
     *     GET /api/purchases?search=+58424123456
     *
     * 11. COMPRADORES GRANDES (mínimo 50 tickets por transacción):
     *     GET /api/purchases?min_quantity=50&sort_by=quantity&sort_order=desc
     *
     * 12. ANÁLISIS DE CLIENTES VIP DE UN EVENTO (compras históricas totales):
     *     GET /api/purchases?event_id=1&sort_by=total_customer_purchased&sort_order=desc&per_page=50
     *
     * 13. COMPRAS EN DÓLARES COMPLETADAS:
     *     GET /api/purchases?currency=USD&status=completed
     *
     * 14. COMPRAS POR MÉTODO DE PAGO ESPECÍFICO:
     *     GET /api/purchases?payment_method_id=2&sort_by=total_amount&sort_order=desc
     *
     * 15. BÚSQUEDA GLOBAL POR APELLIDO:
     *     GET /api/purchases?search=Rodriguez
     *
     * 16. LISTAR COMPRADORES PEQUEÑOS (1-10 tickets):
     *     GET /api/purchases?sort_by=quantity&sort_order=asc&per_page=100
     *
     * 17. ANÁLISIS MENSUAL DE UN EVENTO:
     *     GET /api/purchases?event_id=1&date_from=2025-01-01&date_to=2025-01-31&sort_by=created_at&sort_order=desc
     *
     * 18. HISTORIAL COMPLETO DE COMPRAS DE UN CLIENTE:
     *     GET /api/purchases?identificacion=V-12345678&sort_by=created_at&sort_order=desc&per_page=100
     *
     * 19. COMPRAS FALLIDAS PARA ANÁLISIS:
     *     GET /api/purchases?status=failed&sort_by=created_at&sort_order=desc
     *
     * 20. COMBINAR MÚLTIPLES FILTROS (Evento + Moneda + Rango de fechas + Status):
     *     GET /api/purchases?event_id=1&currency=USD&status=completed&date_from=2025-01-01&date_to=2025-01-31&sort_by=total_amount&sort_order=desc
     *
     * ============================================================================
     * CASOS DE USO AVANZADOS:
     * ============================================================================
     *
     * 21. DASHBOARD ADMINISTRATIVO - Top 10 compradores del mes:
     *     GET /api/purchases?date_from=2025-01-01&date_to=2025-01-31&sort_by=total_customer_purchased&sort_order=desc&per_page=10
     *
     * 22. ANÁLISIS DE VENTAS - Compras mayores a $1000:
     *     GET /api/purchases?sort_by=total_amount&sort_order=desc&min_quantity=20
     *
     * 23. SEGUIMIENTO DE CLIENTE VIP:
     *     GET /api/purchases?identificacion=V-12345678&event_id=1&sort_by=created_at&sort_order=asc
     *
     * 24. VERIFICAR TICKET ESPECÍFICO Y VER COMPRADOR:
     *     GET /api/purchases?ticket_number=00123&event_id=1
     *
     * 25. REPORTES CONTABLES - Todas las compras completadas del mes en USD:
     *     GET /api/purchases?currency=USD&status=completed&date_from=2025-01-01&date_to=2025-01-31&per_page=100
     *
     * 26. DETECTAR PATRONES - Clientes que compraron más de 100 tickets en múltiples transacciones:
     *     GET /api/purchases?sort_by=total_customer_purchased&sort_order=desc&min_quantity=100
     *
     * 27. AUDITORÍA - Ver todas las transacciones con un payment_reference específico:
     *     GET /api/purchases?search=REF-001
     *
     * 28. MARKETING - Clientes frecuentes de un evento (ordenados por historial):
     *     GET /api/purchases?event_id=1&sort_by=total_customer_purchased&sort_order=desc&per_page=50
     *
     * ============================================================================
     * RESPUESTA EXITOSA (200):
     * ============================================================================
     * {
     *   "data": [
     *     {
     *       "transaction_id": "TXN-20250110-ABC123",
     *       "event": {
     *         "id": 1,
     *         "name": "Rifa Año Nuevo 2025"
     *       },
     *       "user": null,
     *       "fullname": "Juan Pérez López",
     *       "email": "juan@example.com",
     *       "whatsapp": "+58424123456",
     *       "identificacion": "V-12345678",
     *       "quantity": 100,                    // Cantidad en ESTA transacción
     *       "total_customer_purchased": 450,    // Total histórico del cliente en el evento
     *       "unit_price": "50.00",
     *       "total_amount": "5000.00",
     *       "currency": "USD",
     *       "payment_method": "Zelle",
     *       "payment_reference": "REF-001",
     *       "payment_proof": "https://...",
     *       "qr_code_url": "https://...",
     *       "status": "completed",
     *       "ticket_numbers": ["00001", "00002", "00003"],
     *       "purchase_ids": [1, 2, 3],
     *       "created_at": "2025-01-10 15:30:00"
     *     }
     *   ],
     *   "pagination": {
     *     "total": 100,
     *     "per_page": 15,
     *     "current_page": 1,
     *     "last_page": 7,
     *     "from": 1,
     *     "to": 15
     *   }
     * }
     *
     * ============================================================================
     * RESPUESTA DE ERROR (422):
     * ============================================================================
     * {
     *   "error": "Mensaje de error descriptivo"
     * }
     *
     * ============================================================================
     * NOTAS IMPORTANTES:
     * ============================================================================
     * - Los filtros son COMBINABLES para análisis complejos
     * - quantity: Tickets en la transacción actual
     * - total_customer_purchased: Suma de TODOS los tickets del cliente en ese evento (historial)
     * - search: Búsqueda global en múltiples campos
     * - fullname: Búsqueda específica solo en el campo nombre
     * - El ordenamiento por defecto es por 'quantity' DESC (mayor a menor)
     * - La paginación por defecto es 15 resultados por página
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

    /**
     * ✨ ACTUALIZADO: Obtener top de compradores por evento con filtro de moneda
     */
    public function getTopBuyers(Request $request, string $eventId)
    {
        // Validar parámetros opcionales
        $limit = (int) $request->get('limit', 10); // Default: top 10
        $minTickets = (int) $request->get('min_tickets', 1); // Mínimo de tickets
        $currency = $request->get('currency'); // ✨ Filtro por moneda (opcional)

        // ✅ Validar que la moneda sea válida si se proporciona
        if ($currency && !in_array(strtoupper($currency), ['VES', 'USD'])) {
            return response()->json([
                'error' => 'Moneda inválida. Valores permitidos: VES, USD'
            ], 422);
        }

        $result = $this->PurchaseServices->getTopBuyersByEvent(
            $eventId,
            $limit,
            $minTickets,
            $currency ? strtoupper($currency) : null // ✨ Pasar moneda en mayúsculas
        );

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
        $result = $this->PurchaseServices->createMassivePurchaseAsync(
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
            'data' => $result['data'],
            'processing_mode' => 'background' // Indicador para el frontend
        ], 202);
    }
    public function storeAdminMassiveAsync(CreateAdminMassivePurchaseRequest $request)
    {
        $autoApprove = $request->input('auto_approve', true);

        $result = $this->PurchaseServices->createMassivePurchaseAsync(
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
            'data' => $result['data'],
            'processing_mode' => 'background'
        ], 202); // 202 Accepted
    }

    /**
     * ✅ NUEVO: Consultar estado de compra masiva
     *
     * Permite verificar si una compra en background ya fue procesada.
     *
     * Ruta: GET /admin/massive-status/{transactionId}
     */
    public function getMassivePurchaseStatus(string $transactionId)
    {
        $result = $this->PurchaseServices->getMassivePurchaseStatus($transactionId);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message'],
                'data' => $result['data'] ?? null
            ], $result['data']['status'] ?? 'processing' ? 200 : 404);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 200);
    }
    public function addTickets(AddTicketsToTransactionRequest $request, string $transactionId)
    {
        $dto = DTOsAddTickets::fromRequest($request, $transactionId);

        $result = $this->PurchaseServices->addTicketsToTransaction($dto);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json($result, 200);
    }

    /**
     * ✅ Quitar tickets de una transacción
     */
    public function removeTickets(Request $request, string $transactionId)
    {
        $validated = $request->validate([
            'ticket_numbers' => 'required|array|min:1',
            'ticket_numbers.*' => 'required|string',
        ]);

        $result = $this->PurchaseServices->removeTicketsFromTransaction(
            $transactionId,
            $validated['ticket_numbers']
        );

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json($result, 200);
    }
    public function updatePendingQuantity(UpdatePurchaseQuantityRequest $request)
    {
        $dto = DTOsUpdatePurchaseQuantity::fromArray($request->validated());

        $result = $this->PurchaseServices->updatePendingPurchaseQuantity($dto);

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
     * ✅ NUEVO: Obtener números disponibles de un evento
     *
     * Ruta: GET /api/events/{eventId}/available-numbers
     *
     * Query Parameters:
     * - search: Buscar números que contengan este texto (ej: ?search=123)
     * - min_number: Filtrar desde este número (ej: ?min_number=1000)
     * - max_number: Filtrar hasta este número (ej: ?max_number=2000)
     * - page: Página actual (default: 1)
     * - per_page: Resultados por página (default: 30, max: 100)
     *
     * Ejemplos:
     * 1. Listar primeros 30 números disponibles:
     *    GET /api/events/1/available-numbers
     *
     * 2. Buscar números que contengan "123":
     *    GET /api/events/1/available-numbers?search=123
     *
     * 3. Números disponibles entre 1000 y 2000:
     *    GET /api/events/1/available-numbers?min_number=1000&max_number=2000
     *
     * 4. Segunda página con 50 resultados:
     *    GET /api/events/1/available-numbers?page=2&per_page=50
     *
     * 5. Combinar filtros:
     *    GET /api/events/1/available-numbers?search=5&min_number=500&max_number=1500&per_page=50
     */
    public function getAvailableNumbers(GetAvailableNumbersRequest $request, string $eventId)
    {
        $filters = DTOsAvailableNumbersFilter::fromRequest($request, (int) $eventId);

        $result = $this->PurchaseServices->getAvailableNumbers($filters);

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }

        return response()->json($result['data'], 200);
    }
    // public function checkSingleNumber(string $eventId, string $ticketNumber)
    // {
    //     $result = $this->PurchaseServices->checkSingleNumberAvailability(
    //         (int) $eventId,
    //         $ticketNumber
    //     );

    //     if (!$result['success']) {
    //         return response()->json([
    //             'error' => $result['message'],
    //             'data' => $result['data'] ?? null
    //         ], $result['available'] === false ? 200 : 422);
    //     }

    //     return response()->json($result['data'], 200);
    // }
}
