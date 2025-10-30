<?php

namespace App\Services\Notification;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    private string $whatsappServiceUrl;
    private string $frontendUrl;
    private int $timeout;

    public function __construct()
    {
        $this->whatsappServiceUrl = config('services.whatsapp.url');
        $this->frontendUrl = config('app.frontend_url');
        $this->timeout = config('services.whatsapp.timeout', 10);
    }

    /**
     * Enviar notificación de aprobación de compra
     */
    public function sendApprovalNotification(
        string $whatsapp,
        string $transactionId,
        array $ticketNumbers,
        int $quantity
    ): bool {
        if (empty($whatsapp)) {
            Log::info('No se envió notificación: WhatsApp no proporcionado', [
                'transaction_id' => $transactionId
            ]);
            return false;
        }

        $purchaseUrl = $this->buildPurchaseUrl($transactionId);

        $message = $this->buildApprovalMessage([
            'transaction_id' => $transactionId,
            'quantity' => $quantity,
            'purchase_url' => $purchaseUrl
        ]);

        return $this->sendNotification($whatsapp, $message, $transactionId, 'approval');
    }

    /**
     * Enviar notificación de rechazo de compra
     */
    public function sendRejectionNotification(
        string $whatsapp,
        string $transactionId,
        ?string $reason = null
    ): bool {
        if (empty($whatsapp)) {
            Log::info('No se envió notificación: WhatsApp no proporcionado', [
                'transaction_id' => $transactionId
            ]);
            return false;
        }

        $message = $this->buildRejectionMessage([
            'transaction_id' => $transactionId,
            'reason' => $reason
        ]);

        return $this->sendNotification($whatsapp, $message, $transactionId, 'rejection');
    }

    /**
     * Construir mensaje de aprobación (versión corta)
     */
    private function buildApprovalMessage(array $data): string
    {
        return "✅ *¡Tu compra ha sido aprobada!*\n\n" .
               "Tu transacción de *{$data['quantity']} ticket(s)* fue confirmada exitosamente.\n\n" .
               "🔗 Ver detalles completos:\n" .
               "{$data['purchase_url']}\n\n" .
               "¡Gracias por tu compra! 🎉";
    }

    /**
     * Construir mensaje de rechazo (versión corta)
     */
    private function buildRejectionMessage(array $data): string
    {
        $reasonText = !empty($data['reason'])
            ? "\n\n*Motivo:* {$data['reason']}"
            : '';

        return "❌ *Tu compra ha sido rechazada*\n\n" .
               "Lamentablemente tu transacción no pudo ser procesada.{$reasonText}\n\n" .
               "Para más información, contacta con soporte.\n" .
               "Disculpa las molestias.";
    }

    /**
     * Construir URL de la compra
     */
    private function buildPurchaseUrl(string $transactionId): string
    {
        return rtrim($this->frontendUrl, '/') . "/my-purchase/{$transactionId}";
    }

    /**
     * Enviar notificación al servicio de WhatsApp
     */
    private function sendNotification(
        string $whatsapp,
        string $message,
        string $transactionId,
        string $type
    ): bool {
        try {
            $phone = $this->normalizePhoneNumber($whatsapp);

            Log::info("📤 Enviando notificación de WhatsApp", [
                'transaction_id' => $transactionId,
                'phone' => $phone,
                'type' => $type,
                'url' => $this->whatsappServiceUrl
            ]);

            // ✅ Petición HTTP usando Laravel HTTP Client
            $response = Http::timeout($this->timeout)
                ->post("{$this->whatsappServiceUrl}/whatsapp/send-notification", [
                    'phone' => $phone,
                    'message' => $message
                ]);

            if ($response->successful()) {
                $body = $response->json();

                if (isset($body['success']) && $body['success']) {
                    Log::info("✅ Notificación enviada exitosamente", [
                        'transaction_id' => $transactionId,
                        'phone' => $phone,
                        'type' => $type
                    ]);
                    return true;
                }

                Log::warning("⚠️ Respuesta no exitosa del servicio de WhatsApp", [
                    'transaction_id' => $transactionId,
                    'response' => $body
                ]);
                return false;
            }

            Log::error("❌ Error en la respuesta del servicio de WhatsApp", [
                'transaction_id' => $transactionId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return false;

        } catch (Exception $exception) {
            Log::error("❌ Excepción al enviar notificación de WhatsApp", [
                'transaction_id' => $transactionId,
                'phone' => $whatsapp,
                'type' => $type,
                'error' => $exception->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Normalizar número de teléfono
     */
    private function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return $phone;
    }

    /**
     * Verificar si el servicio de WhatsApp está disponible
     */
    public function isServiceAvailable(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->whatsappServiceUrl}/whatsapp/status");

            return $response->successful();
        } catch (Exception $e) {
            Log::warning("⚠️ Servicio de WhatsApp no disponible: " . $e->getMessage());
            return false;
        }
    }
}
