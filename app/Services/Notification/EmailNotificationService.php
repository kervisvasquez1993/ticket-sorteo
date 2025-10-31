<?php

namespace App\Services\Notification;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    private string $frontendUrl;

    public function __construct()
    {
        $this->frontendUrl = config('app.frontend_url');
    }

    /**
     * Enviar notificación de aprobación de compra por email
     */
    public function sendApprovalNotification(
        string $email,
        string $transactionId,
        array $ticketNumbers,
        int $quantity,
        string $eventName
    ): bool {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info('No se envió notificación: Email no válido', [
                'transaction_id' => $transactionId,
                'email' => $email
            ]);
            return false;
        }

        try {
            $purchaseUrl = $this->buildPurchaseUrl($transactionId);

            $data = [
                'transaction_id' => $transactionId,
                'quantity' => $quantity,
                'purchase_url' => $purchaseUrl,
                'ticket_numbers' => $ticketNumbers,
                'event_name' => $eventName,
                'type' => 'approval'
            ];

            Mail::send('emails.purchase-approval', $data, function ($message) use ($email, $transactionId) {
                $message->to($email)
                    ->subject('✅ Tu compra ha sido aprobada - ' . $transactionId);
            });

            Log::info("✅ Email de aprobación enviado exitosamente", [
                'transaction_id' => $transactionId,
                'email' => $email,
                'quantity' => $quantity
            ]);

            return true;
        } catch (Exception $exception) {
            Log::error("❌ Error al enviar email de aprobación", [
                'transaction_id' => $transactionId,
                'email' => $email,
                'error' => $exception->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Enviar notificación de rechazo de compra por email
     */
    public function sendRejectionNotification(
        string $email,
        string $transactionId,
        ?string $reason = null,
        string $eventName = ''
    ): bool {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info('No se envió notificación: Email no válido', [
                'transaction_id' => $transactionId,
                'email' => $email
            ]);
            return false;
        }

        try {
            $data = [
                'transaction_id' => $transactionId,
                'reason' => $reason,
                'event_name' => $eventName,
                'type' => 'rejection'
            ];

            Mail::send('emails.purchase-rejection', $data, function ($message) use ($email, $transactionId) {
                $message->to($email)
                    ->subject('❌ Información sobre tu compra - ' . $transactionId);
            });

            Log::info("✅ Email de rechazo enviado exitosamente", [
                'transaction_id' => $transactionId,
                'email' => $email,
                'reason' => $reason
            ]);

            return true;
        } catch (Exception $exception) {
            Log::error("❌ Error al enviar email de rechazo", [
                'transaction_id' => $transactionId,
                'email' => $email,
                'error' => $exception->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Construir URL de la compra
     */
    private function buildPurchaseUrl(string $transactionId): string
    {
        return rtrim($this->frontendUrl, '/') . "/my-purchase/{$transactionId}";
    }

    /**
     * Verificar configuración SMTP
     */
    public function isServiceAvailable(): bool
    {
        try {
            $config = [
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
            ];

            return !empty($config['host']) && !empty($config['username']);
        } catch (Exception $e) {
            Log::warning("⚠️ Servicio de Email no disponible: " . $e->getMessage());
            return false;
        }
    }
}
