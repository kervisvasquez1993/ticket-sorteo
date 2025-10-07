<?php

namespace App\Repository\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\DTOs\Purchase\DTOsPurchaseFilter;
use App\Interfaces\Purchase\IPurchaseRepository;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

class PurchaseRepository implements IPurchaseRepository
{
    public function getAllPurchases()
    {
        $Purchases = Purchase::with(['user', 'event', 'eventPrice', 'paymentMethod'])
            ->orderBy('created_at', 'desc')
            ->get();
        return $Purchases;
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

    public function isNumberAvailable($eventId, $ticketNumber): bool
    {
        return !Purchase::where('event_id', $eventId)
            ->where('ticket_number', $ticketNumber)
            ->exists();
    }

    public function getPurchasesByTransaction($transactionId)
    {
        return Purchase::with(['event', 'eventPrice', 'paymentMethod', 'user'])
            ->where('transaction_id', $transactionId)
            ->get();
    }

    public function getGroupedPurchases(?DTOsPurchaseFilter $filters = null)
    {
        $query = Purchase::query();

        if ($filters) {
            $this->applyFilters($query, $filters);
        }

        $results = $query->select(
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
            DB::raw('MAX(email) as email'),        // ✅ Agregar email
            DB::raw('MAX(whatsapp) as whatsapp')   // ✅ Agregar whatsapp
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
            $sortBy = $filters->getSortBy();
            $sortOrder = $filters->getSortOrder();

            if ($sortBy === 'created_at') {
                $query->orderBy('created_at', $sortOrder);
            } elseif ($sortBy === 'total_amount') {
                $query->orderBy('total_amount', $sortOrder);
            } elseif ($sortBy === 'quantity') {
                $query->orderBy('quantity', $sortOrder);
            } elseif ($sortBy === 'status') {
                $query->orderBy('status', $sortOrder);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $filters ? $filters->getPerPage() : 15;
        $page = $filters ? $filters->getPage() : 1;

        $paginatedResults = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginatedResults->map(function ($group) {
                $purchaseIds = Purchase::where('transaction_id', $group->transaction_id)
                    ->pluck('id')
                    ->toArray();

                $ticketNumbers = Purchase::where('transaction_id', $group->transaction_id)
                    ->whereNotNull('ticket_number')
                    ->pluck('ticket_number')
                    ->toArray();

                // ✅ Manejar user null para compras de invitados
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
                    'email' => $group->email,           // ✅ Email del comprador
                    'whatsapp' => $group->whatsapp,     // ✅ WhatsApp del comprador
                    'quantity' => $group->quantity,
                    'unit_price' => number_format($group->total_amount / $group->quantity, 2),
                    'total_amount' => number_format($group->total_amount, 2),
                    'currency' => $group->currency,
                    'payment_method' => $group->paymentMethod->name ?? 'N/A',
                    'payment_reference' => $group->payment_reference,
                    'payment_proof' => $group->payment_proof_url,
                    'qr_code_url' => $group->qr_code_url,
                    'status' => $group->status,
                    'ticket_numbers' => empty($ticketNumbers) ?
                        'Pendiente de asignación' : $ticketNumbers,
                    'purchase_ids' => $purchaseIds,
                    'created_at' => $group->created_at->toDateTimeString()
                ];
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

        // ✅ Búsqueda mejorada incluyendo email y whatsapp
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
            DB::raw('MAX(whatsapp) as whatsapp')
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
                $purchaseIds = Purchase::where('transaction_id', $group->transaction_id)
                    ->pluck('id')
                    ->toArray();

                $ticketNumbers = Purchase::where('transaction_id', $group->transaction_id)
                    ->whereNotNull('ticket_number')
                    ->pluck('ticket_number')
                    ->toArray();

                return [
                    'transaction_id' => $group->transaction_id,
                    'event' => [
                        'id' => $group->event->id,
                        'name' => $group->event->name
                    ],
                    'email' => $group->email,
                    'whatsapp' => $group->whatsapp,
                    'quantity' => $group->quantity,
                    'unit_price' => number_format($group->total_amount / $group->quantity, 2),
                    'total_amount' => number_format($group->total_amount, 2),
                    'currency' => $group->currency,
                    'payment_method' => $group->paymentMethod->name ?? 'N/A',
                    'payment_reference' => $group->payment_reference,
                    'payment_proof' => $group->payment_proof_url,
                    'status' => $group->status,
                    'ticket_numbers' => empty($ticketNumbers) ?
                        'Pendiente de asignación' : $ticketNumbers,
                    'purchase_ids' => $purchaseIds,
                    'created_at' => $group->created_at->toDateTimeString()
                ];
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
            DB::raw('MAX(qr_code_url) as qr_code_url')  // ✅ Agregar QR
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

        $purchaseIds = Purchase::where('transaction_id', $group->transaction_id)
            ->pluck('id')
            ->toArray();

        $ticketNumbers = Purchase::where('transaction_id', $group->transaction_id)
            ->whereNotNull('ticket_number')
            ->pluck('ticket_number')
            ->toArray();

        // ✅ Manejar user null
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
            'qr_code_url' => $group->qr_code_url,  // ✅ Incluir QR
            'status' => $group->status,
            'ticket_numbers' => empty($ticketNumbers) ?
                'Pendiente de asignación' : $ticketNumbers,
            'purchase_ids' => $purchaseIds,
            'created_at' => $group->created_at->toDateTimeString()
        ];
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
            DB::raw('MAX(whatsapp) as whatsapp')
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
                $purchaseIds = Purchase::where('transaction_id', $group->transaction_id)
                    ->pluck('id')
                    ->toArray();

                $ticketNumbers = Purchase::where('transaction_id', $group->transaction_id)
                    ->whereNotNull('ticket_number')
                    ->pluck('ticket_number')
                    ->toArray();

                // ✅ Manejar user null
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
                    'status' => $group->status,
                    'ticket_numbers' => empty($ticketNumbers) ?
                        'Pendiente de asignación' : $ticketNumbers,
                    'purchase_ids' => $purchaseIds,
                    'created_at' => $group->created_at->toDateTimeString()
                ];
            });
    }

