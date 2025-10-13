<?php

namespace App\Services\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\DTOs\Purchase\DTOsPurchaseFilter;
use App\Interfaces\Purchase\IPurchaseServices;
use App\Interfaces\Purchase\IPurchaseRepository;
use App\Jobs\AssignTicketNumberJob;
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
                    email: $data->getEmail(),           // ✅ Agregar
                    whatsapp: $data->getWhatsapp(),     // ✅ Agregar
                    currency: $data->getCurrency(),
                    user_id: $data->getUserId(),
                    specific_numbers: null,
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

            // ✅ Generar QR Code con la URL del frontend
            $qrImageUrl = $this->generatePurchaseQRCode($transactionId);

            // ✅ Actualizar el QR code en todas las compras de esta transacción
            if ($qrImageUrl) {
                Purchase::where('transaction_id', $transactionId)
                    ->update(['qr_code_url' => $qrImageUrl]);
            }

            DB::commit();

            // ✅ Retornar respuesta simplificada
            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'qr_code_url' => $qrImageUrl,
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
     * Generar código QR para la compra y subirlo a S3
     */
    private function generatePurchaseQRCode(string $transactionId): ?string
    {
        try {
            // ✅ URL del frontend (configurable en .env)
            $frontendUrl = config('app.frontend_url', 'https://tu-frontend.com');
            $purchaseUrl = "{$frontendUrl}/purchase/{$transactionId}";

            // ✅ Crear datos para el QR
            $qrData = json_encode([
                'type' => 'purchase_receipt',
                'transaction_id' => $transactionId,
                'url' => $purchaseUrl,
                'timestamp' => now()->toISOString(),
                'version' => '1.0',
            ]);

            // ✅ Generar la imagen QR
            $qrImage = QrCodeGenerator::format('png')
                ->size(400)
                ->margin(2)
                ->errorCorrection('H') // Alta corrección de errores
                ->generate($purchaseUrl); // Usar directamente la URL

            // ✅ Crear nombre único para el archivo
            $fileName = 'purchase-qr/' . $transactionId . '_' . time() . '.png';

            // ✅ Subir a S3
            $uploaded = Storage::disk('s3')->put($fileName, $qrImage, [
                'visibility' => 'public',
                'ContentType' => 'image/png'
            ]);

            if ($uploaded) {
                // Retornar la URL completa
                $url = "https://backend-imagen-br.s3.us-east-2.amazonaws.com/" . $fileName;

                Log::info('Purchase QR Code generated successfully', [
                    'transaction_id' => $transactionId,
                    'file_name' => $fileName,
                    'url' => $url,
                    'purchase_url' => $purchaseUrl
                ]);

                return $url;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error generating purchase QR code', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    // private function getAvailableNumbersCount(Event $event): int
    // {
    //     $totalNumbers = ($event->end_number - $event->start_number) + 1;
    //     $usedNumbers = Purchase::where('event_id', $event->id)
    //         ->whereNotNull('ticket_number')
    //         ->count();

    //     return $totalNumbers - $usedNumbers;
    // }

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
        $usedNumbers = Purchase::where('event_id', $event->id)
            ->whereNotNull('ticket_number')
            ->count();

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
    public function approveSinglePurchase(string $transactionId): array
    {
        try {
            DB::beginTransaction();

            $purchases = Purchase::where('transaction_id', $transactionId)
                ->where('status', 'pending')
                ->whereNotNull('ticket_number')
                ->get();

            if ($purchases->isEmpty()) {
                throw new Exception('No se encontraron compras individuales pendientes');
            }

            foreach ($purchases as $purchase) {
                $purchase->update(['status' => 'completed']);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Compra(s) aprobada(s) exitosamente.',
                'data' => [
                    'transaction_id' => $transactionId,
                    'quantity' => $purchases->count(),
                    'ticket_numbers' => $purchases->pluck('ticket_number')->toArray(),
                    'status' => 'completed'
                ]
            ];
        } catch (Exception $exception) {
            DB::rollBack();
            return ['success' => false, 'message' => $exception->getMessage()];
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

            // ✅ Validar que todos los números estén disponibles (con lock)
            $reservedNumbers = [];

            foreach ($ticketNumbers as $index => $ticketNumber) {
                $isUsed = Purchase::where('event_id', $event->id)
                    ->where('ticket_number', $ticketNumber)
                    ->lockForUpdate()
                    ->exists();

                if ($isUsed) {
                    $reservedNumbers[$index] = $ticketNumber;
                }
            }

            // ✅ Si hay números reservados, lanzar ValidationException estructurada
            if (!empty($reservedNumbers)) {
                $errors = [];

                // Opción 1: Un solo mensaje con todos los números (RECOMENDADO - MÁS SIMPLE)
                $errors['ticket_numbers'] = [
                    'Los siguientes números ya están reservados: ' . implode(', ', $reservedNumbers) . '. Por favor, selecciona otros números.'
                ];

                /* Opción 2: Un error por cada número (si quieres ser muy específico)
            foreach ($reservedNumbers as $index => $number) {
                $errors["ticket_numbers.{$index}"] = [
                    "El número {$number} ya está reservado. Por favor, selecciona otro número."
                ];
            }
            */

                throw ValidationException::withMessages($errors);
            }

            // ✅ Generar un solo transaction_id para agrupar todas las compras
            $transactionId = 'TXN-' . strtoupper(Str::random(12));

            $purchases = [];

            // ✅ Crear una compra por cada número
            foreach ($ticketNumbers as $ticketNumber) {
                $purchaseData = new DTOsPurchase(
                    event_id: $data->getEventId(),
                    event_price_id: $data->getEventPriceId(),
                    payment_method_id: $data->getPaymentMethodId(),
                    quantity: 1,
                    email: $data->getEmail(),
                    whatsapp: $data->getWhatsapp(),
                    currency: $data->getCurrency(),
                    user_id: $data->getUserId(),
                    specific_numbers: [$ticketNumber],
                    payment_reference: $data->getPaymentReference(),
                    payment_proof_url: $data->getPaymentProofUrl(),
                    total_amount: $eventPrice->amount
                );

                $purchase = $this->PurchaseRepository->createSinglePurchase(
                    $purchaseData,
                    $eventPrice->amount,
                    $transactionId,
                    $ticketNumber
                );

                $purchases[] = $purchase;
            }

            // ✅ Generar un solo QR Code para toda la transacción
            $qrImageUrl = $this->generatePurchaseQRCode($transactionId);

            if ($qrImageUrl) {
                Purchase::where('transaction_id', $transactionId)
                    ->update(['qr_code_url' => $qrImageUrl]);
            }

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'transaction_id' => $transactionId,
                    'ticket_numbers' => $ticketNumbers,
                    'quantity' => count($ticketNumbers),
                    'total_amount' => $data->getTotalAmount(),
                    'qr_code_url' => $qrImageUrl,
                ],
                'message' => 'Compra registrada exitosamente. Números reservados: '
                    . implode(', ', $ticketNumbers)
                    . '. Espera la aprobación del administrador.'
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            // ✅ Re-lanzar ValidationException para que Laravel la maneje correctamente
            throw $e;
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Error creating single purchase: ' . $exception->getMessage());

            // ✅ Para otros errores, también usar ValidationException
            throw ValidationException::withMessages([
                'general' => [$exception->getMessage()]
            ]);
        }
    }
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

            // ✅ Validar disponibilidad con lock
            foreach ($ticketNumbers as $ticketNumber) {
                $isUsed = Purchase::where('event_id', $event->id)
                    ->where('ticket_number', $ticketNumber)
                    ->lockForUpdate()
                    ->exists();

                if ($isUsed) {
                    throw new Exception("El número {$ticketNumber} ya está reservado.");
                }
            }

            $transactionId = 'ADM-' . strtoupper(Str::random(12));
            $purchases = [];

            // ✅ Si auto_approve = true, status = 'completed', sino 'pending'
            $status = $autoApprove ? 'completed' : 'pending';

            foreach ($ticketNumbers as $ticketNumber) {
                $purchaseData = new DTOsPurchase(
                    event_id: $data->getEventId(),
                    event_price_id: $data->getEventPriceId(),
                    payment_method_id: $data->getPaymentMethodId(),
                    quantity: 1,
                    email: $data->getEmail(),
                    whatsapp: $data->getWhatsapp(),
                    currency: $data->getCurrency(),
                    user_id: $data->getUserId(), // ID del admin autenticado
                    specific_numbers: [$ticketNumber],
                    payment_reference: $data->getPaymentReference(),
                    payment_proof_url: $data->getPaymentProofUrl(),
                    total_amount: $eventPrice->amount
                );

                $purchase = $this->PurchaseRepository->createAdminPurchase(
                    $purchaseData,
                    $eventPrice->amount,
                    $transactionId,
                    $ticketNumber,
                    $status // ✅ Pasar el status
                );

                $purchases[] = $purchase;
            }

            // ✅ Generar QR Code
            $qrImageUrl = $this->generatePurchaseQRCode($transactionId);

            if ($qrImageUrl) {
                Purchase::where('transaction_id', $transactionId)
                    ->update(['qr_code_url' => $qrImageUrl]);
            }

            DB::commit();

            // ✅ Mensaje dinámico según el status
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

    public function createAdminRandomPurchase(DTOsPurchase $data, bool $autoApprove = true)
    {
        try {
            DB::beginTransaction();

            $event = Event::findOrFail($data->getEventId());
            $eventPrice = EventPrice::findOrFail($data->getEventPriceId());

            // ✅ Validar disponibilidad de números
            $availableCount = $this->getAvailableNumbersCount($event);

            if ($availableCount < $data->getQuantity()) {
                throw new Exception("Solo quedan {$availableCount} números disponibles.");
            }

            // ✅ Generar transaction_id único con prefijo ADM
            $transactionId = 'ADM-' . strtoupper(Str::random(12));

            $totalAmount = $data->getTotalAmount();
            $purchases = [];

            // ✅ Crear registros sin números asignados
            for ($i = 0; $i < $data->getQuantity(); $i++) {
                $purchaseData = new DTOsPurchase(
                    event_id: $data->getEventId(),
                    event_price_id: $data->getEventPriceId(),
                    payment_method_id: $data->getPaymentMethodId(),
                    quantity: 1,
                    email: $data->getEmail(),
                    whatsapp: $data->getWhatsapp(),
                    currency: $data->getCurrency(),
                    user_id: $data->getUserId(), // ID del admin
                    specific_numbers: null,
                    payment_reference: $data->getPaymentReference(),
                    payment_proof_url: $data->getPaymentProofUrl(),
                    total_amount: $totalAmount
                );

                $purchase = $this->PurchaseRepository->createAdminRandomPurchase(
                    $purchaseData,
                    $eventPrice->amount,
                    $transactionId
                );

                $purchases[] = $purchase;
            }

            // ✅ Generar QR Code
            $qrImageUrl = $this->generatePurchaseQRCode($transactionId);

            if ($qrImageUrl) {
                Purchase::where('transaction_id', $transactionId)
                    ->update(['qr_code_url' => $qrImageUrl]);
            }

            DB::commit();

            // ✅ Si auto_approve es true, ejecutar lógica de aprobación inmediatamente
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

            // ✅ Si no se aprueba automáticamente
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
}
