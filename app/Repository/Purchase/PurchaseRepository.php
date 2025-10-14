<?php

namespace App\Repository\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\DTOs\Purchase\DTOsPurchaseFilter;
use App\Interfaces\Purchase\IPurchaseRepository;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

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
            'email' => $data->getEmail(),
            'whatsapp' => $data->getWhatsapp(),
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
    public function getReservedTicketNumbers(int $eventId, array $ticketNumbers): array
    {
        return Purchase::where('event_id', $eventId)
            ->whereIn('ticket_number', $ticketNumbers)
            ->lockForUpdate()
            ->pluck('ticket_number')
            ->toArray();
    }

    /**
     * ✅ OPTIMIZADO: Obtener números usados de un evento
     */
    public function getUsedTicketNumbers(int $eventId): array
    {
        return Purchase::where('event_id', $eventId)
            ->whereNotNull('ticket_number')
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
            DB::raw('COUNT(*) as quantity'),
            DB::raw('SUM(amount) as total_amount'),
            'currency',
            'status',
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            'user_id',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
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
                'status',
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
            DB::raw('COUNT(*) as quantity'),
            DB::raw('SUM(amount) as total_amount'),
            'currency',
            'status',
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            'user_id',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
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
                'status',
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

    public function getGroupedUserPurchases($userId)
    {
        return Purchase::select(
            'transaction_id',
            DB::raw('MIN(id) as first_purchase_id'),
            DB::raw('MIN(created_at) as created_at'),
            DB::raw('COUNT(*) as quantity'),
            DB::raw('SUM(amount) as total_amount'),
            'currency',
            'status',
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
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
                'status',
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
            DB::raw('COUNT(*) as quantity'),
            DB::raw('SUM(amount) as total_amount'),
            'currency',
            'status',
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            'user_id',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
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
                'status',
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

    public function getGroupedPurchasesByEvent(string $eventId)
    {
        return Purchase::select(
            'transaction_id',
            DB::raw('MIN(id) as first_purchase_id'),
            DB::raw('MIN(created_at) as created_at'),
            DB::raw('COUNT(*) as quantity'),
            DB::raw('SUM(amount) as total_amount'),
            'currency',
            'status',
            'event_id',
            'payment_method_id',
            'payment_reference',
            'payment_proof_url',
            'user_id',
            DB::raw('MAX(email) as email'),
            DB::raw('MAX(whatsapp) as whatsapp'),
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
                'status',
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

        $ticketNumbers = Purchase::where('transaction_id', $group->transaction_id)
            ->whereNotNull('ticket_number')
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

        return [
            'transaction_id' => $group->transaction_id,
            'event' => [
                'id' => $group->event->id,
                'name' => $group->event->name
            ],
            'user' => $userData,
            'email' => $group->email,
            'whatsapp' => $group->whatsapp,
            'quantity' => $group->quantity,
            'unit_price' => number_format($group->total_amount / $group->quantity, 2),
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
}