    public function createSinglePurchase(DTOsPurchase $data, $amount, $transactionId, $ticketNumber): Purchase
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
            'status' => 'pending', // ✅ Status pending
            'ticket_number' => $ticketNumber, // ✅ Número ya asignado
            'transaction_id' => $transactionId,
            'payment_reference' => $data->getPaymentReference(),
            'payment_proof_url' => $data->getPaymentProofUrl(),
            'quantity' => 1,
            'total_amount' => $data->getTotalAmount(),
        ];

        return Purchase::create($purchaseData);
    }
    public function createAdminPurchase(DTOsPurchase $data, $amount, $transactionId, $ticketNumber, $status): Purchase
    {
        $purchaseData = [
            'user_id' => $data->getUserId(), // ✅ ID del admin
            'email' => $data->getEmail(),
            'whatsapp' => $data->getWhatsapp(),
            'event_id' => $data->getEventId(),
            'event_price_id' => $data->getEventPriceId(),
            'payment_method_id' => $data->getPaymentMethodId(),
            'amount' => $amount,
            'currency' => $data->getCurrency(),
            'status' => $status, // ✅ 'pending' o 'completed'
            'ticket_number' => $ticketNumber, // ✅ Número ya asignado
            'transaction_id' => $transactionId,
            'payment_reference' => $data->getPaymentReference(),
            'payment_proof_url' => $data->getPaymentProofUrl(), // ✅ Puede ser null
            'quantity' => 1,
            'total_amount' => $data->getTotalAmount(),
        ];

        return Purchase::create($purchaseData);
    }
    public function createAdminRandomPurchase(DTOsPurchase $data, $amount, $transactionId): Purchase
    {
        $purchaseData = [
            'user_id' => $data->getUserId(), // ✅ ID del admin
            'email' => $data->getEmail(),
            'whatsapp' => $data->getWhatsapp(),
            'event_id' => $data->getEventId(),
            'event_price_id' => $data->getEventPriceId(),
            'payment_method_id' => $data->getPaymentMethodId(),
            'amount' => $amount,
            'currency' => $data->getCurrency(),
            'status' => 'pending', // ✅ Siempre pending, luego se aprueba
            'ticket_number' => null, // ✅ Sin número asignado aún
            'transaction_id' => $transactionId,
            'payment_reference' => $data->getPaymentReference(),
            'payment_proof_url' => $data->getPaymentProofUrl(),
            'quantity' => 1,
            'total_amount' => $data->getTotalAmount(),
        ];

        return Purchase::create($purchaseData);
    }
}
