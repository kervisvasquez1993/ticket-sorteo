<?php

namespace App\Http\Controllers\Api\WhatsAppStatus;

use App\Http\Controllers\Controller;
use App\Interfaces\WhatsAppStatus\IWhatsAppStatusServices;
use Illuminate\Http\Request;

class WhatsAppStatusController extends Controller
{
    protected IWhatsAppStatusServices $whatsAppStatusServices;

    public function __construct(IWhatsAppStatusServices $whatsAppStatusServicesInterface)
    {
        $this->whatsAppStatusServices = $whatsAppStatusServicesInterface;
    }

    /**
     * Obtener el estado actual del servicio de WhatsApp
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status()
    {
        $result = $this->whatsAppStatusServices->getWhatsAppStatus();

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message'],
                'data' => $result['data'] ?? null
            ], 503); // Service Unavailable
        }

        return response()->json($result['data'], 200);
    }

    /**
     * Verificar si el servicio está conectado
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function isConnected()
    {
        $isConnected = $this->whatsAppStatusServices->isConnected();

        return response()->json([
            'connected' => $isConnected
        ], 200);
    }

    /**
     * Obtener código QR si está disponible
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function qrCode()
    {
        $result = $this->whatsAppStatusServices->getQRCode();

        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 404);
        }

        return response()->json($result['data'], 200);
    }
}
