<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventPrice;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessTicketPurchaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [10, 30, 60]; // Reintentos con espera incremental

    public function __construct(
        private int $eventId,
        private int $userId,
        private int $eventPriceId,
        private int $paymentMethodId,
        private ?string $transactionId,
        private string $batchId
    ) {}

    public function handle(): void
    {
        try {
            DB::transaction(function () {
                // 1. Obtener el evento con lock para evitar race conditions
                $event = Event::lockForUpdate()->findOrFail($this->eventId);

                // 2. Validar que el evento esté activo
                if ($event->status !== 'active') {
                    throw new \Exception('El evento no está activo');
                }

                // 3. Validar fechas
                if (now()->lt($event->start_date) || now()->gt($event->end_date)) {
                    throw new \Exception('El evento no está en periodo de compra');
                }

                // 4. Obtener el precio y validar que pertenece al evento
                $eventPrice = EventPrice::where('id', $this->eventPriceId)
                    ->where('event_id', $this->eventId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (!$eventPrice) {
                    throw new \Exception("El precio seleccionado no es válido o no está activo");
                }

                // 5. Validar método de pago
                $paymentMethod = PaymentMethod::where('id', $this->paymentMethodId)
                    ->where('is_active', true)
                    ->first();

                if (!$paymentMethod) {
                    throw new \Exception("El método de pago no es válido o no está activo");
                }

                // 6. Obtener número disponible aleatorio
                $ticketNumber = $this->getAvailableRandomNumber($event);

                if ($ticketNumber === null) {
                    throw new \Exception('No hay números disponibles');
                }

                // 7. Crear la compra
                $purchase = Purchase::create([
                    'user_id' => $this->userId,
                    'event_id' => $this->eventId,
                    'event_price_id' => $this->eventPriceId,
                    'payment_method_id' => $this->paymentMethodId,
                    'ticket_number' => $ticketNumber,
                    'amount' => $eventPrice->amount,
                    'currency' => $eventPrice->currency,
                    'status' => 'completed',
                    'transaction_id' => $this->transactionId ? $this->transactionId . '-' . $this->batchId : $this->batchId,
                ]);

                Log::info("Ticket comprado exitosamente", [
                    'purchase_id' => $purchase->id,
                    'user_id' => $this->userId,
                    'event_id' => $this->eventId,
                    'ticket_number' => $ticketNumber,
                    'batch_id' => $this->batchId,
                    'amount' => $eventPrice->amount,
                    'currency' => $eventPrice->currency
                ]);
            }, 5); // 5 intentos de transacción
        } catch (\Exception $e) {
            Log::error("Error al procesar compra de ticket", [
                'user_id' => $this->userId,
                'event_id' => $this->eventId,
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function getAvailableRandomNumber(Event $event): ?int
    {
        // Obtener números ya usados con lock
        $usedNumbers = Purchase::where('event_id', $event->id)
            ->lockForUpdate()
            ->pluck('ticket_number')
            ->toArray();

        // Crear rango completo
        $allNumbers = range($event->start_number, $event->end_number);

        // Obtener disponibles
        $availableNumbers = array_diff($allNumbers, $usedNumbers);

        if (empty($availableNumbers)) {
            return null;
        }

        // Seleccionar uno aleatorio
        $availableNumbers = array_values($availableNumbers);
        return $availableNumbers[array_rand($availableNumbers)];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job de compra de ticket falló completamente", [
            'user_id' => $this->userId,
            'event_id' => $this->eventId,
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Aquí podrías enviar una notificación al usuario
        // o guardar en una tabla de errores para revisión manual
    }
}
