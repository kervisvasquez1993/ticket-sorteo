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
                    specific_numbers: null
                );

                $purchase = $this->PurchaseRepository->createPurchase($purchaseData, $eventPrice->amount);

                // Despachar job para asignar número
                AssignTicketNumberJob::dispatch($purchase->id, $specificNumber);

                $purchases[] = $purchase;
            }

            DB::commit();

            return [
                'success' => true,
                'data' => $purchases,
                'message' => 'Compra procesada. Los números se asignarán en breve.'
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
}
