<?php

namespace App\Jobs;

use Exception;
use App\Models\Event;
use App\Models\EventPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Interfaces\Purchase\IPurchaseRepository;
use App\Jobs\SendPurchaseNotificationJob;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;

class ProcessMassivePurchaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 3000;
    public $failOnTimeout = true;

    protected array $purchaseData;
    protected string $transactionId;
    protected bool $autoApprove;
    protected string $prefix;

    public function __construct(
        array $purchaseData,
        string $transactionId,
        bool $autoApprove = true,
        string $prefix = 'MASSIVE'
    ) {
        $this->purchaseData = $purchaseData;
        $this->transactionId = $transactionId;
        $this->autoApprove = $autoApprove;
        $this->prefix = $prefix;
        $this->onQueue('massive-purchases');
    }

    public function handle(IPurchaseRepository $purchaseRepository): void
    {
        Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log::info("[{$this->prefix}] 🚀 INICIANDO PROCESAMIENTO DE COMPRA MASIVA", [
            'transaction_id' => $this->transactionId,
            'quantity' => $this->purchaseData['quantity'],
            'event_id' => $this->purchaseData['event_id'],
            'auto_approve' => $this->autoApprove
        ]);
        Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            DB::beginTransaction();

            // 1. Validar evento y precio
            $event = Event::findOrFail($this->purchaseData['event_id']);
            $eventPrice = EventPrice::findOrFail($this->purchaseData['event_price_id']);

            Log::info("[{$this->prefix}] ✅ Evento validado: {$event->name}");

            // 2. ⚠️ CRÍTICO: Obtener números disponibles ANTES de insertar
            $availableNumbers = $this->getAvailableTicketNumbers($event, $purchaseRepository);
            $requestedQuantity = $this->purchaseData['quantity'];

            if (count($availableNumbers) < $requestedQuantity) {
                throw new Exception(
                    "No hay suficientes números disponibles. Solicitados: {$requestedQuantity}, " .
                    "Disponibles: " . count($availableNumbers)
                );
            }

            Log::info("[{$this->prefix}] ✅ Números disponibles verificados: " . count($availableNumbers));

            // 3. Determinar status inicial
            $initialStatus = $this->autoApprove ? 'completed' : 'pending';

            // 4. Crear registros en lotes CON números asignados
            $batchSize = 500;
            $totalInserted = 0;
            $assignedNumbers = array_slice($availableNumbers, 0, $requestedQuantity);

            Log::info("[{$this->prefix}] 🔄 Insertando {$requestedQuantity} registros en lotes de {$batchSize}");

            for ($i = 0; $i < $requestedQuantity; $i += $batchSize) {
                $currentBatchSize = min($batchSize, $requestedQuantity - $i);
                $batchNumbers = array_slice($assignedNumbers, $i, $currentBatchSize);

                $purchaseRecords = [];
                foreach ($batchNumbers as $ticketNumber) {
                    $purchaseRecords[] = $this->preparePurchaseRecord(
                        $eventPrice->amount,
                        $initialStatus,
                        $ticketNumber // ✅ ASIGNAR NÚMERO
                    );
                }

                $purchaseRepository->bulkInsertPurchases($purchaseRecords);
                $totalInserted += $currentBatchSize;

                Log::info("[{$this->prefix}] ✅ Lote insertado. Progreso: {$totalInserted}/{$requestedQuantity}");

                unset($purchaseRecords);
                gc_collect_cycles();
            }

            // 5. Generar QR Code
            $qrImageUrl = $this->generatePurchaseQRCode();
            if ($qrImageUrl) {
                $purchaseRepository->updateQrCodeByTransaction($this->transactionId, $qrImageUrl);
            }

            DB::commit();

            Log::info("[{$this->prefix}] 🎉 COMPRA MASIVA COMPLETADA: {$totalInserted} tickets con números asignados");

            // 6. Enviar notificación
            $this->dispatchNotification($eventPrice->amount * $requestedQuantity, $event);

        } catch (Exception $exception) {
            DB::rollBack();

            Log::error("[{$this->prefix}] ❌ ERROR", [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            throw $exception;
        }
    }

    /**
     * ✅ MÉTODO CLAVE: Obtener números disponibles del evento
     */
    private function getAvailableTicketNumbers(Event $event, IPurchaseRepository $repository): array
    {
        // Obtener todos los números usados del evento
        $usedNumbers = $repository->getUsedTicketNumbers($event->id);

        // Generar rango completo de números
        $allNumbers = range($event->start_number, $event->end_number);

        // Filtrar números disponibles
        $availableNumbers = array_values(array_diff($allNumbers, $usedNumbers));

        // Mezclar aleatoriamente si el evento lo requiere
        if ($event->random_assignment ?? true) {
            shuffle($availableNumbers);
        }

        return $availableNumbers;
    }

    /**
     * ✅ MODIFICADO: Ahora incluye ticket_number
     */
    private function preparePurchaseRecord(float $amount, string $status, string $ticketNumber): array
    {
        return [
            'event_id' => $this->purchaseData['event_id'],
            'event_price_id' => $this->purchaseData['event_price_id'],
            'payment_method_id' => $this->purchaseData['payment_method_id'],
            'user_id' => $this->purchaseData['user_id'] ?? null,
            'email' => $this->purchaseData['email'] ?? null,
            'whatsapp' => $this->purchaseData['whatsapp'] ?? null,
            'identificacion' => $this->purchaseData['identificacion'] ?? null,
            'currency' => $this->purchaseData['currency'],
            'amount' => $amount,
            'total_amount' => $amount,
            'quantity' => 1,
            'ticket_number' => $ticketNumber, // ✅ NÚMERO ASIGNADO
            'transaction_id' => $this->transactionId,
            'payment_reference' => $this->purchaseData['payment_reference'] ?? null,
            'payment_proof_url' => $this->purchaseData['payment_proof_url'] ?? null,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function generatePurchaseQRCode(): ?string
    {
        try {
            $frontendUrl = config('app.frontend_url', 'https://tu-frontend.com');
            $purchaseUrl = "{$frontendUrl}/my-purchase/{$this->transactionId}";

            $qrImage = QrCodeGenerator::format('png')
                ->size(400)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($purchaseUrl);

            $fileName = "purchase-qr/{$this->prefix}-{$this->transactionId}_" . time() . '.png';

            $uploaded = Storage::disk('s3')->put($fileName, $qrImage, [
                'visibility' => 'public',
                'ContentType' => 'image/png'
            ]);

            if ($uploaded) {
                return "https://backend-imagen-br.s3.us-east-2.amazonaws.com/" . $fileName;
            }

            return null;
        } catch (\Exception $e) {
            Log::error("[{$this->prefix}] Error generando QR code", [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function dispatchNotification(float $totalAmount, Event $event): void
    {
        try {
            SendPurchaseNotificationJob::dispatch([
                'transaction_id' => $this->transactionId,
                'quantity' => $this->purchaseData['quantity'],
                'total_amount' => $totalAmount,
                'client_email' => $this->purchaseData['email'] ?? null,
                'client_whatsapp' => $this->purchaseData['whatsapp'] ?? null,
                'event_id' => $event->id,
                'event_name' => $event->name,
            ], 'massive');

            Log::info("[{$this->prefix}] ✅ Notificación despachada");
        } catch (\Exception $e) {
            Log::warning("[{$this->prefix}] ⚠️ No se pudo despachar notificación", [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log::critical("[{$this->prefix}] 💀 JOB FALLIDO DESPUÉS DE {$this->tries} INTENTOS", [
            'transaction_id' => $this->transactionId,
            'quantity' => $this->purchaseData['quantity'],
            'event_id' => $this->purchaseData['event_id'],
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);
        Log::critical("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }
}
