<?php

namespace App\Services\Notification;

use App\Models\Purchase;
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

        $this->httpClient = new Client([
            'timeout' => $this->timeout,
            'verify' => false,
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * ✅ Formatear array de números de ticket
     */
    private function formatTicketNumbers(array $ticketNumbers): array
    {
        return array_map(function ($number) {
            return Purchase::formatTicketNumber($number);
        }, $ticketNumbers);
    }

    /**
     * ✅ Convertir array de tickets formateados a string legible con #
     */
    private function formatTicketsForMessage(array $ticketNumbers): string
    {
        $formatted = $this->formatTicketNumbers($ticketNumbers);
        return implode(', ', array_map(fn($num) => "#{$num}", $formatted));
    }

    /**
     * Enviar notificación de aprobación de compra
     */
    public function sendApprovalNotification(
        string $whatsapp,
        string $transactionId,
        array $ticketNumbers,
        int $quantity,
        string $fullname = ''
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
            'ticket_numbers' => $ticketNumbers,
            'fullname' => $fullname
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
     * ✅ Construir mensaje de aprobación con números formateados
     */
    private function buildApprovalMessage(array $data): string
    {
        // ✅ Formatear los números de tickets usando el método helper
        $ticketsFormatted = $this->formatTicketsForMessage($data['ticket_numbers']);

        // Obtener la URL base desde las variables de entorno
        $baseUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/');
        $purchaseUrl = "{$baseUrl}";

        // ✅ Construir el saludo personalizado
        $greeting = !empty($data['fullname'])
            ? "¡Hola *{$data['fullname']}*! 👋\n\n"
            : "¡Hola! 👋\n\n";

        return $greeting .
            "✅ *¡Tu compra ha sido aprobada!*\n\n" .
            "🎫 *Tickets:* {$ticketsFormatted}\n\n" .
            "📦 *Cantidad:* {$data['quantity']} ticket(s)\n\n" .
            "🔗 *Ver mi compra:* {$purchaseUrl}\n\n" .
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

        $supportPhone = env('SUPPORT_PHONE', '+58 424-5750827');

        return "❌ *Tu compra ha sido rechazada*\n\n" .
            "Lamentablemente tu transacción no pudo ser procesada.{$reasonText}\n\n" .
            "Para más información, contacta con soporte:\n" .
            "📱 {$supportPhone}\n\n" .
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
                'endpoint' => $endpoint,
                'message_preview' => substr($message, 0, 100) . '...'
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
