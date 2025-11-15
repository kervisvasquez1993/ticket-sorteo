<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\NewPurchaseNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class SendPurchaseNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos
     */
    public $tries = 3;

    /**
     * Tiempo máximo de ejecución (segundos)
     */
    public $timeout = 60;

    protected $purchaseData;
    protected $purchaseType;

    /**
     * Create a new job instance.
     */
    public function __construct(array $purchaseData, string $purchaseType)
    {
        $this->purchaseData = $purchaseData;
        $this->purchaseType = $purchaseType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // ✅ Obtener solo usuarios con rol 'admin'
            $administrators = User::where('role', 'admin')->get();

            // También puedes usar el scope:
            // $administrators = User::admins()->get();

            if ($administrators->isEmpty()) {
                Log::warning('No hay administradores para notificar', [
                    'transaction_id' => $this->purchaseData['transaction_id']
                ]);
                return;
            }

            // Enviar notificación a cada administrador
            foreach ($administrators as $admin) {
                $admin->notify(new NewPurchaseNotification($this->purchaseData, $this->purchaseType));
            }

            Log::info('Notificaciones enviadas exitosamente', [
                'transaction_id' => $this->purchaseData['transaction_id'],
                'data' => $this->purchaseData,
                'type' => $this->purchaseType,
                'admin_count' => $administrators->count(),
                'admin_emails' => $administrators->pluck('email')->toArray()
            ]);
        } catch (Exception $e) {
            Log::error('Error al enviar notificaciones en el job: ' . $e->getMessage(), [
                'transaction_id' => $this->purchaseData['transaction_id'] ?? 'unknown',
                'type' => $this->purchaseType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-lanzar la excepción para que Laravel reintente el job
            throw $e;
        }
    }

    /**
     * Manejar el fallo del job después de todos los intentos
     */
    public function failed(Exception $exception): void
    {
        Log::error('Job de notificación falló después de todos los intentos', [
            'transaction_id' => $this->purchaseData['transaction_id'] ?? 'unknown',
            'type' => $this->purchaseType,
            'error' => $exception->getMessage()
        ]);
    }
}
