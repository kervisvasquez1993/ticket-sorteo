<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignTicketNumberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    protected $purchaseId;
    protected $specificNumber;

    /**
     * Create a new job instance.
     */
    public function __construct(int $purchaseId, ?int $specificNumber = null)
    {
        $this->purchaseId = $purchaseId;
        $this->specificNumber = $specificNumber;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $purchase = Purchase::find($this->purchaseId);

        if (!$purchase) {
            Log::error("Purchase not found: {$this->purchaseId}");
            return;
        }

        $event = $purchase->event;
        $lockKey = "event_number_assignment_{$event->id}";

        // Lock para evitar condiciones de carrera
        $lock = Cache::lock($lockKey, 10);

        try {
            if ($lock->get()) {
                DB::transaction(function () use ($purchase, $event) {
                    // Si hay un número específico, intentar asignarlo
                    if ($this->specificNumber !== null) {
                        $exists = Purchase::where('event_id', $event->id)
                            ->where('ticket_number', $this->specificNumber)
                            ->exists();

                        if ($exists) {
                            throw new \Exception("El número {$this->specificNumber} ya está asignado.");
                        }

                        $assignedNumber = $this->specificNumber;
                    } else {
                        // Asignar número aleatorio
                        $assignedNumber = $this->assignRandomNumber($event);
                    }

                    // Actualizar purchase con el número asignado
                    $purchase->update([
                        'ticket_number' => $assignedNumber,
                        'status' => 'pending', // o 'completed' según tu lógica
                    ]);

                    Log::info("Ticket number {$assignedNumber} assigned to purchase {$purchase->id}");
                });
            } else {
                // Si no se puede obtener el lock, reintentar el job
                $this->release(2);
            }
        } catch (\Exception $e) {
            Log::error("Error assigning ticket number: " . $e->getMessage());

            // Marcar la compra como fallida
            $purchase->update([
                'status' => 'failed',
            ]);

            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Asignar número aleatorio disponible
     */
    private function assignRandomNumber(Event $event): int
    {
        // Obtener números ya usados
        $usedNumbers = Purchase::where('event_id', $event->id)
            ->whereNotNull('ticket_number')
            ->pluck('ticket_number')
            ->toArray();

        // Crear rango completo
        $allNumbers = range($event->start_number, $event->end_number);

        // Excluir los usados
        $availableNumbers = array_diff($allNumbers, $usedNumbers);

        if (empty($availableNumbers)) {
            throw new \Exception('No hay números disponibles para este evento.');
        }

        // Seleccionar número aleatorio
        $randomNumber = $availableNumbers[array_rand($availableNumbers)];

        return $randomNumber;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("AssignTicketNumberJob failed for purchase {$this->purchaseId}: " . $exception->getMessage());

        // Actualizar el estado de la compra
        Purchase::where('id', $this->purchaseId)->update([
            'status' => 'failed',
        ]);
    }
}
