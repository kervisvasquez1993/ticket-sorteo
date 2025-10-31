<?php

namespace App\Services\Notification;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    private string $whatsappServiceUrl;
    private string $frontendUrl;
    private int $timeout;
    private Client $httpClient;

    public function __construct()
    {
        $this->whatsappServiceUrl = config('services.whatsapp.url');
        $this->frontendUrl = config('app.frontend_url');
        $this->timeout = config('services.whatsapp.timeout', 10);

        // ✅ Configurar Guzzle con opciones específicas
        $this->httpClient = new Client([
            'timeout' => $this->timeout,
            'verify' => false, // Deshabilitar verificación SSL (solo en desarrollo/testing)
            'http_errors' => false, // No lanzar excepciones en errores HTTP
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
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
            'purchase_url' => $purchaseUrl,
            'ticket_numbers' => $ticketNumbers
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
     * Construir mensaje de aprobación con enlace clickeable
     */
    private function buildApprovalMessage(array $data): string
    {
        $ticketsText = count($data['ticket_numbers']) <= 3
            ? implode(', ', $data['ticket_numbers'])
            : count($data['ticket_numbers']) . ' tickets';

        return "✅ *¡Tu compra ha sido aprobada!*\n\n" .
            "🎫 *Tickets:* {$ticketsText}\n" .
            "📦 *Cantidad:* {$data['quantity']} ticket(s)\n\n" .
            "👉 *Ver detalles de tu compra:*\n" .
            "{$data['purchase_url']}\n\n" .
            "_(Haz clic en el enlace para ver todos los detalles)_\n\n" .
            "¡Gracias por tu compra! 🎉";
    }

    /**
     * Construir mensaje de rechazo
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
     * Enviar notificación al servicio de WhatsApp usando Guzzle
     */
    private function sendNotification(
        string $whatsapp,
        string $message,
        string $transactionId,
        string $type
    ): bool {
        try {
            $phone = $this->normalizePhoneNumber($whatsapp);
            // ✅ RUTA CORRECTA: /whatsapp/send
            $endpoint = rtrim($this->whatsappServiceUrl, '/') . '/whatsapp/send';

            Log::info("📤 Enviando notificación de WhatsApp", [
                'transaction_id' => $transactionId,
                'phone' => $phone,
                'type' => $type,
                'endpoint' => $endpoint
            ]);

            // ✅ PAYLOAD: phoneNumber y message
            $response = $this->httpClient->post($endpoint, [
                'json' => [
                    'phoneNumber' => $phone,
                    'message' => $message
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            Log::info("📥 Respuesta del servicio de WhatsApp", [
                'transaction_id' => $transactionId,
                'status' => $statusCode,
                'body' => $body
            ]);

            if ($statusCode >= 200 && $statusCode < 300) {
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
                'status' => $statusCode,
                'body' => $body
            ]);

            return false;
        } catch (GuzzleException $exception) {
            Log::error("❌ Excepción Guzzle al enviar notificación de WhatsApp", [
                'transaction_id' => $transactionId,
                'phone' => $whatsapp,
                'type' => $type,
                'error' => $exception->getMessage(),
                'code' => $exception->getCode()
            ]);

            return false;
        } catch (Exception $exception) {
            Log::error("❌ Excepción general al enviar notificación de WhatsApp", [
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
            // ✅ Endpoint de status (ajusta según tu API)
            $endpoint = rtrim($this->whatsappServiceUrl, '/') . '/whatsapp/status';

            $response = $this->httpClient->get($endpoint);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (Exception $e) {
            Log::warning("⚠️ Servicio de WhatsApp no disponible: " . $e->getMessage());
            return false;
        }
    }
}
