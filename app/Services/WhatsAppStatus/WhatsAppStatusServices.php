<?php

namespace App\Services\WhatsAppStatus;

use App\DTOs\WhatsAppStatus\DTOsWhatsAppStatus;
use App\Interfaces\WhatsAppStatus\IWhatsAppStatusServices;
use App\Interfaces\WhatsAppStatus\IWhatsAppStatusRepository;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class WhatsAppStatusServices implements IWhatsAppStatusServices
{
    protected IWhatsAppStatusRepository $whatsAppStatusRepository;
    private Client $httpClient;
    private string $whatsappServiceUrl;
    private int $timeout;

    public function __construct(IWhatsAppStatusRepository $whatsAppStatusRepositoryInterface)
    {
        $this->whatsAppStatusRepository = $whatsAppStatusRepositoryInterface;

        // Configuración del cliente HTTP
        $this->whatsappServiceUrl = config('services.whatsapp.url');
        $this->timeout = config('services.whatsapp.timeout', 10);

        $this->httpClient = new Client([
            'timeout' => $this->timeout,
            'verify' => false, // Solo en desarrollo
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * Obtener el estado del servicio de WhatsApp
     */
    public function getWhatsAppStatus()
    {
        try {
            $endpoint = rtrim($this->whatsappServiceUrl, '/') . '/whatsapp/status';

            Log::info("📡 Consultando estado del servicio de WhatsApp", [
                'endpoint' => $endpoint
            ]);

            $response = $this->httpClient->get($endpoint);
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            Log::info("📥 Respuesta del servicio de WhatsApp", [
                'status' => $statusCode,
                'body' => $body
            ]);

            if ($statusCode >= 200 && $statusCode < 300 && $body) {
                return [
                    'success' => true,
                    'data' => [
                        'connected' => $body['connected'] ?? false,
                        'qrAvailable' => $body['qrAvailable'] ?? false,
                        'message' => $body['message'] ?? 'Estado desconocido',
                        'timestamp' => now()->toIso8601String()
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => 'No se pudo obtener el estado del servicio',
                'data' => [
                    'connected' => false,
                    'qrAvailable' => false,
                    'message' => 'Servicio no disponible'
                ]
            ];

        } catch (GuzzleException $exception) {
            Log::error("❌ Error Guzzle al consultar estado de WhatsApp", [
                'error' => $exception->getMessage(),
                'code' => $exception->getCode()
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión con el servicio de WhatsApp',
                'data' => [
                    'connected' => false,
                    'qrAvailable' => false,
                    'message' => 'Error de conexión'
                ]
            ];

        } catch (Exception $exception) {
            Log::error("❌ Error general al consultar estado de WhatsApp", [
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    /**
     * Verificar si el servicio está conectado
     */
    public function isConnected(): bool
    {
        try {
            $result = $this->getWhatsAppStatus();
            return $result['success'] && ($result['data']['connected'] ?? false);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtener código QR si está disponible
     */
    public function getQRCode()
    {
        try {
            $endpoint = rtrim($this->whatsappServiceUrl, '/') . '/whatsapp/qr';

            Log::info("📡 Solicitando código QR", [
                'endpoint' => $endpoint
            ]);

            $response = $this->httpClient->get($endpoint);
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success' => true,
                    'data' => $body
                ];
            }

            return [
                'success' => false,
                'message' => 'No se pudo obtener el código QR'
            ];

        } catch (Exception $exception) {
            Log::error("❌ Error al obtener código QR", [
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    // Implementación de métodos de la interfaz (si necesitas persistencia)
    public function getAllWhatsAppStatuss()
    {
        try {
            $results = $this->whatsAppStatusRepository->getAllWhatsAppStatuss();
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

    public function getWhatsAppStatusById($id)
    {
        try {
            $results = $this->whatsAppStatusRepository->getWhatsAppStatusById($id);
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

    public function createWhatsAppStatus(DTOsWhatsAppStatus $data)
    {
        try {
            $results = $this->whatsAppStatusRepository->createWhatsAppStatus($data);
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

    public function updateWhatsAppStatus(DTOsWhatsAppStatus $data, $id)
    {
        try {
            $whatsAppStatus = $this->whatsAppStatusRepository->getWhatsAppStatusById($id);
            $results = $this->whatsAppStatusRepository->updateWhatsAppStatus($data, $whatsAppStatus);
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

    public function deleteWhatsAppStatus($id)
    {
        try {
            $whatsAppStatus = $this->whatsAppStatusRepository->getWhatsAppStatusById($id);
            $results = $this->whatsAppStatusRepository->deleteWhatsAppStatus($whatsAppStatus);
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
}
