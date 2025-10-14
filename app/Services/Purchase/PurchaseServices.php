<?php

namespace App\Services\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\DTOs\Purchase\DTOsPurchaseFilter;
use App\Interfaces\Purchase\IPurchaseServices;
use App\Interfaces\Purchase\IPurchaseRepository;
use App\Jobs\SendPurchaseNotificationJob;
use App\Models\Event;
use App\Models\EventPrice;
use App\Models\Purchase;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseServices implements IPurchaseServices
{
    protected IPurchaseRepository $PurchaseRepository;

    public function __construct(IPurchaseRepository $PurchaseRepositoryInterface)
    {
        $this->PurchaseRepository = $PurchaseRepositoryInterface;
    }

    // ====================================================================
    // MÉTODOS BÁSICOS CRUD
    // ====================================================================

    public function getAllPurchases(?DTOsPurchaseFilter $filters = null)
    {
        try {
            $results = $this->PurchaseRepository->getGroupedPurchases($filters);

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

    // ====================================================================
    // MÉTODOS DE CREACIÓN OPTIMIZADOS
    // ====================================================================

    /**
     * ✅ OPTIMIZADO: Crear compra aleatoria con insert masivo (Repository)
     */
    public function createPurchase(DTOsPurchase $data)
    {
        try {
            DB::beginTransaction();

            $event = Event::findOrFail($data->getEventId());
            $eventPrice = EventPrice::findOrFail($data->getEventPriceId());

            $availableCount = $this->getAvailableNumbersCount($event);

            if ($availableCount < $data->getQuantity()) {
                throw new Exception("Solo quedan {$availableCount} números disponibles.");
            }

            $transactionId = $this->generateUniqueTransactionId();
            $totalAmount = $data->getTotalAmount();

            $purchaseRecords = [];
            for ($i = 0; $i < $data->getQuantity(); $i++) {
                $purchaseRecords[] = $this->PurchaseRepository->preparePurchaseRecord(
                    $data,
                    $eventPrice->amount,
                    $transactionId,
                    null,
                    'pending'
                );
            }

            $this->PurchaseRepository->bulkInsertPurchases($purchaseRecords);

            $qrImageUrl = $this->generatePurchaseQRCode($transactionId);
            if ($qrImageUrl) {
                $this->PurchaseRepository->updateQrCodeByTransaction($transactionId, $qrImageUrl);
            }

            DB::commit();

            // ✅ DESPACHAR JOB EN SEGUNDO PLANO (solo con datos del DTO)
            SendPurchaseNotificationJob::dispatch([
                'transaction_id' => $transactionId,
                'quantity' => $data->getQuantity(),
                'total_amount' => $totalAmount,
                'client_email' => $data->getEmail(),
                'client_whatsapp' => $data->getWhatsapp(),
                'event_id' => $event->id,
                'event_name' => $event->name,
            ], 'quantity');

            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'qr_code_url' => $qrImageUrl,
                    'quantity' => $data->getQuantity(),
                    'total_amount' => $totalAmount,
                ],
                'message' => 'Compra registrada exitosamente. Hemos enviado la información de tu compra a tu correo electrónico.'
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

    /**
     * ✅ OPTIMIZADO: Crear compra con números específicos (Repository)
     */
    public function createSinglePurchase(DTOsPurchase $data)
    {
        try {
            DB::beginTransaction();

            $event = Event::findOrFail($data->getEventId());
            $eventPrice = EventPrice::findOrFail($data->getEventPriceId());
            $ticketNumbers = $data->getSpecificNumbers();

            if (empty($ticketNumbers)) {
                throw ValidationException::withMessages([
                    'ticket_numbers' => ['Debes seleccionar al menos un número de ticket.']
                ]);
            }

            $reservedNumbers = $this->PurchaseRepository->getReservedTicketNumbers($event->id, $ticketNumbers);

            if (!empty($reservedNumbers)) {
                throw ValidationException::withMessages([
                    'ticket_numbers' => [
                        'Los siguientes números ya están reservados: ' .
                            implode(', ', $reservedNumbers) .
                            '. Por favor, selecciona otros números.'
                    ]
                ]);
            }

            $transactionId = $this->generateUniqueTransactionId();

            $purchaseRecords = [];
            foreach ($ticketNumbers as $ticketNumber) {
                $purchaseRecords[] = $this->PurchaseRepository->preparePurchaseRecord(
                    $data,
                    $eventPrice->amount,
                    $transactionId,
                    $ticketNumber,
                    'pending'
                );
            }

            $this->PurchaseRepository->bulkInsertPurchases($purchaseRecords);

            $qrImageUrl = $this->generatePurchaseQRCode($transactionId);
            if ($qrImageUrl) {
                $this->PurchaseRepository->updateQrCodeByTransaction($transactionId, $qrImageUrl);
            }

            DB::commit();

            // ✅ DESPACHAR JOB EN SEGUNDO PLANO (solo con datos del DTO)
            SendPurchaseNotificationJob::dispatch([
                'transaction_id' => $transactionId,
                'ticket_numbers' => $ticketNumbers,
                'quantity' => count($ticketNumbers),
                'total_amount' => $eventPrice->amount * count($ticketNumbers),
                'client_email' => $data->getEmail(),
                'client_whatsapp' => $data->getWhatsapp(),
                'event_id' => $event->id,
                'event_name' => $event->name,
            ], 'single');

            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'ticket_numbers' => $ticketNumbers,
                    'quantity' => count($ticketNumbers),
                    'total_amount' => $eventPrice->amount * count($ticketNumbers),
                    'qr_code_url' => $qrImageUrl,
                ],
                'message' => 'Compra registrada exitosamente. Números reservados: '
                    . implode(', ', $ticketNumbers)
                    . '. Espera la aprobación del administrador.'
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Error creating single purchase: ' . $exception->getMessage(), [
                'event_id' => $data->getEventId(),
                'ticket_numbers' => $ticketNumbers ?? [],
            ]);

            throw ValidationException::withMessages([
                'general' => ['Ocurrió un error al procesar tu compra. Por favor, intenta nuevamente.']
            ]);
        }
    }

    /**
     * ✅ OPTIMIZADO: Crear compra admin con números específicos (Repository)
     */
    public function createAdminPurchase(DTOsPurchase $data, bool $autoApprove = false)
    {
        try {
            DB::beginTransaction();

            $event = Event::findOrFail($data->getEventId());
            $eventPrice = EventPrice::findOrFail($data->getEventPriceId());
            $ticketNumbers = $data->getSpecificNumbers();

            if (empty($ticketNumbers)) {
                throw new Exception("No se especificaron números de tickets.");
            }

            // ✅ Validar usando repository
            $reservedNumbers = $this->PurchaseRepository->getReservedTicketNumbers($event->id, $ticketNumbers);

            if (!empty($reservedNumbers)) {
                throw new Exception(
                    'Los siguientes números ya están reservados: ' . implode(', ', $reservedNumbers)
                );
            }

            $transactionId = $this->generateUniqueTransactionId('ADM');
            $status = $autoApprove ? 'completed' : 'pending';

            // ✅ Preparar e insertar usando repository
            $purchaseRecords = [];
            foreach ($ticketNumbers as $ticketNumber) {
                $purchaseRecords[] = $this->PurchaseRepository->preparePurchaseRecord(
                    $data,
                    $eventPrice->amount,
                    $transactionId,
                    $ticketNumber,
                    $status
                );
            }

            $this->PurchaseRepository->bulkInsertPurchases($purchaseRecords);

            // ✅ Generar y actualizar QR Code
            $qrImageUrl = $this->generatePurchaseQRCode($transactionId);
            if ($qrImageUrl) {
                $this->PurchaseRepository->updateQrCodeByTransaction($transactionId, $qrImageUrl);
            }

            DB::commit();

            $message = $autoApprove
                ? 'Compra creada y aprobada automáticamente. Números asignados: ' . implode(', ', $ticketNumbers)
                : 'Compra registrada exitosamente. Status: Pendiente de aprobación. Números reservados: ' . implode(', ', $ticketNumbers);

            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'ticket_numbers' => $ticketNumbers,
                    'quantity' => count($ticketNumbers),
                    'total_amount' => $data->getTotalAmount(),
                    'status' => $status,
                    'qr_code_url' => $qrImageUrl,
                ],
                'message' => $message
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Error creating admin purchase: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    /**
     * ✅ OPTIMIZADO: Crear compra admin con números aleatorios (Repository)
     */
    public function createAdminRandomPurchase(DTOsPurchase $data, bool $autoApprove = true)
    {
        try {
            DB::beginTransaction();

            $event = Event::findOrFail($data->getEventId());
            $eventPrice = EventPrice::findOrFail($data->getEventPriceId());

            $availableCount = $this->getAvailableNumbersCount($event);

            if ($availableCount < $data->getQuantity()) {
                throw new Exception("Solo quedan {$availableCount} números disponibles.");
            }

            $transactionId = $this->generateUniqueTransactionId('ADM');
            $totalAmount = $data->getTotalAmount();

            // ✅ Preparar e insertar usando repository
            $purchaseRecords = [];
            for ($i = 0; $i < $data->getQuantity(); $i++) {
                $purchaseRecords[] = $this->PurchaseRepository->preparePurchaseRecord(
                    $data,
                    $eventPrice->amount,
                    $transactionId,
                    null,
                    'pending'
                );
            }

            $this->PurchaseRepository->bulkInsertPurchases($purchaseRecords);

            // ✅ Generar y actualizar QR Code
            $qrImageUrl = $this->generatePurchaseQRCode($transactionId);
            if ($qrImageUrl) {
                $this->PurchaseRepository->updateQrCodeByTransaction($transactionId, $qrImageUrl);
            }

            DB::commit();

            // ✅ Si auto_approve, ejecutar aprobación
            if ($autoApprove) {
                $approvalResult = $this->approvePurchase($transactionId);

                if (!$approvalResult['success']) {
                    throw new Exception($approvalResult['message']);
                }

                return [
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transactionId,
                        'quantity' => $data->getQuantity(),
                        'total_amount' => $totalAmount,
                        'assigned_numbers' => $approvalResult['data']['assigned_numbers'],
                        'status' => 'completed',
                        'qr_code_url' => $qrImageUrl,
                    ],
                    'message' => 'Compra creada y aprobada automáticamente. Números asignados: '
                        . implode(', ', $approvalResult['data']['assigned_numbers'])
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'quantity' => $data->getQuantity(),
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                    'qr_code_url' => $qrImageUrl,
                ],
                'message' => 'Compra registrada exitosamente. Status: Pendiente de aprobación.'
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Error creating admin random purchase: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    // ====================================================================
    // MÉTODOS DE APROBACIÓN Y RECHAZO
    // ====================================================================

    /**
     * ✅ OPTIMIZADO: Aprobar compra con números aleatorios (Repository)
     */
    public function approvePurchase(string $transactionId): array
    {
        try {
            DB::beginTransaction();

            // ✅ Obtener compras pendientes usando repository
            $purchases = $this->PurchaseRepository->getPendingPurchasesByTransaction($transactionId);

            if ($purchases->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron compras pendientes con este transaction_id'
                ];
            }

            $event = Event::findOrFail($purchases->first()->event_id);

            // ✅ Obtener números usados usando repository
            $usedNumbers = $this->PurchaseRepository->getUsedTicketNumbers($event->id);

            $allNumbers = range($event->start_number, $event->end_number);
            $availableNumbers = array_diff($allNumbers, $usedNumbers);

            if (count($availableNumbers) < $purchases->count()) {
                throw new Exception('No hay suficientes números disponibles para esta transacción.');
            }

            // ✅ Asignar números usando repository
            $assignedNumbers = [];
            $availableNumbersArray = array_values($availableNumbers);

            foreach ($purchases as $purchase) {
                if (empty($availableNumbersArray)) {
                    throw new Exception('Se agotaron los números disponibles durante la asignación.');
                }

                $randomIndex = array_rand($availableNumbersArray);
                $assignedNumber = $availableNumbersArray[$randomIndex];

                // ✅ Asignar usando repository
                $this->PurchaseRepository->assignTicketNumber($purchase->id, $assignedNumber, 'completed');

                $assignedNumbers[] = $assignedNumber;

                // Remover número asignado
                unset($availableNumbersArray[$randomIndex]);
                $availableNumbersArray = array_values($availableNumbersArray);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Pago aprobado y números asignados exitosamente.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'purchases_count' => $purchases->count(),
                    'assigned_numbers' => $assignedNumbers,
                    'status' => 'completed'
                ]
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Error approving purchase: ' . $exception->getMessage());

            // ✅ Actualizar usando repository
            $this->PurchaseRepository->updateStatusByTransactionAndConditions(
                $transactionId,
                'failed',
                'processing'
            );

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    /**
     * ✅ OPTIMIZADO: Aprobar compra con números ya asignados (Repository)
     */
    public function approveSinglePurchase(string $transactionId): array
    {
        try {
            DB::beginTransaction();

            // ✅ Actualizar usando repository con condiciones específicas
            $updatedCount = $this->PurchaseRepository->updateStatusByTransactionAndConditions(
                $transactionId,
                'completed',
                'pending',
                true // hasTicketNumber = true
            );

            if ($updatedCount === 0) {
                throw new Exception('No se encontraron compras individuales pendientes');
            }

            // Obtener números asignados
            $purchases = $this->PurchaseRepository->getPurchasesByTransaction($transactionId);
            $ticketNumbers = $purchases->pluck('ticket_number')->filter()->toArray();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Compra(s) aprobada(s) exitosamente.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'quantity' => $updatedCount,
                    'ticket_numbers' => $ticketNumbers,
                    'status' => 'completed'
                ]
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * ✅ OPTIMIZADO: Rechazar compra (Repository)
     */
    public function rejectPurchase(string $transactionId, string|null $reason = null): array
    {
        try {
            DB::beginTransaction();

            // ✅ Actualizar usando repository
            $updatedCount = $this->PurchaseRepository->updateStatusByTransactionAndConditions(
                $transactionId,
                'failed',
                'pending'
            );

            if ($updatedCount === 0) {
                throw new Exception('No se encontraron compras pendientes con este transaction_id');
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Compra rechazada correctamente.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'purchases_count' => $updatedCount,
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

    // ====================================================================
    // MÉTODOS DE CONSULTA
    // ====================================================================

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
            $summary = Purchase::getTransactionSummary($transactionId);

            if (!$summary) {
                throw new Exception("No se encontró la transacción {$transactionId}");
            }

            return [
                'success' => true,
                'data' => $summary
            ];
        } catch (Exception $exception) {
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

    public function getPurchasesByWhatsApp(string $whatsapp)
    {
        try {
            $results = $this->PurchaseRepository->getPurchasesByWhatsApp($whatsapp);

            if ($results->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron compras para este número de WhatsApp',
                    'data' => []
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'purchases' => $results,
                    'total_purchases' => $results->count(),
                    'total_tickets' => $results->sum('quantity'),
                    'whatsapp' => $whatsapp
                ],
                'message' => 'Compras obtenidas exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => []
            ];
        }
    }

    // ====================================================================
    // MÉTODOS HELPER PRIVADOS (Solo lógica de negocio, no BD)
    // ====================================================================

    /**
     * Genera un transaction_id único
     */
    private function generateUniqueTransactionId(string $prefix = 'TXN'): string
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $timestamp = now()->format('YmdHis');
            $random = strtoupper(Str::random(12));

            $transactionId = "{$prefix}-{$timestamp}-{$random}";

            // ✅ Verificar usando repository
            if (!$this->PurchaseRepository->transactionIdExists($transactionId)) {
                return $transactionId;
            }

            usleep(1000); // 1ms
        }

        throw new \Exception('No se pudo generar un transaction_id único.');
    }

    /**
     * Generar código QR para la compra y subirlo a S3
     */
    private function generatePurchaseQRCode(string $transactionId): ?string
    {
        try {
            $frontendUrl = config('app.frontend_url', 'https://tu-frontend.com');
            $purchaseUrl = "{$frontendUrl}/purchase/{$transactionId}";

            $qrImage = QrCodeGenerator::format('png')
                ->size(400)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($purchaseUrl);

            $fileName = 'purchase-qr/' . $transactionId . '_' . time() . '.png';

            $uploaded = Storage::disk('s3')->put($fileName, $qrImage, [
                'visibility' => 'public',
                'ContentType' => 'image/png'
            ]);

            if ($uploaded) {
                $url = "https://backend-imagen-br.s3.us-east-2.amazonaws.com/" . $fileName;

                Log::info('Purchase QR Code generated successfully', [
                    'transaction_id' => $transactionId,
                    'url' => $url
                ]);

                return $url;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error generating purchase QR code', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Obtener conteo de números disponibles
     */
    private function getAvailableNumbersCount(Event $event): int
    {
        $totalNumbers = ($event->end_number - $event->start_number) + 1;

        // ✅ Usar repository para obtener números usados
        $usedNumbers = count($this->PurchaseRepository->getUsedTicketNumbers($event->id));

        return $totalNumbers - $usedNumbers;
    }

    /**
     * Obtiene el resumen de una transacción
     */
    public function getTransactionDetails(string $transactionId): ?array
    {
        return Purchase::getTransactionSummary($transactionId);
    }

    /**
     * Actualiza el estado de toda una transacción
     */
    public function updateTransactionStatus(string $transactionId, string $newStatus): int
    {
        $validStatuses = ['processing', 'pending', 'completed', 'failed', 'refunded'];

        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException("Estado inválido: {$newStatus}");
        }

        // ✅ Usar repository
        return $this->PurchaseRepository->updateStatusByTransaction($transactionId, $newStatus);
    }
}
