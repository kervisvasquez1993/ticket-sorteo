<?php

namespace App\Repository\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
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

    /**
     * Obtener compras de un usuario específico
     */
    public function getUserPurchases($userId)
    {
        return Purchase::with(['event', 'eventPrice', 'paymentMethod'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener compras por evento
     */
    public function getPurchasesByEvent($eventId)
    {
        return Purchase::with(['user', 'eventPrice', 'paymentMethod'])
            ->where('event_id', $eventId)
            ->orderBy('ticket_number', 'asc')
            ->get();
    }

    /**
     * Verificar si un número está disponible
     */
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
    public function getGroupedPurchases()
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
            'user_id'
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
            )
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($group) {
                // Obtener los IDs de todas las compras del grupo
                $purchaseIds = Purchase::where('transaction_id', $group->transaction_id)
                    ->pluck('id')
                    ->toArray();

                // Obtener los números de ticket asignados
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
                    'user' => [
                        'id' => $group->user->id,
                        'name' => $group->user->name,
                        'email' => $group->user->email
                    ],
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

    /**
     * Obtener compras agrupadas del usuario
     */
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
            'payment_proof_url'
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
            'user_id'
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

        // Obtener los IDs de todas las compras del grupo
        $purchaseIds = Purchase::where('transaction_id', $group->transaction_id)
            ->pluck('id')
            ->toArray();

        // Obtener los números de ticket asignados
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
            'user' => [
                'id' => $group->user->id,
                'name' => $group->user->name,
                'email' => $group->user->email
            ],
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
            'user_id'
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
                // Obtener los IDs de todas las compras del grupo
                $purchaseIds = Purchase::where('transaction_id', $group->transaction_id)
                    ->pluck('id')
                    ->toArray();

                // Obtener los números de ticket asignados
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
                    'user' => [
                        'id' => $group->user->id,
                        'name' => $group->user->name,
                        'email' => $group->user->email
                    ],
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
}
