<?php

namespace App\Jobs;

use Exception;
use App\Models\Event;
use App\Models\Purchase;
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
    protected bool $isAdminPurchase;

    public function __construct(
        array $purchaseData,
        string $transactionId,
        bool $autoApprove = true,
        string $prefix = 'MASSIVE',
        bool $isAdminPurchase = true
    ) {
        $this->purchaseData = $purchaseData;
        $this->transactionId = $transactionId;
        $this->autoApprove = $autoApprove;
        $this->prefix = $prefix;
        $this->isAdminPurchase = $isAdminPurchase;
        $this->onQueue('massive-purchases');
    }

    public function handle(IPurchaseRepository $purchaseRepository): void
    {
        Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log::info("[{$this->prefix}] 🚀 INICIANDO PROCESAMIENTO DE COMPRA MASIVA", [
            'transaction_id' => $this->transactionId,
            'quantity' => $this->purchaseData['quantity'],
            'event_id' => $this->purchaseData['event_id'],
            'auto_approve' => $this->autoApprove,
            'is_admin_purchase' => $this->isAdminPurchase
        ]);
        Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $assignedNumbers = [];
        $totalInserted = 0;
        $batchSize = 500;
        $requestedQuantity = $this->purchaseData['quantity'];

        try {
            // 1. Validar evento y precio (FUERA de transacción)
            $event = Event::findOrFail($this->purchaseData['event_id']);
            $eventPrice = EventPrice::findOrFail($this->purchaseData['event_price_id']);

            Log::info("[{$this->prefix}] ✅ Evento validado: {$event->name}");

            // 2. Determinar status y monto
            $initialStatus = $this->autoApprove ? 'completed' : 'pending';
            $unitAmount = $this->isAdminPurchase ? 0.00 : $eventPrice->amount;

            Log::info("[{$this->prefix}] 💰 Monto por ticket: " .
                ($this->isAdminPurchase ? '$0.00 (Compra Administrativa)' : "\${$unitAmount}"));

            // 3. ✅ PROCESAR EN LOTES CON TRANSACCIÓN POR LOTE
            for ($i = 0; $i < $requestedQuantity; $i += $batchSize) {
                $currentBatchSize = min($batchSize, $requestedQuantity - $i);

                // ✅ NUEVA TRANSACCIÓN POR CADA LOTE
                DB::beginTransaction();

                try {
                    // ✅ Obtener números disponibles CON LOCK DENTRO de la transacción
                    $availableNumbers = $this->getAvailableTicketNumbersLocked($event, $currentBatchSize);

                    if (count($availableNumbers) < $currentBatchSize) {
                        DB::rollBack();
                        throw new Exception(
                            "No hay suficientes números disponibles en el lote {$i}. " .
                            "Necesarios: {$currentBatchSize}, Disponibles: " . count($availableNumbers) .
                            ". Total insertado hasta ahora: {$totalInserted}"
                        );
                    }

                    // ✅ Tomar solo los números necesarios
                    $batchNumbers = array_slice($availableNumbers, 0, $currentBatchSize);

                    // ✅ Preparar registros CON FORMATEO MANUAL
                    $purchaseRecords = [];
                    foreach ($batchNumbers as $ticketNumber) {
                        $purchaseRecords[] = $this->preparePurchaseRecord(
                            $unitAmount,
                            $initialStatus,
                            $ticketNumber
                        );
                    }

                    // ✅ Insertar con manejo de duplicados
                    try {
                        Purchase::insert($purchaseRecords);
                        $totalInserted += $currentBatchSize;
                        $assignedNumbers = array_merge($assignedNumbers, $batchNumbers);

                        DB::commit();

                        Log::info("[{$this->prefix}] ✅ Lote {$i} insertado. Progreso: {$totalInserted}/{$requestedQuantity}");

                    } catch (\Illuminate\Database\QueryException $e) {
                        DB::rollBack();

                        // ✅ Si es error de duplicado (23505), reintentamos
                        if ($e->getCode() == 23505) {
                            Log::warning("[{$this->prefix}] ⚠️ Duplicado detectado en lote {$i}, reintentando...");

                            usleep(100000); // 100ms

                            // Reintento
                            DB::beginTransaction();

                            try {
                                $retryNumbers = $this->getAvailableTicketNumbersLocked($event, $currentBatchSize);

                                if (count($retryNumbers) < $currentBatchSize) {
                                    DB::rollBack();
                                    throw new Exception("No hay números para reintento en lote {$i}");
                                }

                                $retryRecords = [];
                                foreach (array_slice($retryNumbers, 0, $currentBatchSize) as $ticketNumber) {
                                    $retryRecords[] = $this->preparePurchaseRecord(
                                        $unitAmount,
                                        $initialStatus,
                                        $ticketNumber
                                    );
                                }

                                Purchase::insert($retryRecords);
                                $totalInserted += $currentBatchSize;
                                $assignedNumbers = array_merge(
                                    $assignedNumbers,
                                    array_slice($retryNumbers, 0, $currentBatchSize)
                                );

                                DB::commit();

                                Log::info("[{$this->prefix}] ✅ Lote {$i} reintentado exitosamente");

                            } catch (Exception $retryException) {
                                DB::rollBack();
                                throw $retryException;
                            }
                        } else {
                            throw $e;
                        }
                    }

                    unset($purchaseRecords, $batchNumbers);
                    gc_collect_cycles();

                } catch (Exception $batchException) {
                    DB::rollBack();
                    throw $batchException;
                }
            }

            // 4. ✅ GENERAR QR CODE (transacción separada)
            $qrImageUrl = $this->generatePurchaseQRCode();
            if ($qrImageUrl) {
                DB::transaction(function() use ($purchaseRepository, $qrImageUrl) {
                    $purchaseRepository->updateQrCodeByTransaction($this->transactionId, $qrImageUrl);
                });
                Log::info("[{$this->prefix}] ✅ QR Code generado");
            }

            $totalAmount = $unitAmount * $totalInserted;

            Log::info("[{$this->prefix}] 🎉 COMPRA MASIVA COMPLETADA", [
                'tickets_created' => $totalInserted,
                'total_amount' => $totalAmount,
                'is_admin' => $this->isAdminPurchase
            ]);

            // 5. ✅ ENVIAR NOTIFICACIÓN
            $this->dispatchNotification($totalAmount, $event);

        } catch (Exception $exception) {
            Log::error("[{$this->prefix}] ❌ ERROR CRÍTICO", [
                'error' => $exception->getMessage(),
                'total_inserted' => $totalInserted,
                'requested' => $requestedQuantity,
                'trace' => $exception->getTraceAsString()
            ]);

            throw $exception;
        }
    }

    /**
     * ✅ CRÍTICO: Obtener números CON LOCK PESIMISTA
     */
    private function getAvailableTicketNumbersLocked(Event $event, int $needed): array
    {
        // ✅ Query CON LOCK dentro de la transacción activa
        $usedNumbers = DB::table('purchases')
            ->where('event_id', $event->id)
            ->whereNotNull('ticket_number')
            ->where('ticket_number', 'NOT LIKE', 'RECHAZADO%')
            ->lockForUpdate() // ✅ LOCK PESIMISTA
            ->pluck('ticket_number')
            ->map(function($number) {
                return Purchase::formatTicketNumber($number);
            })
            ->toArray();

        // Generar rango completo
        $allNumbers = [];
        for ($i = $event->start_number; $i <= $event->end_number; $i++) {
            $allNumbers[] = Purchase::formatTicketNumber($i);
        }

        // Filtrar disponibles
        $availableNumbers = array_values(array_diff($allNumbers, $usedNumbers));

        // Mezclar
        if ($event->random_assignment ?? true) {
            shuffle($availableNumbers);
        }

        Log::debug("[{$this->prefix}] Números en lote", [
            'used' => count($usedNumbers),
            'available' => count($availableNumbers),
            'needed' => $needed
        ]);

        return $availableNumbers;
    }

    /**
     * ✅ Preparar registro CON FORMATEO MANUAL
     */
    private function preparePurchaseRecord(float $amount, string $status, string $ticketNumber): array
    {
        $formattedTicketNumber = Purchase::formatTicketNumber($ticketNumber);

        return [
            'event_id' => $this->purchaseData['event_id'],
            'event_price_id' => $this->purchaseData['event_price_id'],
            'payment_method_id' => $this->purchaseData['payment_method_id'],
            'user_id' => $this->purchaseData['user_id'] ?? null,
            'fullname' => $this->purchaseData['fullname'] ?? null,
            'email' => $this->purchaseData['email'] ?? null,
            'whatsapp' => $this->purchaseData['whatsapp'] ?? null,
            'identificacion' => $this->purchaseData['identificacion'] ?? null,
            'currency' => $this->purchaseData['currency'],
            'amount' => $amount,
            'total_amount' => $amount,
            'quantity' => 1,
            'ticket_number' => $formattedTicketNumber,
            'transaction_id' => $this->transactionId,
            'payment_reference' => $this->purchaseData['payment_reference'] ?? 'ADMIN-MASSIVE',
            'payment_proof_url' => $this->purchaseData['payment_proof_url'] ?? null,
            'status' => $status,
            'is_admin_purchase' => $this->isAdminPurchase,
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
            Log::error("[{$this->prefix}] Error generando QR", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function dispatchNotification(float $totalAmount, Event $event): void
    {
        try {
            $notificationData = [
                'transaction_id' => $this->transactionId,
                'quantity' => $this->purchaseData['quantity'],
                'total_amount' => $totalAmount,
                'client_fullname' => $this->purchaseData['fullname'] ?? null,
                'client_email' => $this->purchaseData['email'] ?? null,
                'client_whatsapp' => $this->purchaseData['whatsapp'] ?? null,
                'client_identificacion' => $this->purchaseData['identificacion'] ?? null,
                'event_id' => $event->id,
                'event_name' => $event->name,
                'currency' => $this->purchaseData['currency'],
                'payment_method_id' => $this->purchaseData['payment_method_id'],
                'status' => $this->autoApprove ? 'completed' : 'pending',
                'is_admin_purchase' => $this->isAdminPurchase,
                'created_at' => now()->toDateTimeString(),
            ];

            SendPurchaseNotificationJob::dispatch($notificationData, 'massive')
                ->onQueue('notifications');

            Log::info("[{$this->prefix}] ✅ Notificación despachada");
        } catch (\Exception $e) {
            Log::warning("[{$this->prefix}] ⚠️ Error en notificación", [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Log::critical("[{$this->prefix}] 💀 JOB FALLIDO", [
            'transaction_id' => $this->transactionId,
            'quantity' => $this->purchaseData['quantity'],
            'error' => $exception->getMessage()
        ]);
        Log::critical("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        try {
            DB::table('purchases')
                ->where('transaction_id', $this->transactionId)
                ->update([
                    'status' => 'failed',
                    'payment_reference' => 'JOB FAILED: ' . substr($exception->getMessage(), 0, 100),
                    'updated_at' => now()
                ]);
        } catch (\Exception $e) {
            Log::error("[{$this->prefix}] No se pudo actualizar status", [
                'error' => $e->getMessage()
            ]);
        }
    }
}
