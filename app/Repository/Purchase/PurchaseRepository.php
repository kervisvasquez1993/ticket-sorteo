<?php

namespace App\Repository\Purchase;

use App\DTOs\Purchase\DTOsAddTickets;
use App\DTOs\Purchase\DTOsPurchase;
use App\DTOs\Purchase\DTOsPurchaseFilter;
use App\Interfaces\Purchase\IPurchaseRepository;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PurchaseRepository implements IPurchaseRepository
{
    // ====================================================================
    // MÉTODOS BÁSICOS CRUD
    // ====================================================================

    public function getAllPurchases()
    {
        return Purchase::with(['user', 'event', 'eventPrice', 'paymentMethod'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPurchaseById($id): Purchase
    {
        $Purchase = Purchase::with(['user', 'event', 'eventPrice', 'paymentMethod'])
            ->where('id', $id)
            ->first();

        if (!$Purchase) {
            throw new \Exception("No results found for Purchase with ID {$id}");
        }
        return $Purchase;
    }

    public function createPurchase(DTOsPurchase $data, $amount, $transactionId = null): Purchase
    {
        $purchaseData = [
            'user_id' => $data->getUserId(),
            'email' => $data->getEmail(),
            'whatsapp' => $data->getWhatsapp(),
            'identificacion' => $data->getIdentificacion(), // ✅ AGREGADO
            'event_id' => $data->getEventId(),
            'event_price_id' => $data->getEventPriceId(),
            'payment_method_id' => $data->getPaymentMethodId(),
            'amount' => $amount,
            'currency' => $data->getCurrency(),
            'status' => 'pending',
            'ticket_number' => null,
            'transaction_id' => $transactionId,
            'payment_reference' => $data->getPaymentReference(),
            'payment_proof_url' => $data->getPaymentProofUrl(),
            'quantity' => 1,
            'total_amount' => $data->getTotalAmount(),
        ];

        return Purchase::create($purchaseData);
    }

    public function updatePurchase(DTOsPurchase $data, Purchase $Purchase): Purchase
    {
        $Purchase->update($data->toArray());
        return $Purchase;
    }

    public function deletePurchase(Purchase $Purchase): Purchase
    {
        $Purchase->delete();
        return $Purchase;
    }

    // ====================================================================
    // MÉTODOS OPTIMIZADOS PARA INSERT MASIVO
    // ====================================================================

    /**
     * ✅ NUEVO: Insert masivo de múltiples compras
     * Retorna la cantidad de registros insertados
     */
    public function bulkInsertPurchases(array $purchaseRecords): int
    {
        return Purchase::insert($purchaseRecords);
    }

    /**
     * ✅ NUEVO: Preparar array de datos para insert masivo
     * Este método construye el array desde el DTO
     */
    public function preparePurchaseRecord(
        DTOsPurchase $data,
        float $amount,
        string $transactionId,
        ?string $ticketNumber = null,
        string $status = 'pending'
    ): array {
        return [
            'event_id' => $data->getEventId(),
            'event_price_id' => $data->getEventPriceId(),
            'payment_method_id' => $data->getPaymentMethodId(),
            'user_id' => $data->getUserId(),
            'fullname' => $data->getFullname(),
            'email' => $data->getEmail(),
            'whatsapp' => $data->getWhatsapp(),
            'identificacion' => $data->getIdentificacion(),
            'currency' => $data->getCurrency(),
            'ticket_number' => $ticketNumber,
            'amount' => $amount,
            'total_amount' => $amount,
            'quantity' => 1,
            'transaction_id' => $transactionId,
            'payment_reference' => $data->getPaymentReference(),
            'payment_proof_url' => $data->getPaymentProofUrl(),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // ====================================================================
    // MÉTODOS DE VALIDACIÓN Y VERIFICACIÓN
    // ====================================================================

    /**
     * ✅ OPTIMIZADO: Verificar múltiples números de ticket de una vez
     */
    // public function getReservedTicketNumbers(int $eventId, array $ticketNumbers): array
    // {
    //     return Purchase::where('event_id', $eventId)
    //         ->whereIn('ticket_number', $ticketNumbers)
    //         ->lockForUpdate()
    //         ->pluck('ticket_number')
    //         ->toArray();
    // }
    public function getReservedTicketNumbers(int $eventId, array $ticketNumbers): array
    {
        return Purchase::where('event_id', $eventId)
            ->whereIn('ticket_number', $ticketNumbers)
            ->where('ticket_number', 'NOT LIKE', 'RECHAZADO%') // ✅ Excluir rechazados
            ->lockForUpdate()
            ->pluck('ticket_number')
            ->toArray();
    }

    /**
     * ✅ OPTIMIZADO: Obtener números usados de un evento
     */
    // public function getUsedTicketNumbers(int $eventId): array
    // {
    //     return Purchase::where('event_id', $eventId)
    //         ->whereNotNull('ticket_number')
    //         ->pluck('ticket_number')
    //         ->toArray();
    // }
    public function getUsedTicketNumbers(int $eventId): array
    {
        return Purchase::where('event_id', $eventId)
            ->whereNotNull('ticket_number')
            ->where('ticket_number', 'NOT LIKE', 'RECHAZADO%') // ✅ Excluir rechazados
            ->pluck('ticket_number')
            ->toArray();
    }

    /**
     * Verificar si un número está disponible (método legacy)
     */
    public function isNumberAvailable($eventId, $ticketNumber): bool
    {
        return !Purchase::where('event_id', $eventId)
            ->where('ticket_number', $ticketNumber)
            ->exists();
    }

    /**
     * ✅ NUEVO: Verificar si un transaction_id existe
     */
    public function transactionIdExists(string $transactionId): bool
    {
        return Purchase::where('transaction_id', $transactionId)->exists();
    }

    // ====================================================================
    // MÉTODOS DE ACTUALIZACIÓN MASIVA
    // ====================================================================

    /**
     * ✅ NUEVO: Actualizar QR Code para toda una transacción
     */
    public function updateQrCodeByTransaction(string $transactionId, string $qrCodeUrl): int
    {
        return Purchase::where('transaction_id', $transactionId)
            ->update([
                'qr_code_url' => $qrCodeUrl,
                'updated_at' => now()
            ]);
    }

    /**
     * ✅ NUEVO: Actualizar status de toda una transacción
     */
    public function updateStatusByTransaction(string $transactionId, string $status): int
    {
        return Purchase::where('transaction_id', $transactionId)
            ->update([
                'status' => $status,
                'updated_at' => now()
            ]);
    }

    /**
     * ✅ NUEVO: Actualizar status de compras específicas
     */
    public function updateStatusByTransactionAndConditions(
        string $transactionId,
        string $newStatus,
        ?string $currentStatus = null,
        ?bool $hasTicketNumber = null
    ): int {
        $query = Purchase::where('transaction_id', $transactionId);

        if ($currentStatus !== null) {
            $query->where('status', $currentStatus);
        }

        if ($hasTicketNumber === true) {
            $query->whereNotNull('ticket_number');
        } elseif ($hasTicketNumber === false) {
            $query->whereNull('ticket_number');
        }

        return $query->update([
            'status' => $newStatus,
            'updated_at' => now()
        ]);
    }

    /**
     * ✅ NUEVO: Asignar número de ticket a una compra específica
     */
    public function assignTicketNumber(int $purchaseId, string $ticketNumber, string $status = 'completed'): bool
    {
        return Purchase::where('id', $purchaseId)
            ->update([
                'ticket_number' => $ticketNumber,
                'status' => $status,
                'updated_at' => now()
            ]) > 0;
    }

    // ====================================================================
    // MÉTODOS DE CONSULTA POR TRANSACCIÓN
    // ====================================================================

    /**
     * ✅ OPTIMIZADO: Obtener compras por transaction_id
     */
    public function getPurchasesByTransaction($transactionId)
    {
        return Purchase::with(['event', 'eventPrice', 'paymentMethod', 'user'])
            ->where('transaction_id', $transactionId)
            ->get();
    }

    /**
     * ✅ NUEVO: Obtener compras pendientes por transaction_id con lock
     */
    public function getPendingPurchasesByTransaction(string $transactionId)
    {
        return Purchase::where('transaction_id', $transactionId)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->get();
    }

    /**
     * ✅ NUEVO: Contar compras por transacción
     */
    public function countPurchasesByTransaction(string $transactionId): int
    {
        return Purchase::where('transaction_id', $transactionId)->count();
    }

    // ====================================================================
    // MÉTODOS DE CONSULTA GENERAL
    // ====================================================================

    public function getUserPurchases($userId)
    {
        return Purchase::with(['event', 'eventPrice', 'paymentMethod'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPurchasesByEvent($eventId)
    {
        return Purchase::with(['user', 'eventPrice', 'paymentMethod'])
            ->where('event_id', $eventId)
            ->orderBy('ticket_number', 'asc')
            ->get();
    }

    public function getPurchasesByWhatsApp(string $whatsapp)
    {
        $results = Purchase::select(
            'transaction_id',
            DB::raw('MIN(id) as first_purchase_id'),
            DB::raw('MIN(created_at) as created_at'),
            DB::raw('COUNT(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN 1 END) as quantity'),
            DB::raw('SUM(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN amount ELSE 0 END) as total_amount'),
            'currency',
            DB::raw("CASE
            WHEN COUNT(CASE WHEN status = 'completed' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'completed'
            WHEN COUNT(CASE WHEN status = 'pending' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'pending'
            ELSE 'failed'
        END as status"),
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            'user_id',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
            DB::raw('MAX(identificacion) as identificacion'),
            DB::raw('MAX(fullname) as fullname'),
            DB::raw('MAX(qr_code_url) as qr_code_url')
        )
            ->where('whatsapp', $whatsapp)
            ->with([
                'event:id,name',
                'paymentMethod:id,name',
                'user:id,name,email'
            ])
            ->groupBy(
                'transaction_id',
                'currency',
                'event_id',
                'payment_method_id',
                'payment_reference',
                'payment_proof_url',
                'user_id'
            )
            ->orderBy('created_at', 'desc')
            ->get();

        return $results->map(function ($group) {
            return $this->formatGroupedPurchase($group);
        });
    }
    public function getPurchasesByIdentificacion(string $identificacion)
    {
        $numeros = preg_replace('/[^0-9]/', '', $identificacion);

        $results = Purchase::select(
            'transaction_id',
            DB::raw('MIN(id) as first_purchase_id'),
            DB::raw('MIN(created_at) as created_at'),
            DB::raw('COUNT(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN 1 END) as quantity'),
            DB::raw('SUM(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN amount ELSE 0 END) as total_amount'),
            'currency',
            DB::raw("CASE
            WHEN COUNT(CASE WHEN status = 'completed' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'completed'
            WHEN COUNT(CASE WHEN status = 'pending' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'pending'
            ELSE 'failed'
        END as status"),
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            'user_id',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
            DB::raw('MAX(identificacion) as identificacion'),
            DB::raw('MAX(fullname) as fullname'),
            DB::raw('MAX(qr_code_url) as qr_code_url')
        )
            ->where(function ($query) use ($numeros) {
                $query->where('identificacion', 'LIKE', '%' . $numeros)
                    ->orWhere('identificacion', $numeros);
            })
            ->with([
                'event:id,name',
                'paymentMethod:id,name',
                'user:id,name,email'
            ])
            ->groupBy(
                'transaction_id',
                'currency',
                'event_id',
                'payment_method_id',
                'payment_reference',
                'payment_proof_url',
                'user_id'
            )
            ->orderBy('created_at', 'desc')
            ->get();

        return $results->map(function ($group) {
            return $this->formatGroupedPurchase($group);
        });
    }
    // ====================================================================
    // MÉTODOS DE AGRUPACIÓN
    // ====================================================================
    public function getGroupedPurchases(?DTOsPurchaseFilter $filters = null)
    {
        $query = Purchase::query();

        if ($filters) {
            $this->applyFilters($query, $filters);
        }

        $query->select(
            'transaction_id',
            DB::raw('MIN(id) as first_purchase_id'),
            DB::raw('MIN(created_at) as created_at'),
            DB::raw('COUNT(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN 1 END) as quantity'),
            DB::raw('SUM(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN amount ELSE 0 END) as total_amount'),
            'currency',
            DB::raw("CASE
            WHEN COUNT(CASE WHEN status = 'completed' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'completed'
            WHEN COUNT(CASE WHEN status = 'pending' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'pending'
            ELSE 'failed'
        END as status"),
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            'user_id',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
            DB::raw('MAX(identificacion) as identificacion'),
            DB::raw('MAX(fullname) as fullname'),
            DB::raw('MAX(qr_code_url) as qr_code_url')
        )
            ->with([
                'event:id,name',
                'paymentMethod:id,name',
                'user:id,name,email'
            ])
            ->groupBy(
                'transaction_id',
                'currency',
                'event_id',
                'payment_method_id',
                'payment_reference',
                'payment_proof_url',
                'user_id'
            );

        if ($filters && $filters->isValidSortField() && $filters->isValidSortOrder()) {
            $this->applySorting($query, $filters);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $filters ? $filters->getPerPage() : 15;
        $page = $filters ? $filters->getPage() : 1;

        $paginatedResults = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginatedResults->map(function ($group) {
                return $this->formatGroupedPurchase($group);
            }),
            'pagination' => [
                'total' => $paginatedResults->total(),
                'per_page' => $paginatedResults->perPage(),
                'current_page' => $paginatedResults->currentPage(),
                'last_page' => $paginatedResults->lastPage(),
                'from' => $paginatedResults->firstItem(),
                'to' => $paginatedResults->lastItem()
            ]
        ];
    }


    // ✅ ACTUALIZADO: Incluir identificacion en el SELECT
    // public function getGroupedUserPurchases($userId)
    // {
    //     return Purchase::select(
    //         'transaction_id',
    //         DB::raw('MIN(id) as first_purchase_id'),
    //         DB::raw('MIN(created_at) as created_at'),
    //         DB::raw('COUNT(*) as quantity'),
    //         DB::raw('SUM(amount) as total_amount'),
    //         'currency',
    //         'status',
    //         'event_id',
    //         'payment_method_id',
    //         'payment_reference',
    //         'payment_proof_url',
    //         DB::raw('MAX(email) as email'),
    //         DB::raw('MAX(whatsapp) as whatsapp'),
    //         DB::raw('MAX(identificacion) as identificacion'),
    //         DB::raw('MAX(fullname) as fullname'),
    //         DB::raw('MAX(qr_code_url) as qr_code_url')
    //     )
    //         ->where('user_id', $userId)
    //         ->with([
    //             'event:id,name',
    //             'paymentMethod:id,name'
    //         ])
    //         ->groupBy(
    //             'transaction_id',
    //             'currency',
    //             'status',
    //             'event_id',
    //             'payment_method_id',
    //             'payment_reference',
    //             'payment_proof_url'
    //         )
    //         ->orderBy('created_at', 'desc')
    //         ->get()
    //         ->map(function ($group) {
    //             return $this->formatGroupedPurchase($group);
    //         });
    // }

    public function getGroupedUserPurchases($userId)
    {
        return Purchase::select(
            'transaction_id',
            DB::raw('MIN(id) as first_purchase_id'),
            DB::raw('MIN(created_at) as created_at'),
            DB::raw('COUNT(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN 1 END) as quantity'),
            DB::raw('SUM(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN amount ELSE 0 END) as total_amount'),
            'currency',
            DB::raw("CASE
            WHEN COUNT(CASE WHEN status = 'completed' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'completed'
            WHEN COUNT(CASE WHEN status = 'pending' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'pending'
            ELSE 'failed'
        END as status"),
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
            DB::raw('MAX(identificacion) as identificacion'),
            DB::raw('MAX(fullname) as fullname'),
            DB::raw('MAX(qr_code_url) as qr_code_url')
        )
            ->where('user_id', $userId)
            ->with([
                'event:id,name',
                'paymentMethod:id,name'
            ])
            ->groupBy(
                'transaction_id',
                'currency',
                'event_id',
                'payment_method_id',
                'payment_reference',
                'payment_proof_url'
            )
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($group) {
                return $this->formatGroupedPurchase($group);
            });
    }


    public function getPurchaseByTransaction(string $transactionId)
    {
        $group = Purchase::select(
            'transaction_id',
            DB::raw('MIN(id) as first_purchase_id'),
            DB::raw('MIN(created_at) as created_at'),
            // ✅ Contar solo tickets válidos (no rechazados Y no failed)
            DB::raw('COUNT(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN 1 END) as quantity'),
            // ✅ Sumar solo amounts de tickets válidos (no rechazados Y no failed)
            DB::raw('SUM(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN amount ELSE 0 END) as total_amount'),
            'currency',
            // ✅ Status basado en tickets válidos
            DB::raw("CASE
            WHEN COUNT(CASE WHEN status = 'completed' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'completed'
            WHEN COUNT(CASE WHEN status = 'pending' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'pending'
            ELSE 'failed'
        END as status"),
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            'user_id',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
            DB::raw('MAX(identificacion) as identificacion'),
            DB::raw('MAX(fullname) as fullname'),
            DB::raw('MAX(qr_code_url) as qr_code_url')
        )
            ->where('transaction_id', $transactionId)
            ->with([
                'event:id,name',
                'paymentMethod:id,name',
                'user:id,name,email'
            ])
            ->groupBy(
                'transaction_id',
                'currency',
                'event_id',
                'payment_method_id',
                'payment_reference',
                'payment_proof_url',
                'user_id'
            )
            ->first();

        if (!$group) {
            return null;
        }

        return $this->formatGroupedPurchase($group);
    }
    // ✅ ACTUALIZADO: Incluir identificacion en el SELECT
    public function getGroupedPurchasesByEvent(string $eventId)
    {
        return Purchase::select(
            'transaction_id',
            DB::raw('MIN(id) as first_purchase_id'),
            DB::raw('MIN(created_at) as created_at'),
            DB::raw('COUNT(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN 1 END) as quantity'),
            DB::raw('SUM(CASE WHEN (ticket_number NOT LIKE \'RECHAZADO%\' OR ticket_number IS NULL) AND status != \'failed\' THEN amount ELSE 0 END) as total_amount'),
            'currency',
            DB::raw("CASE
            WHEN COUNT(CASE WHEN status = 'completed' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'completed'
            WHEN COUNT(CASE WHEN status = 'pending' AND (ticket_number NOT LIKE 'RECHAZADO%' OR ticket_number IS NULL) THEN 1 END) > 0 THEN 'pending'
            ELSE 'failed'
        END as status"),
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            'user_id',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
            DB::raw('MAX(identificacion) as identificacion'),
            DB::raw('MAX(qr_code_url) as qr_code_url')
        )
            ->where('event_id', $eventId)
            ->with([
                'event:id,name',
                'paymentMethod:id,name',
                'user:id,name,email'
            ])
            ->groupBy(
                'transaction_id',
                'currency',
                'event_id',
                'payment_method_id',
                'payment_reference',
                'payment_proof_url',
                'user_id'
            )
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($group) {
                return $this->formatGroupedPurchase($group);
            });
    }


    // ====================================================================
    // MÉTODOS LEGACY (Para compatibilidad - DEPRECADOS)
    // ====================================================================

    /**
     * @deprecated Usar bulkInsertPurchases() en su lugar
     */
    public function createSinglePurchase(DTOsPurchase $data, $amount, $transactionId, $ticketNumber): Purchase
    {
        return Purchase::create($this->preparePurchaseRecord(
            $data,
            $amount,
            $transactionId,
            $ticketNumber,
            'pending'
        ));
    }

    /**
     * @deprecated Usar bulkInsertPurchases() en su lugar
     */
    public function createAdminPurchase(DTOsPurchase $data, $amount, $transactionId, $ticketNumber, $status): Purchase
    {
        return Purchase::create($this->preparePurchaseRecord(
            $data,
            $amount,
            $transactionId,
            $ticketNumber,
            $status
        ));
    }

    /**
     * @deprecated Usar bulkInsertPurchases() en su lugar
     */
    public function createAdminRandomPurchase(DTOsPurchase $data, $amount, $transactionId): Purchase
    {
        return Purchase::create($this->preparePurchaseRecord(
            $data,
            $amount,
            $transactionId,
            null,
            'pending'
        ));
    }

    // ====================================================================
    // MÉTODOS PRIVADOS HELPER
    // ====================================================================

    private function formatGroupedPurchase($group): array
    {
        $purchaseIds = Purchase::where('transaction_id', $group->transaction_id)
            ->pluck('id')
            ->toArray();

        // ✅ Solo tickets válidos (no rechazados Y no failed)
        $ticketNumbers = Purchase::where('transaction_id', $group->transaction_id)
            ->whereNotNull('ticket_number')
            ->where('ticket_number', 'NOT LIKE', 'RECHAZADO%')
            ->where('status', '!=', 'failed') // ✅ AGREGADO: Excluir failed
            ->orderBy('ticket_number', 'asc') // ✅ Ordenar los números
            ->pluck('ticket_number')
            ->toArray();

        $userData = null;
        if ($group->user) {
            $userData = [
                'id' => $group->user->id,
                'name' => $group->user->name,
                'email' => $group->user->email
            ];
        }

        $validQuantity = max(1, $group->quantity);

        return [
            'transaction_id' => $group->transaction_id,
            'event' => [
                'id' => $group->event->id,
                'name' => $group->event->name
            ],
            'user' => $userData,
            'fullname' => $group->fullname ?? null,
            'email' => $group->email,
            'whatsapp' => $group->whatsapp,
            'identificacion' => $group->identificacion ?? null,
            'quantity' => $group->quantity,
            'unit_price' => number_format($group->total_amount / $validQuantity, 2),
            'total_amount' => number_format($group->total_amount, 2),
            'currency' => $group->currency,
            'payment_method' => $group->paymentMethod->name ?? 'N/A',
            'payment_reference' => $group->payment_reference,
            'payment_proof' => $group->payment_proof_url,
            'qr_code_url' => $group->qr_code_url ?? null,
            'status' => $group->status,
            'ticket_numbers' => empty($ticketNumbers)
                ? 'Pendiente de asignación'
                : $ticketNumbers,
            'purchase_ids' => $purchaseIds,
            'created_at' => $group->created_at->toDateTimeString()
        ];
    }
    // ✅ ACTUALIZADO: Incluir identificacion en los filtros de búsqueda
    private function applyFilters($query, DTOsPurchaseFilter $filters)
    {
        if ($filters->getUserId()) {
            $query->where('user_id', $filters->getUserId());
        }

        if ($filters->getEventId()) {
            $query->where('event_id', $filters->getEventId());
        }

        if ($filters->getStatus() && $filters->isValidStatus()) {
            $query->where('status', $filters->getStatus());
        }

        if ($filters->getCurrency() && $filters->isValidCurrency()) {
            $query->where('currency', $filters->getCurrency());
        }

        if ($filters->getPaymentMethodId()) {
            $query->where('payment_method_id', $filters->getPaymentMethodId());
        }

        if ($filters->getTransactionId()) {
            $query->where('transaction_id', 'LIKE', '%' . $filters->getTransactionId() . '%');
        }

        // ✨ NUEVO: Filtro por número de ticket
        if ($filters->getTicketNumber()) {
            $query->where('ticket_number', 'LIKE', '%' . $filters->getTicketNumber() . '%');
        }

        if ($filters->getDateFrom()) {
            $query->whereDate('created_at', '>=', $filters->getDateFrom());
        }

        if ($filters->getDateTo()) {
            $query->whereDate('created_at', '<=', $filters->getDateTo());
        }

        if ($filters->getSearch()) {
            $search = $filters->getSearch();
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'LIKE', '%' . $search . '%')
                    ->orWhere('payment_reference', 'LIKE', '%' . $search . '%')
                    ->orWhere('email', 'LIKE', '%' . $search . '%')
                    ->orWhere('whatsapp', 'LIKE', '%' . $search . '%')
                    ->orWhere('identificacion', 'LIKE', '%' . $search . '%') // ✅ AGREGADO
                    ->orWhere('ticket_number', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'LIKE', '%' . $search . '%')
                            ->orWhere('email', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhereHas('event', function ($eventQuery) use ($search) {
                        $eventQuery->where('name', 'LIKE', '%' . $search . '%');
                    });
            });
        }
    }

    private function applySorting($query, DTOsPurchaseFilter $filters)
    {
        $sortBy = $filters->getSortBy();
        $sortOrder = $filters->getSortOrder();

        $allowedSorts = ['created_at', 'total_amount', 'quantity', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }
    }
    public function checkTicketAvailability(int $eventId, string $ticketNumber): array
    {
        $purchase = Purchase::getTicketBasicInfo($eventId, $ticketNumber);

        return [
            'available' => is_null($purchase),
            'purchase' => $purchase
        ];
    }
    // En PurchaseRepository.php

    public function rejectPurchaseAndFreeNumbers(string $transactionId, ?string $reason = null): int
    {
        DB::beginTransaction();

        try {
            $timestamp = now()->format('YmdHis');

            // 1. Obtener compras pendientes
            $purchasesToReject = Purchase::where('transaction_id', $transactionId)
                ->where('status', 'pending')
                ->get();

            if ($purchasesToReject->isEmpty()) {
                DB::rollBack();
                return 0;
            }

            $liberatedNumbers = [];
            $updatedIds = [];

            // 2. Preparar datos para cada compra
            foreach ($purchasesToReject as $index => $purchase) {
                $originalNumber = null;

                // Capturar número original si existe y no está rechazado
                if ($purchase->ticket_number && !str_starts_with($purchase->ticket_number, 'RECHAZADO')) {
                    $originalNumber = $purchase->ticket_number;
                    $liberatedNumbers[] = $originalNumber;
                }

                // Crear número de rechazo único
                if ($originalNumber) {
                    $rejectionTicketNumber = "RECHAZADO-{$originalNumber}-{$timestamp}-" . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $rejectionTicketNumber = "RECHAZADO-SIN_NUMERO-{$timestamp}-" . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                }

                // Agregar razón si existe
                if ($reason) {
                    $reasonSlug = Str::limit(Str::slug($reason), 20, '');
                    $rejectionTicketNumber .= "-{$reasonSlug}";
                }

                // 3. ✅ ACTUALIZAR USANDO QUERY BUILDER DIRECTO (más confiable)
                $affected = DB::table('purchases')
                    ->where('id', $purchase->id)
                    ->where('status', 'pending') // Double-check
                    ->update([
                        'status' => 'failed',
                        'ticket_number' => $rejectionTicketNumber,
                        'payment_reference' => $reason
                            ? "RECHAZADO: {$reason}"
                            : "RECHAZADO: Compra no aprobada",
                        'updated_at' => now()
                    ]);

                if ($affected > 0) {
                    $updatedIds[] = $purchase->id;
                }
            }

            DB::commit();

            Log::info('✅ Compras rechazadas exitosamente', [
                'transaction_id' => $transactionId,
                'liberated_numbers' => $liberatedNumbers,
                'total_rejected' => count($updatedIds),
                'updated_ids' => $updatedIds,
                'reason' => $reason,
                'timestamp' => $timestamp
            ]);

            return count($updatedIds);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Error rechazando compras', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function addTicketsToTransaction(DTOsAddTickets $dto): array
    {
        DB::beginTransaction();

        try {
            // 1. Obtener compra de referencia
            $referencePurchase = Purchase::where('transaction_id', $dto->getTransactionId())
                ->first();

            if (!$referencePurchase) {
                throw new \Exception("Transacción no encontrada: {$dto->getTransactionId()}");
            }

            $event = \App\Models\Event::findOrFail($referencePurchase->event_id);

            // 2. Determinar qué números agregar según el modo
            if ($dto->isSpecificMode()) {
                $ticketsToAdd = $this->prepareSpecificTickets($dto, $event, $referencePurchase->event_id);
            } else {
                $ticketsToAdd = $this->prepareRandomTickets($dto, $event);
            }

            // 3. Preparar registros para inserción
            $purchaseRecords = $this->preparePurchaseRecords(
                $ticketsToAdd,
                $referencePurchase,
                $dto->getTransactionId()
            );

            // 4. Insertar
            Purchase::insert($purchaseRecords);

            DB::commit();

            Log::info('✅ Tickets agregados a transacción', [
                'transaction_id' => $dto->getTransactionId(),
                'mode' => $dto->getMode(),
                'tickets_added' => $ticketsToAdd,
                'count' => count($ticketsToAdd)
            ]);

            return [
                'mode' => $dto->getMode(),
                'tickets_added' => $ticketsToAdd,
                'count' => count($ticketsToAdd)
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error agregando tickets a transacción', [
                'transaction_id' => $dto->getTransactionId(),
                'dto_data' => $dto->toArray(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ Preparar números específicos con validaciones
     */
    private function prepareSpecificTickets(DTOsAddTickets $dto, $event, int $eventId): array
    {
        $ticketsToAdd = $dto->getTicketNumbers();

        // Validar que están en rango
        foreach ($ticketsToAdd as $ticketNumber) {
            if ($ticketNumber < $event->start_number || $ticketNumber > $event->end_number) {
                throw new \Exception(
                    "El número {$ticketNumber} está fuera del rango válido " .
                        "({$event->start_number} - {$event->end_number})"
                );
            }
        }

        // Validar que están disponibles
        $reservedNumbers = $this->getReservedTicketNumbers($eventId, $ticketsToAdd);

        if (!empty($reservedNumbers)) {
            throw new \Exception(
                'Los siguientes números ya están reservados: ' . implode(', ', $reservedNumbers)
            );
        }

        return $ticketsToAdd;
    }

    /**
     * ✅ Preparar números aleatorios
     */
    private function prepareRandomTickets(DTOsAddTickets $dto, $event): array
    {
        $quantity = $dto->getQuantity();

        // Obtener números disponibles
        $usedNumbers = $this->getUsedTicketNumbers($event->id);
        $allNumbers = range($event->start_number, $event->end_number);
        $availableNumbers = array_diff($allNumbers, $usedNumbers);

        if (count($availableNumbers) < $quantity) {
            throw new \Exception(
                "No hay suficientes números disponibles. " .
                    "Disponibles: " . count($availableNumbers) . ", Solicitados: {$quantity}"
            );
        }

        // Seleccionar números aleatorios
        $availableNumbersArray = array_values($availableNumbers);
        $ticketsToAdd = [];

        for ($i = 0; $i < $quantity; $i++) {
            $randomIndex = array_rand($availableNumbersArray);
            $ticketsToAdd[] = $availableNumbersArray[$randomIndex];
            unset($availableNumbersArray[$randomIndex]);
            $availableNumbersArray = array_values($availableNumbersArray);
        }

        sort($ticketsToAdd); // Ordenar para mejor legibilidad

        return $ticketsToAdd;
    }

    /**
     * ✅ Preparar registros de compra clonando datos de referencia
     */
    private function preparePurchaseRecords(
        array $ticketsToAdd,
        $referencePurchase,
        string $transactionId
    ): array {
        $purchaseRecords = [];
        $now = now();

        foreach ($ticketsToAdd as $ticketNumber) {
            $purchaseRecords[] = [
                // ✅ Clonar TODOS los datos de la compra de referencia
                'event_id' => $referencePurchase->event_id,
                'event_price_id' => $referencePurchase->event_price_id,
                'payment_method_id' => $referencePurchase->payment_method_id,
                'user_id' => $referencePurchase->user_id,
                'fullname' => $referencePurchase->fullname,
                'email' => $referencePurchase->email,
                'whatsapp' => $referencePurchase->whatsapp,
                'identificacion' => $referencePurchase->identificacion,
                'currency' => $referencePurchase->currency,
                'amount' => $referencePurchase->amount,
                'total_amount' => $referencePurchase->amount,
                'quantity' => 1,
                'transaction_id' => $transactionId,
                'payment_reference' => $referencePurchase->payment_reference,
                'payment_proof_url' => $referencePurchase->payment_proof_url,
                'qr_code_url' => $referencePurchase->qr_code_url,
                'status' => $referencePurchase->status,
                'is_admin_purchase' => $referencePurchase->is_admin_purchase,

                // ✅ Solo cambia el número de ticket
                'ticket_number' => $ticketNumber,

                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $purchaseRecords;
    }

    /**
     * ✅ Quitar tickets de una transacción
     */
    public function removeTicketsFromTransaction(
        string $transactionId,
        array $ticketNumbersToRemove
    ): int {
        DB::beginTransaction();

        try {
            // 1. Obtener tickets actuales
            $currentTickets = $this->getTransactionTickets($transactionId);

            if (empty($currentTickets)) {
                throw new \Exception("No se encontraron tickets en esta transacción");
            }

            // 2. Validar que los números a remover existen
            $invalidTickets = array_diff($ticketNumbersToRemove, $currentTickets);

            if (!empty($invalidTickets)) {
                throw new \Exception(
                    "Los siguientes números no pertenecen a esta transacción: "
                        . implode(', ', $invalidTickets)
                );
            }

            // 3. Validar que no se eliminan TODOS los tickets
            $remainingTickets = array_diff($currentTickets, $ticketNumbersToRemove);

            if (empty($remainingTickets)) {
                throw new \Exception(
                    "No puedes eliminar todos los tickets de una transacción. " .
                        "Si deseas cancelar la compra completa, usa el método de rechazo."
                );
            }

            // 4. Marcar como rechazados
            $timestamp = now()->format('YmdHis');
            $affected = 0;

            foreach ($ticketNumbersToRemove as $index => $ticketNumber) {
                $rejectionTicketNumber = "RECHAZADO-{$ticketNumber}-{$timestamp}-" .
                    str_pad($index + 1, 3, '0', STR_PAD_LEFT) .
                    "-REMOVED";

                $updated = DB::table('purchases')
                    ->where('transaction_id', $transactionId)
                    ->where('ticket_number', $ticketNumber)
                    ->update([
                        'ticket_number' => $rejectionTicketNumber,
                        'status' => 'failed',
                        'payment_reference' => 'TICKET REMOVIDO DE LA TRANSACCIÓN',
                        'updated_at' => now()
                    ]);

                $affected += $updated;
            }

            DB::commit();

            Log::info('✅ Tickets removidos de transacción', [
                'transaction_id' => $transactionId,
                'removed_tickets' => $ticketNumbersToRemove,
                'count' => $affected,
                'remaining_tickets' => $remainingTickets
            ]);

            return $affected;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error removiendo tickets de transacción', [
                'transaction_id' => $transactionId,
                'tickets_to_remove' => $ticketNumbersToRemove ?? [],
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ Obtener tickets actuales de una transacción (sin rechazados)
     */
    public function getTransactionTickets(string $transactionId): array
    {
        return Purchase::where('transaction_id', $transactionId)
            ->whereNotNull('ticket_number')
            ->where('ticket_number', 'NOT LIKE', 'RECHAZADO%')
            ->orderBy('ticket_number', 'asc')
            ->pluck('ticket_number')
            ->toArray();
    }
}
