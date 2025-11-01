<?php

namespace App\Interfaces\WhatsAppStatus;

use App\DTOs\WhatsAppStatus\DTOsWhatsAppStatus;

interface IWhatsAppStatusServices
{
    /**
     * Obtener el estado actual del servicio de WhatsApp
     *
     * @return array{success: bool, data?: array, message?: string}
     */
    public function getWhatsAppStatus();

    /**
     * Verificar si el servicio de WhatsApp está conectado
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Obtener el código QR para conectar WhatsApp
     *
     * @return array{success: bool, data?: array, message?: string}
     */
    public function getQRCode();

    // ============================================
    // Métodos CRUD base (si necesitas persistencia)
    // ============================================

    /**
     * Obtener todos los registros de status
     *
     * @return array{success: bool, data?: mixed, message?: string}
     */
    public function getAllWhatsAppStatuss();

    /**
     * Obtener un registro de status por ID
     *
     * @param int|string $id
     * @return array{success: bool, data?: mixed, message?: string}
     */
    public function getWhatsAppStatusById($id);

    /**
     * Crear un nuevo registro de status
     *
     * @param DTOsWhatsAppStatus $data
     * @return array{success: bool, data?: mixed, message?: string}
     */
    public function createWhatsAppStatus(DTOsWhatsAppStatus $data);

    /**
     * Actualizar un registro de status existente
     *
     * @param DTOsWhatsAppStatus $data
     * @param int|string $id
     * @return array{success: bool, data?: mixed, message?: string}
     */
    public function updateWhatsAppStatus(DTOsWhatsAppStatus $data, $id);

    /**
     * Eliminar un registro de status
     *
     * @param int|string $id
     * @return array{success: bool, data?: mixed, message?: string}
     */
    public function deleteWhatsAppStatus($id);
}
