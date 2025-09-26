<?php

namespace App\Services\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\Interfaces\Purchase\IPurchaseServices;
use App\Interfaces\Purchase\IPurchaseRepository;
use App\Jobs\AssignTicketNumberJob;
use App\Models\Event;
use App\Models\EventPrice;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class PurchaseServices implements IPurchaseServices
{
    protected IPurchaseRepository $PurchaseRepository;

    public function __construct(IPurchaseRepository $PurchaseRepositoryInterface)
    {
        $this->PurchaseRepository = $PurchaseRepositoryInterface;
    }

    public function getAllPurchases()
    {
        try {
            $results = $this->PurchaseRepository->getAllPurchases();
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function getPurchaseById($id)
    {
        try {
            $results = $this->PurchaseRepository->getPurchaseById($id);
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function createPurchase(DTOsPurchase $data)
    {
        try {
            DB::beginTransaction();

            $event = Event::findOrFail($data->getEventId());
            $eventPrice = EventPrice::findOrFail($data->getEventPriceId());

            // Validar disponibilidad de números
            $availableCount = $this->getAvailableNumbersCount($event);

            if ($availableCount < $data->getQuantity()) {
                throw new Exception("Solo quedan {$availableCount} números disponibles.");
            }

            // Si hay números específicos, validar que no estén ocupados
            if ($data->getSpecificNumbers()) {
                $this->validateSpecificNumbers($event, $data->getSpecificNumbers());
            }

            // Generar transaction_id único para agrupar las compras
            $transactionId = 'TXN-' . strtoupper(Str::random(12));

            // Calcular total
            $totalAmount = $eventPrice->amount * $data->getQuantity();

            $purchases = [];
            $specificNumbers = $data->getSpecificNumbers();

            // Crear las compras sin números asignados
            for ($i = 0; $i < $data->getQuantity(); $i++) {
                $specificNumber = $specificNumbers[$i] ?? null;

                $purchaseData = new DTOsPurchase(
                    event_id: $data->getEventId(),
                    event_price_id: $data->getEventPriceId(),
                    payment_method_id: $data->getPaymentMethodId(),
                    quantity: 1,
                    currency: $eventPrice->currency,
                    user_id: $data->getUserId(),
                    specific_numbers: null,
                    payment_reference: $data->getPaymentReference(),
                    payment_proof_url: $data->getPaymentProofUrl(),
                );

                $purchase = $this->PurchaseRepository->createPurchase(
                    $purchaseData,
                    $eventPrice->amount,
                    $transactionId
                );

                // Despachar job para asignar número
                AssignTicketNumberJob::dispatch($purchase->id, $specificNumber);

                $purchases[] = $purchase;
            }

            DB::commit();

            // Respuesta mejorada con resumen
            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'summary' => [
                        'event_id' => $event->id,
                        'event_name' => $event->name,
                        'quantity' => $data->getQuantity(),
                        'unit_price' => number_format($eventPrice->amount, 2),
                        'total_amount' => number_format($totalAmount, 2),
                        'currency' => $eventPrice->currency,
                        'payment_method' => $purchases[0]->paymentMethod->name ?? 'N/A',
                        'payment_reference' => $data->getPaymentReference(),
                        'payment_proof' => $data->getPaymentProofUrl(),
                        'status' => 'processing',
                        'created_at' => now()->toDateTimeString(),
                    ],
                    'ticket_numbers' => [
                        'status' => 'Los números se están asignando',
                        'requested_numbers' => $specificNumbers ?? 'Aleatorios',
                    ],
                    'purchase_ids' => array_column($purchases, 'id'),
                ],
                'message' => 'Compra procesada exitosamente. Los números de ticket se asignarán en breve.'
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Error creating purchase: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function updatePurchase(DTOsPurchase $data, $id)
    {
        try {
            $Purchase = $this->PurchaseRepository->getPurchaseById($id);
            $results = $this->PurchaseRepository->updatePurchase($data, $Purchase);
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function deletePurchase($id)
    {
        try {
            $Purchase = $this->PurchaseRepository->getPurchaseById($id);
            $results = $this->PurchaseRepository->deletePurchase($Purchase);
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    /**
     * Obtener conteo de números disponibles
     */
    private function getAvailableNumbersCount(Event $event): int
    {
        $totalNumbers = ($event->end_number - $event->start_number) + 1;
        $usedNumbers = $event->purchases()->whereNotNull('ticket_number')->count();

        return $totalNumbers - $usedNumbers;
    }

    /**
     * Validar números específicos
     */
    private function validateSpecificNumbers(Event $event, array $numbers): void
    {
        $usedNumbers = $event->purchases()
            ->whereIn('ticket_number', $numbers)
            ->pluck('ticket_number')
            ->toArray();

        if (!empty($usedNumbers)) {
            $usedList = implode(', ', $usedNumbers);
            throw new Exception("Los siguientes números ya están ocupados: {$usedList}");
        }
    }

    /**
     * Obtener compras del usuario autenticado
     */
    public function getUserPurchases($userId)
    {
        try {
            $results = $this->PurchaseRepository->getUserPurchases($userId);
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
    public function getPurchaseSummary($transactionId)
    {
        try {
            $purchases = $this->PurchaseRepository->getPurchasesByTransaction($transactionId);

            if ($purchases->isEmpty()) {
                throw new Exception("No se encontró la transacción {$transactionId}");
            }

            $first = $purchases->first();
            $totalAmount = $purchases->sum('amount');
            $ticketNumbers = $purchases->whereNotNull('ticket_number')->pluck('ticket_number')->toArray();

            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'event' => [
                        'id' => $first->event->id,
                        'name' => $first->event->name,
                    ],
                    'quantity' => $purchases->count(),
                    'total_amount' => number_format($totalAmount, 2),
                    'currency' => $first->currency,
                    'payment_method' => $first->paymentMethod->name,
                    'payment_reference' => $first->payment_reference,
                    'payment_proof' => $first->payment_proof_url,
                    'ticket_numbers' => $ticketNumbers,
                    'status' => $purchases->first()->status,
                    'created_at' => $first->created_at->toDateTimeString(),
                ]
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}
