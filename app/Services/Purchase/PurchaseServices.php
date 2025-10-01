<?php

namespace App\Services\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\Interfaces\Purchase\IPurchaseServices;
use App\Interfaces\Purchase\IPurchaseRepository;
use App\Jobs\AssignTicketNumberJob;
use App\Models\Event;
use App\Models\EventPrice;
use App\Models\Purchase;
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
            $results = $this->PurchaseRepository->getGroupedPurchases();
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Compras agrupadas obtenidas exitosamente'
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
            // Generar transaction_id único para agrupar las compras
            $transactionId = 'TXN-' . strtoupper(Str::random(12));

            // El total ya viene calculado del DTO
            $totalAmount = $data->getTotalAmount();

            $purchases = [];
            $specificNumbers = $data->getSpecificNumbers();
            for ($i = 0; $i < $data->getQuantity(); $i++) {
                $specificNumber = $specificNumbers[$i] ?? null;

                $purchaseData = new DTOsPurchase(
                    event_id: $data->getEventId(),
                    event_price_id: $data->getEventPriceId(),
                    payment_method_id: $data->getPaymentMethodId(),
                    quantity: 1,
                    currency: $data->getCurrency(),
                    user_id: $data->getUserId(),
                    specific_numbers: null, // NO asignar aún
                    payment_reference: $data->getPaymentReference(),
                    payment_proof_url: $data->getPaymentProofUrl(),
                    total_amount: $totalAmount
                );

                $purchase = $this->PurchaseRepository->createPurchase(
                    $purchaseData,
                    $eventPrice->amount,
                    $transactionId
                );
                $purchases[] = $purchase;
            }
            DB::commit();
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
                        'currency' => $data->getCurrency(),
                        'payment_method' => $purchases[0]->paymentMethod->name ?? 'N/A',
                        'payment_reference' => $data->getPaymentReference(),
                        'payment_proof' => $data->getPaymentProofUrl(),
                        'status' => 'pending', // ✅ Cambiar a pending
                        'created_at' => now()->toDateTimeString(),
                    ],
                    'ticket_numbers' => [
                        'status' => 'Los números se asignarán una vez se verifique el pago',
                        'requested_numbers' => $specificNumbers ?? 'Aleatorios',
                    ],
                    'purchase_ids' => array_column($purchases, 'id'),
                ],
                'message' => 'Compra registrada exitosamente. Estamos verificando tu pago y te notificaremos cuando sea aprobado.'
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

    public function approvePurchase(string $transactionId): array
    {
        try {
            DB::beginTransaction();

            // Obtener todas las compras de esta transacción
            $purchases = Purchase::where('transaction_id', $transactionId)->get();

            if ($purchases->isEmpty()) {
                throw new Exception('No se encontraron compras con este transaction_id');
            }

            // Verificar si ya fue aprobada
            $firstPurchase = $purchases->first();
            if (in_array($firstPurchase->status, ['completed', 'processing'])) {
                return [
                    'success' => false,
                    'message' => 'Esta transacción ya fue aprobada anteriormente.',
                    'data' => [
                        'transaction_id' => $transactionId,
                        'status' => $firstPurchase->status
                    ]
                ];
            }

            // Verificar que todas estén en pending
            $pendingPurchases = $purchases->where('status', 'pending');
            if ($pendingPurchases->isEmpty()) {
                throw new Exception('No se encontraron compras pendientes con este transaction_id');
            }

            $event = Event::findOrFail($firstPurchase->event_id);

            // Obtener números ya usados
            $usedNumbers = Purchase::where('event_id', $event->id)
                ->whereNotNull('ticket_number')
                ->pluck('ticket_number')
                ->toArray();

            // Crear rango completo de números disponibles
            $allNumbers = range($event->start_number, $event->end_number);
            $availableNumbers = array_diff($allNumbers, $usedNumbers);

            // Verificar disponibilidad
            if (count($availableNumbers) < $pendingPurchases->count()) {
                throw new Exception('No hay suficientes números disponibles para esta transacción.');
            }

            // Asignar números de forma síncrona
            $assignedNumbers = [];
            foreach ($pendingPurchases as $purchase) {
                if (empty($availableNumbers)) {
                    throw new Exception('Se agotaron los números disponibles durante la asignación.');
                }

                // Seleccionar número aleatorio
                $availableNumbersArray = array_values($availableNumbers);
                $randomIndex = array_rand($availableNumbersArray);
                $assignedNumber = $availableNumbersArray[$randomIndex];

                // Asignar número y cambiar status a completed
                $purchase->update([
                    'ticket_number' => $assignedNumber,
                    'status' => 'completed'
                ]);

                $assignedNumbers[] = $assignedNumber;

                // Remover el número asignado de los disponibles
                $availableNumbers = array_diff($availableNumbers, [$assignedNumber]);

                Log::info("Ticket number {$assignedNumber} assigned to purchase {$purchase->id}");
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Pago aprobado y números asignados exitosamente.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'purchases_count' => $pendingPurchases->count(),
                    'assigned_numbers' => $assignedNumbers,
                    'status' => 'completed'
                ]
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Error approving purchase: ' . $exception->getMessage());

            // Actualizar compras a failed si hubo error
            Purchase::where('transaction_id', $transactionId)
                ->where('status', 'processing')
                ->update(['status' => 'failed']);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function rejectPurchase(string $transactionId, string|null $reason = null): array
    {
        try {
            DB::beginTransaction();

            $purchases = Purchase::where('transaction_id', $transactionId)
                ->where('status', 'pending')
                ->get();

            if ($purchases->isEmpty()) {
                throw new Exception('No se encontraron compras pendientes con este transaction_id');
            }
            foreach ($purchases as $purchase) {
                $purchase->update([
                    'status' => 'failed',
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Compra rechazada correctamente.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'purchases_count' => $purchases->count(),
                    'reason' => $reason
                ]
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Error rejecting purchase: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }


    public function getUserPurchasesGrouped($userId)
    {
        try {
            $results = $this->PurchaseRepository->getGroupedUserPurchases($userId);
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Compras del usuario agrupadas obtenidas exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function getPurchaseByTransaction(string $transactionId)
    {
        try {
            $result = $this->PurchaseRepository->getPurchaseByTransaction($transactionId);

            if (!$result) {
                throw new Exception("No se encontró la transacción {$transactionId}");
            }

            return [
                'success' => true,
                'data' => $result,
                'message' => 'Transacción obtenida exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
    public function getPurchasesByEvent(string $eventId)
    {
        try {
            // Validar que el evento existe
            $event = Event::find($eventId);
            if (!$event) {
                throw new Exception("Evento no encontrado");
            }

            $results = $this->PurchaseRepository->getGroupedPurchasesByEvent($eventId);

            return [
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'status' => $event->status,
                    ],
                    'statistics' => $event->getStatistics(),
                    'purchases' => $results
                ],
                'message' => 'Compras del evento obtenidas exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}
