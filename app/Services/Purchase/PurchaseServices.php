<?php

namespace App\Services\Purchase;

use App\DTOs\Purchase\DTOsAddTickets;
use Exception;
use App\Models\Event;
use App\Models\Purchase;
use App\Models\EventPrice;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\DTOs\Purchase\DTOsPurchase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\DTOs\Purchase\DTOsPurchaseFilter;
use App\DTOs\Purchase\DTOsUpdatePurchaseQuantity;
use App\Jobs\SendPurchaseNotificationJob;
use App\Interfaces\Purchase\IPurchaseServices;
use Illuminate\Validation\ValidationException;
use App\Interfaces\Purchase\IPurchaseRepository;
use App\Services\Notification\EmailNotificationService;
use App\Services\Notification\WhatsAppNotificationService;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;

class PurchaseServices implements IPurchaseServices
{
    protected IPurchaseRepository $PurchaseRepository;
    protected WhatsAppNotificationService $whatsappNotification;
    protected EmailNotificationService $emailNotification;

    public function __construct(
        IPurchaseRepository $PurchaseRepositoryInterface,
        WhatsAppNotificationService $whatsappNotification,
        EmailNotificationService $emailNotification
    ) {
        $this->PurchaseRepository = $PurchaseRepositoryInterface;
        $this->whatsappNotification = $whatsappNotification;
        $this->emailNotification = $emailNotification;
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
                'message' => 'Compra registrada exitosamente, Espera la aprobación del administrador.'
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



    // ====================================================================
    // MÉTODOS DE APROBACIÓN Y RECHAZO
    // ====================================================================

    /**
     * ✅ OPTIMIZADO: Aprobar compra con números aleatorios (Repository)
     */
    /**
     * ✅ CORREGIDO: Aprobar compra (detecta si tiene números o necesita asignar aleatorios)
     */
    public function approvePurchase(string $transactionId): array
    {
        try {
            DB::beginTransaction();

            $purchases = $this->PurchaseRepository->getPendingPurchasesByTransaction($transactionId);

            if ($purchases->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron compras pendientes con este transaction_id'
                ];
            }

            $event = Event::findOrFail($purchases->first()->event_id);

            // ✅ DETECTAR: ¿Ya tienen números asignados o necesitan números aleatorios?
            $purchasesWithNumbers = $purchases->filter(fn($p) => !is_null($p->ticket_number));
            $purchasesWithoutNumbers = $purchases->filter(fn($p) => is_null($p->ticket_number));

            $assignedNumbers = [];

            // ========================================================================
            // CASO 1: SI TIENEN NÚMEROS ASIGNADOS → Solo cambiar status a 'completed'
            // ========================================================================
            if ($purchasesWithNumbers->isNotEmpty()) {
                foreach ($purchasesWithNumbers as $purchase) {
                    $this->PurchaseRepository->assignTicketNumber(
                        $purchase->id,
                        $purchase->ticket_number,
                        'completed'
                    );
                    $assignedNumbers[] = $purchase->ticket_number;
                }

                Log::info('✅ Compra con números específicos aprobada', [
                    'transaction_id' => $transactionId,
                    'assigned_numbers' => $assignedNumbers
                ]);
            }

            // ========================================================================
            // CASO 2: SI NO TIENEN NÚMEROS → Asignar números aleatorios
            // ========================================================================
            if ($purchasesWithoutNumbers->isNotEmpty()) {
                $usedNumbers = $this->PurchaseRepository->getUsedTicketNumbers($event->id);
                $allNumbers = range($event->start_number, $event->end_number);
                $availableNumbers = array_diff($allNumbers, $usedNumbers);

                if (count($availableNumbers) < $purchasesWithoutNumbers->count()) {
                    throw new Exception('No hay suficientes números disponibles para esta transacción.');
                }

                $availableNumbersArray = array_values($availableNumbers);

                foreach ($purchasesWithoutNumbers as $purchase) {
                    if (empty($availableNumbersArray)) {
                        throw new Exception('Se agotaron los números disponibles durante la asignación.');
                    }

                    $randomIndex = array_rand($availableNumbersArray);
                    $assignedNumber = $availableNumbersArray[$randomIndex];

                    $this->PurchaseRepository->assignTicketNumber($purchase->id, $assignedNumber, 'completed');

                    $assignedNumbers[] = $assignedNumber;
                    unset($availableNumbersArray[$randomIndex]);
                    $availableNumbersArray = array_values($availableNumbersArray);
                }

                Log::info('✅ Compra con números aleatorios aprobada', [
                    'transaction_id' => $transactionId,
                    'assigned_numbers' => $assignedNumbers
                ]);
            }

            DB::commit();

            $firstPurchase = $purchases->first();

            // ✅ ENVIAR NOTIFICACIONES (Email y WhatsApp)
            $emailSent = false;
            $whatsappSent = false;
            $emailStatus = 'not_attempted';
            $whatsappStatus = 'not_attempted';

            // Intentar enviar email si está disponible
            if (!empty($firstPurchase->email)) {
                $emailSent = $this->emailNotification->sendApprovalNotification(
                    $firstPurchase->email,
                    $transactionId,
                    $assignedNumbers,
                    $purchases->count(),
                    $event->name
                );
                $emailStatus = $emailSent ? 'sent_successfully' : 'failed_to_send';
            } else {
                $emailStatus = 'no_email_provided';
            }

            // ✅ Intentar enviar WhatsApp si está disponible (AHORA CON NOMBRE)
            if (!empty($firstPurchase->whatsapp)) {
                $whatsappSent = $this->whatsappNotification->sendApprovalNotification(
                    $firstPurchase->whatsapp,
                    $transactionId,
                    $assignedNumbers,
                    $purchases->count(),
                    $firstPurchase->fullname // ✅ AGREGAR EL NOMBRE
                );
                $whatsappStatus = $whatsappSent ? 'sent_successfully' : 'failed_to_send';
            } else {
                $whatsappStatus = 'no_whatsapp_provided';
            }

            return [
                'success' => true,
                'message' => 'Pago aprobado y números asignados exitosamente.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'purchases_count' => $purchases->count(),
                    'assigned_numbers' => $assignedNumbers,
                    'status' => 'completed',
                    'had_specific_numbers' => $purchasesWithNumbers->isNotEmpty(),
                    'notifications' => [
                        'email' => [
                            'sent' => $emailSent,
                            'status' => $emailStatus,
                            'address' => !empty($firstPurchase->email) ? $firstPurchase->email : null
                        ],
                        'whatsapp' => [
                            'sent' => $whatsappSent,
                            'status' => $whatsappStatus,
                            'phone' => !empty($firstPurchase->whatsapp) ? $firstPurchase->whatsapp : null
                        ]
                    ]
                ]
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Error approving purchase: ' . $exception->getMessage());

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
    /**
     * ✅ Aprobar compra individual con notificación de WhatsApp
     */
    public function approveSinglePurchase(string $transactionId): array
    {
        try {
            DB::beginTransaction();

            $updatedCount = $this->PurchaseRepository->updateStatusByTransactionAndConditions(
                $transactionId,
                'completed',
                'pending',
                true
            );

            if ($updatedCount === 0) {
                throw new Exception('No se encontraron compras individuales pendientes');
            }

            $purchases = $this->PurchaseRepository->getPurchasesByTransaction($transactionId);
            $ticketNumbers = $purchases->pluck('ticket_number')->filter()->toArray();
            $firstPurchase = $purchases->first();
            $event = Event::findOrFail($firstPurchase->event_id);

            DB::commit();

            // ✅ ENVIAR NOTIFICACIONES
            $emailSent = false;
            $whatsappSent = false;
            $emailStatus = 'not_attempted';
            $whatsappStatus = 'not_attempted';

            if (!empty($firstPurchase->email)) {
                $emailSent = $this->emailNotification->sendApprovalNotification(
                    $firstPurchase->email,
                    $transactionId,
                    $ticketNumbers,
                    $updatedCount,
                    $event->name
                );
                $emailStatus = $emailSent ? 'sent_successfully' : 'failed_to_send';
            } else {
                $emailStatus = 'no_email_provided';
            }

            if (!empty($firstPurchase->whatsapp)) {
                $whatsappSent = $this->whatsappNotification->sendApprovalNotification(
                    $firstPurchase->whatsapp,
                    $transactionId,
                    $ticketNumbers,
                    $updatedCount
                );
                $whatsappStatus = $whatsappSent ? 'sent_successfully' : 'failed_to_send';
            } else {
                $whatsappStatus = 'no_whatsapp_provided';
            }

            return [
                'success' => true,
                'message' => 'Compra(s) aprobada(s) exitosamente.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'quantity' => $updatedCount,
                    'ticket_numbers' => $ticketNumbers,
                    'status' => 'completed',
                    'notifications' => [
                        'email' => [
                            'sent' => $emailSent,
                            'status' => $emailStatus,
                            'address' => !empty($firstPurchase->email) ? $firstPurchase->email : null
                        ],
                        'whatsapp' => [
                            'sent' => $whatsappSent,
                            'status' => $whatsappStatus,
                            'phone' => !empty($firstPurchase->whatsapp) ? $firstPurchase->whatsapp : null
                        ]
                    ]
                ]
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }


    /**
     * ✅ Rechazar compra con notificación de WhatsApp
     */
    public function rejectPurchase(string $transactionId, string|null $reason = null): array
    {
        try {
            DB::beginTransaction();

            // 1. Obtener datos ANTES de rechazar
            $purchases = $this->PurchaseRepository->getPurchasesByTransaction($transactionId);

            if ($purchases->isEmpty()) {
                throw new Exception('No se encontraron compras con este transaction_id');
            }

            $pendingPurchases = $purchases->where('status', 'pending');

            if ($pendingPurchases->isEmpty()) {
                throw new Exception('No hay compras pendientes para rechazar en esta transacción');
            }

            $firstPurchase = $purchases->first();
            $event = Event::findOrFail($firstPurchase->event_id);

            // Guardar números antes de rechazar
            $liberatedNumbers = $pendingPurchases
                ->whereNotNull('ticket_number')
                ->pluck('ticket_number')
                ->toArray();

            // 2. Rechazar
            $updatedCount = $this->PurchaseRepository->rejectPurchaseAndFreeNumbers(
                $transactionId,
                $reason
            );

            // 3. ✅ COMMIT ANTES de notificaciones
            DB::commit();

            // 4. ✅ REFRESCAR datos para notificaciones
            $firstPurchase->refresh(); // Recargar desde DB

            // 5. ✅ ENVIAR NOTIFICACIONES (fuera de la transacción)
            $emailSent = false;
            $whatsappSent = false;
            $emailStatus = 'not_attempted';
            $whatsappStatus = 'not_attempted';

            if (!empty($firstPurchase->email)) {
                try {
                    $emailSent = $this->emailNotification->sendRejectionNotification(
                        $firstPurchase->email,
                        $transactionId,
                        $reason,
                        $event->name
                    );
                    $emailStatus = $emailSent ? 'sent_successfully' : 'failed_to_send';
                } catch (\Exception $e) {
                    Log::error('Error enviando email de rechazo', [
                        'transaction_id' => $transactionId,
                        'email' => $firstPurchase->email,
                        'error' => $e->getMessage()
                    ]);
                    $emailStatus = 'error_sending';
                }
            } else {
                $emailStatus = 'no_email_provided';
            }

            if (!empty($firstPurchase->whatsapp)) {
                try {
                    $whatsappSent = $this->whatsappNotification->sendRejectionNotification(
                        $firstPurchase->whatsapp,
                        $transactionId,
                        $reason
                    );
                    $whatsappStatus = $whatsappSent ? 'sent_successfully' : 'failed_to_send';
                } catch (\Exception $e) {
                    Log::error('Error enviando WhatsApp de rechazo', [
                        'transaction_id' => $transactionId,
                        'whatsapp' => $firstPurchase->whatsapp,
                        'error' => $e->getMessage()
                    ]);
                    $whatsappStatus = 'error_sending';
                }
            } else {
                $whatsappStatus = 'no_whatsapp_provided';
            }

            Log::info('✅ Notificaciones de rechazo procesadas', [
                'transaction_id' => $transactionId,
                'email_status' => $emailStatus,
                'whatsapp_status' => $whatsappStatus
            ]);

            return [
                'success' => true,
                'message' => 'Compra rechazada correctamente.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'purchases_count' => $updatedCount,
                    'liberated_numbers' => $liberatedNumbers,
                    'reason' => $reason,
                    'notifications' => [
                        'email' => [
                            'sent' => $emailSent,
                            'status' => $emailStatus,
                            'address' => !empty($firstPurchase->email) ? $firstPurchase->email : null
                        ],
                        'whatsapp' => [
                            'sent' => $whatsappSent,
                            'status' => $whatsappStatus,
                            'phone' => !empty($firstPurchase->whatsapp) ? $firstPurchase->whatsapp : null
                        ]
                    ]
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
            $purchaseUrl = "{$frontendUrl}/my-purchase/{$transactionId}";

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

    public function getPurchasesByIdentificacion(string $identificacion)
    {
        try {

            $identificacion = $this->normalizeIdentificacion($identificacion);

            $results = $this->PurchaseRepository->getPurchasesByIdentificacion($identificacion);

            if ($results->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No se encontraron compras para esta cédula de identidad',
                    'data' => []
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'purchases' => $results,
                    'total_purchases' => $results->count(),
                    'total_tickets' => $results->sum('quantity'),
                    'identificacion' => $identificacion
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

    /**
     * Normaliza el formato de la identificación
     */
    private function normalizeIdentificacion(string $identificacion): string
    {
        // Convertir a mayúsculas
        $identificacion = strtoupper(trim($identificacion));

        // Si no tiene guion, agregarlo
        if (!str_contains($identificacion, '-')) {
            $identificacion = preg_replace('/^([VE])(\d+)$/', '$1-$2', $identificacion);
        }

        return $identificacion;
    }


    public function checkTicketAvailability(int $eventId, string $ticketNumber): array
    {
        try {
            // Validar y obtener evento
            $event = Event::findOrFail($eventId);

            // Normalizar número de ticket
            $ticketNumber = trim($ticketNumber);

            // ✅ Usar método del modelo Event (incluye validación de rango)
            $availabilityInfo = $event->getTicketAvailability($ticketNumber);

            // Si está fuera de rango
            if (!$availabilityInfo['in_range']) {
                return [
                    'success' => false,
                    'available' => false,
                    'message' => "El número {$ticketNumber} está fuera del rango válido ({$event->start_number} - {$event->end_number})",
                    'data' => [
                        'ticket_number' => $ticketNumber,
                        'event_id' => $eventId,
                        'event_name' => $event->name,
                        'valid_range' => [
                            'start' => $event->start_number,
                            'end' => $event->end_number
                        ]
                    ]
                ];
            }

            // Si está disponible
            if ($availabilityInfo['available']) {
                return [
                    'success' => true,
                    'available' => true,
                    'message' => "El número {$ticketNumber} está disponible",
                    'data' => [
                        'ticket_number' => $ticketNumber,
                        'event_id' => $eventId,
                        'event_name' => $event->name,
                        'status' => 'available'
                    ]
                ];
            }

            // Si está reservado
            $purchase = $availabilityInfo['purchase'];

            return [
                'success' => true,
                'available' => false,
                'message' => "El número {$ticketNumber} ya está reservado",
                'data' => [
                    'ticket_number' => $ticketNumber,
                    'event_id' => $eventId,
                    'event_name' => $event->name,
                    'status' => 'reserved',
                    'reserved_at' => $purchase->created_at->toDateTimeString(),
                    'purchase_status' => $purchase->status
                ]
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Event not found in checkTicketAvailability', [
                'event_id' => $eventId,
                'ticket_number' => $ticketNumber
            ]);

            return [
                'success' => false,
                'available' => false,
                'message' => 'Evento no encontrado',
                'data' => [
                    'ticket_number' => $ticketNumber,
                    'event_id' => $eventId
                ]
            ];
        } catch (\Exception $exception) {
            Log::error('Error in checkTicketAvailability', [
                'event_id' => $eventId,
                'ticket_number' => $ticketNumber,
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);

            return [
                'success' => false,
                'available' => false,
                'message' => 'Error al verificar disponibilidad del ticket',
                'data' => [
                    'error_detail' => config('app.debug') ? $exception->getMessage() : null
                ]
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
    public function createMassivePurchaseAsync(DTOsPurchase $data, bool $autoApprove = true): array
    {
        try {
            // 1. Validar evento y precio
            $event = Event::findOrFail($data->getEventId());
            $eventPrice = EventPrice::findOrFail($data->getEventPriceId());

            // 2. Verificar disponibilidad de números
            $availableCount = $this->getAvailableNumbersCount($event);

            if ($availableCount < $data->getQuantity()) {
                return [
                    'success' => false,
                    'message' => "No hay suficientes tickets disponibles. Solicitados: {$data->getQuantity()}, Disponibles: {$availableCount}"
                ];
            }

            // 3. Generar transaction_id único
            $transactionId = $this->generateUniqueTransactionId();

            // 4. ✅ Asegurar que currency siempre tenga un valor
            $currency = $data->getCurrency() ?? $eventPrice->currency ?? 'USD';

            // 5. ✅ Preparar datos para el job
            $jobData = [
                'event_id' => $data->getEventId(),
                'event_price_id' => $data->getEventPriceId(),
                'payment_method_id' => $data->getPaymentMethodId(),
                'user_id' => $data->getUserId() ?? null,
                'email' => $data->getEmail() ?? null,
                'whatsapp' => $data->getWhatsapp() ?? null,
                'identificacion' => $data->getIdentificacion() ?? null,
                'currency' => $currency,
                'quantity' => $data->getQuantity(),
                'payment_reference' => $data->getPaymentReference() ?? 'ADMIN-MASSIVE-' . $transactionId,
                'payment_proof_url' => $data->getPaymentProofUrl() ?? null,
            ];

            // 6. ✅ Despachar job con flag de compra administrativa
            \App\Jobs\ProcessMassivePurchaseJob::dispatch(
                $jobData,
                $transactionId,
                $autoApprove,
                'ADMIN-MASSIVE',
                true // ✅ isAdminPurchase = true (monto será $0)
            )->onQueue('massive-purchases');

            Log::info('🚀 Compra masiva administrativa despachada', [
                'transaction_id' => $transactionId,
                'quantity' => $data->getQuantity(),
                'event_id' => $event->id,
                'auto_approve' => $autoApprove,
                'is_admin' => true,
                'amount' => 0.00
            ]);

            // 7. ✅ Retornar respuesta con monto $0
            return [
                'success' => true,
                'message' => 'Tu compra de ' . number_format($data->getQuantity()) . ' tickets está siendo procesada en segundo plano.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'quantity' => $data->getQuantity(),
                    'total_amount' => 0.00, // ✅ Compra administrativa sin costo
                    'currency' => $currency,
                    'status' => 'processing',
                    'is_admin_purchase' => true, // ✅ Indicador para el frontend
                    'estimated_completion' => now()->addMinutes(ceil($data->getQuantity() / 100))->toDateTimeString(),
                    'estimated_time' => $this->estimateProcessingTime($data->getQuantity()),
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name
                    ]
                ]
            ];
        } catch (\Exception $exception) {
            Log::error('❌ Error al despachar compra masiva administrativa', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error al iniciar el procesamiento: ' . $exception->getMessage()
            ];
        }
    }

    /**
     * ✅ NUEVO: Método helper para estimar tiempo de procesamiento
     */
    private function estimateProcessingTime(int $quantity): string
    {
        $seconds = ceil($quantity / 100); // Aproximadamente 100 tickets por segundo

        if ($seconds < 60) {
            return "{$seconds} segundos";
        }

        $minutes = ceil($seconds / 60);
        return "{$minutes} minuto" . ($minutes > 1 ? 's' : '');
    }

    /**
     * ✅ NUEVO: Consultar estado de una compra masiva en proceso
     *
     * Permite al usuario verificar si su compra masiva ya fue procesada.
     *
     * @param string $transactionId
     * @return array
     */
    public function getMassivePurchaseStatus(string $transactionId): array
    {
        try {

            $purchases = $this->PurchaseRepository->getPurchasesByTransaction($transactionId);

            if ($purchases->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Transacción no encontrada o aún en proceso',
                    'data' => [
                        'transaction_id' => $transactionId,
                        'status' => 'processing',
                        'note' => 'Si acabas de crear esta compra, espera unos momentos y vuelve a consultar.'
                    ]
                ];
            }
            $firstPurchase = $purchases->first();
            $totalTickets = $purchases->count();
            $totalAmount = $purchases->sum('amount');

            return [
                'success' => true,
                'message' => 'Compra masiva completada',
                'data' => [
                    'transaction_id' => $transactionId,
                    'status' => $firstPurchase->status,
                    'quantity' => $totalTickets,
                    'total_amount' => $totalAmount,
                    'currency' => $firstPurchase->currency,
                    'qr_code_url' => $firstPurchase->qr_code_url,
                    'created_at' => $firstPurchase->created_at->toDateTimeString(),
                    'event' => [
                        'id' => $firstPurchase->event_id,
                        'name' => $firstPurchase->event->name ?? 'N/A'
                    ],
                    'contact' => [
                        'email' => $firstPurchase->email,
                        'whatsapp' => $firstPurchase->whatsapp,
                        'identificacion' => $firstPurchase->identificacion
                    ]
                ]
            ];
        } catch (Exception $exception) {
            Log::error('Error consultando estado de compra masiva', [
                'transaction_id' => $transactionId,
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error al consultar el estado: ' . $exception->getMessage()
            ];
        }
    }

    public function addTicketsToTransaction(DTOsAddTickets $dto): array
    {
        try {
            DB::beginTransaction();

            // 1. Verificar transacción
            $transaction = $this->PurchaseRepository->getPurchaseByTransaction(
                $dto->getTransactionId()
            );

            if (!$transaction) {
                throw new \Exception("Transacción no encontrada: {$dto->getTransactionId()}");
            }

            // 2. Verificar evento activo
            $event = Event::findOrFail($transaction['event']['id']);

            if ($event->status !== 'active') {
                throw new \Exception("No se pueden agregar tickets a un evento inactivo");
            }

            // 3. Agregar tickets usando repository
            $result = $this->PurchaseRepository->addTicketsToTransaction($dto);

            DB::commit();

            // 4. Obtener transacción actualizada
            $updatedTransaction = $this->PurchaseRepository->getPurchaseByTransaction(
                $dto->getTransactionId()
            );

            $modeText = $dto->getMode() === 'random' ? 'aleatorios' : 'específicos';

            return [
                'success' => true,
                'message' => "Se agregaron {$result['count']} ticket(s) {$modeText} exitosamente",
                'data' => [
                    'transaction_id' => $dto->getTransactionId(),
                    'mode' => $result['mode'],
                    'added_tickets' => $result['tickets_added'],
                    'added_count' => $result['count'],
                    'previous_quantity' => $transaction['quantity'],
                    'new_quantity' => $updatedTransaction['quantity'],
                    'previous_amount' => $transaction['total_amount'],
                    'new_amount' => $updatedTransaction['total_amount'],
                    'all_tickets' => $updatedTransaction['ticket_numbers']
                ]
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('❌ Error agregando tickets', [
                'dto' => $dto->toArray(),
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    /**
     * ✅ Quitar tickets de una transacción
     */
    public function removeTicketsFromTransaction(
        string $transactionId,
        array $ticketNumbersToRemove
    ): array {
        try {
            DB::beginTransaction();

            // 1. Verificar transacción
            $transaction = $this->PurchaseRepository->getPurchaseByTransaction($transactionId);

            if (!$transaction) {
                throw new \Exception("Transacción no encontrada: {$transactionId}");
            }

            // 2. Remover tickets
            $removedCount = $this->PurchaseRepository->removeTicketsFromTransaction(
                $transactionId,
                $ticketNumbersToRemove
            );

            DB::commit();

            // 3. Obtener transacción actualizada
            $updatedTransaction = $this->PurchaseRepository->getPurchaseByTransaction($transactionId);

            return [
                'success' => true,
                'message' => "Se removieron {$removedCount} ticket(s) exitosamente",
                'data' => [
                    'transaction_id' => $transactionId,
                    'removed_tickets' => $ticketNumbersToRemove,
                    'removed_count' => $removedCount,
                    'previous_quantity' => $transaction['quantity'],
                    'new_quantity' => $updatedTransaction['quantity'],
                    'previous_amount' => $transaction['total_amount'],
                    'new_amount' => $updatedTransaction['total_amount'],
                    'remaining_tickets' => $updatedTransaction['ticket_numbers']
                ]
            ];
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('❌ Error removiendo tickets', [
                'transaction_id' => $transactionId,
                'tickets_to_remove' => $ticketNumbersToRemove,
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function updatePendingPurchaseQuantity(DTOsUpdatePurchaseQuantity $dto): array
    {
        try {
            $transaction = $this->PurchaseRepository->getPurchaseByTransaction(
                $dto->getTransactionId()
            );

            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => "Transacción no encontrada: {$dto->getTransactionId()}"
                ];
            }
            if ($transaction['status'] !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'Solo se puede editar la cantidad de compras en estado pendiente'
                ];
            }

            if ($transaction['ticket_numbers'] !== 'Pendiente de asignación') {
                return [
                    'success' => false,
                    'message' => 'Esta compra ya tiene números asignados. No se puede editar la cantidad.'
                ];
            }

            if ($dto->getNewQuantity() > $transaction['quantity']) {
                $event = Event::findOrFail($transaction['event']['id']);
                $availableCount = $this->getAvailableNumbersCount($event);
                $neededTickets = $dto->getNewQuantity() - $transaction['quantity'];

                if ($availableCount < $neededTickets) {
                    return [
                        'success' => false,
                        'message' => "No hay suficientes tickets disponibles. Necesitas {$neededTickets}, disponibles: {$availableCount}"
                    ];
                }
            }
            $result = $this->PurchaseRepository->adjustPendingPurchaseQuantity(
                $dto->getTransactionId(),
                $dto->getNewQuantity()
            );
            $updatedTransaction = $this->PurchaseRepository->getPurchaseByTransaction(
                $dto->getTransactionId()
            );
            return [
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'transaction_id' => $dto->getTransactionId(),
                    'action' => $result['action'],
                    'previous_quantity' => $result['previous_quantity'] ?? null,
                    'new_quantity' => $result['new_quantity'],
                    'difference' => $result['added_count'] ?? $result['removed_count'] ?? 0,
                    'previous_amount' => $transaction['total_amount'],
                    'new_amount' => $updatedTransaction['total_amount'],
                    'transaction' => $updatedTransaction
                ]
            ];
        } catch (\Exception $exception) {
            Log::error('❌ Error actualizando cantidad de compra pendiente', [
                'dto' => $dto->toArray(),
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    /**
     * ✨ ACTUALIZADO: Obtener top de compradores por evento con filtro de moneda
     */
    public function getTopBuyersByEvent(
        string $eventId,
        int $limit = 10,
        int $minTickets = 1,
        ?string $currency = null // ✨ VES o USD
    ): array {
        try {
            // Validar que el evento existe
            $event = Event::findOrFail($eventId);

            // Obtener top compradores desde el repository
            $topBuyers = $this->PurchaseRepository->getTopBuyersByEvent(
                $eventId,
                $limit,
                $minTickets,
                $currency // ✨ Pasar moneda al repository
            );

            return [
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'status' => $event->status,
                    ],
                    'currency_filter' => $currency, // ✨ VES, USD o null
                    'top_buyers' => $topBuyers,
                    'total_buyers' => count($topBuyers),
                    'limit' => $limit,
                    'min_tickets_filter' => $minTickets
                ],
                'message' => $currency
                    ? "Top de compradores en {$currency} obtenido exitosamente"
                    : 'Top de compradores obtenido exitosamente'
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => 'Evento no encontrado'
            ];
        } catch (Exception $exception) {
            Log::error('Error obteniendo top de compradores', [
                'event_id' => $eventId,
                'currency' => $currency,
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}
