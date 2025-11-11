<?php

namespace App\Interfaces\Purchase;

use App\DTOs\Purchase\DTOsAddTickets;
use App\DTOs\Purchase\DTOsPurchase;
use App\DTOs\Purchase\DTOsPurchaseFilter;
use App\DTOs\Purchase\DTOsUpdatePurchaseQuantity;

interface IPurchaseServices
{
    // ====================================================================
    // MÉTODOS BÁSICOS CRUD
    // ====================================================================

    /**
     * Obtener todas las compras con filtros opcionales
     */
    public function getAllPurchases(?DTOsPurchaseFilter $filters = null);

    /**
     * Obtener compra por ID
     */
    public function getPurchaseById($id);

    /**
     * Crear compra por cantidad (números aleatorios)
     */
    public function createPurchase(DTOsPurchase $data);

    /**
     * Actualizar compra
     */
    public function updatePurchase(DTOsPurchase $data, $id);

    /**
     * Eliminar compra
     */
    public function deletePurchase($id);

    // ====================================================================
    // MÉTODOS DE CREACIÓN DE COMPRAS
    // ====================================================================

    /**
     * Crear compra con números específicos
     */
    public function createSinglePurchase(DTOsPurchase $data);

    /**
     * Crear compra admin con números específicos
     */
    public function createAdminPurchase(DTOsPurchase $data, bool $autoApprove = false);

    /**
     * Crear compra admin con números aleatorios
     */
    public function createAdminRandomPurchase(DTOsPurchase $data, bool $autoApprove = true);

    /**
     * Crear compra masiva en segundo plano
     */
    public function createMassivePurchaseAsync(DTOsPurchase $data, bool $autoApprove = true): array;

    // ====================================================================
    // MÉTODOS DE APROBACIÓN Y RECHAZO
    // ====================================================================

    /**
     * Aprobar compra (asigna números si no los tiene)
     */
    public function approvePurchase(string $transactionId);

    /**
     * Rechazar compra
     */
    public function rejectPurchase(string $transactionId);

    // ====================================================================
    // MÉTODOS DE CONSULTA
    // ====================================================================

    /**
     * Obtener compras de un usuario
     */
    public function getUserPurchases($userId);

    /**
     * Obtener resumen de una transacción
     */
    public function getPurchaseSummary($transactionId);

    /**
     * Obtener compra por transaction_id
     */
    public function getPurchaseByTransaction(string $transactionId);

    /**
     * Obtener compras de un evento
     */
    public function getPurchasesByEvent(string $eventId);

    /**
     * Obtener compras por WhatsApp
     */
    public function getPurchasesByWhatsApp(string $whatsapp);

    /**
     * Obtener compras por identificación
     */
    public function getPurchasesByIdentificacion(string $identificacion);

    /**
     * Obtener estado de compra masiva en proceso
     */
    public function getMassivePurchaseStatus(string $transactionId): array;

    // ====================================================================
    // MÉTODOS DE GESTIÓN DE TICKETS
    // ====================================================================

    /**
     * Verificar disponibilidad de un ticket
     */
    public function checkTicketAvailability(int $eventId, string $ticketNumber): array;

    /**
     * Agregar tickets a una transacción existente
     */
    public function addTicketsToTransaction(DTOsAddTickets $dto): array;

    /**
     * Remover tickets de una transacción
     */
    public function removeTicketsFromTransaction(
        string $transactionId,
        array $ticketNumbersToRemove
    ): array;

    /**
     * ✅ NUEVO: Editar cantidad de una compra pendiente (sin números asignados)
     *
     * Permite aumentar o disminuir la cantidad de tickets de una compra
     * que aún no ha sido aprobada y no tiene números asignados.
     *
     * @param DTOsUpdatePurchaseQuantity $dto
     * @return array [success, message, data]
     */
    public function updatePendingPurchaseQuantity(DTOsUpdatePurchaseQuantity $dto): array;
}
