<?php

namespace App\Interfaces\Purchase;

use App\DTOs\Purchase\DTOsAddTickets;
use App\DTOs\Purchase\DTOsPurchase;
use App\DTOs\Purchase\DTOsPurchaseFilter;
use App\Models\Purchase;

interface IPurchaseRepository
{
    // ====================================================================
    // MÉTODOS BÁSICOS CRUD
    // ====================================================================

    /**
     * Obtener todas las compras
     */
    public function getAllPurchases();

    /**
     * Obtener compra por ID
     */
    public function getPurchaseById($id): Purchase;

    /**
     * Crear una compra individual (legacy)
     */
    public function createPurchase(DTOsPurchase $data, $amount, $transactionId = null): Purchase;

    /**
     * Actualizar compra
     */
    public function updatePurchase(DTOsPurchase $data, Purchase $Purchase): Purchase;

    /**
     * Eliminar compra
     */
    public function deletePurchase(Purchase $Purchase): Purchase;

    // ====================================================================
    // MÉTODOS OPTIMIZADOS PARA INSERT MASIVO
    // ====================================================================

    /**
     * Insert masivo de múltiples compras
     *
     * @param array $purchaseRecords Array de registros preparados
     * @return int Cantidad de registros insertados
     */
    public function bulkInsertPurchases(array $purchaseRecords): int;

    /**
     * Preparar array de datos para insert masivo
     *
     * @param DTOsPurchase $data
     * @param float $amount
     * @param string $transactionId
     * @param string|null $ticketNumber
     * @param string $status
     * @return array
     */
    public function preparePurchaseRecord(
        DTOsPurchase $data,
        float $amount,
        string $transactionId,
        ?string $ticketNumber = null,
        string $status = 'pending'
    ): array;

    // ====================================================================
    // MÉTODOS DE VALIDACIÓN Y VERIFICACIÓN
    // ====================================================================

    /**
     * Verificar múltiples números de ticket de una vez
     *
     * @param int $eventId
     * @param array $ticketNumbers
     * @return array Números que ya están reservados
     */
    public function getReservedTicketNumbers(int $eventId, array $ticketNumbers): array;

    /**
     * Obtener números usados de un evento
     *
     * @param int $eventId
     * @return array
     */
    public function getUsedTicketNumbers(int $eventId): array;

    /**
     * Verificar si un número está disponible (legacy)
     *
     * @param mixed $eventId
     * @param mixed $ticketNumber
     * @return bool
     */
    public function isNumberAvailable($eventId, $ticketNumber): bool;

    /**
     * Verificar si un transaction_id existe
     *
     * @param string $transactionId
     * @return bool
     */
    public function transactionIdExists(string $transactionId): bool;

    // ====================================================================
    // MÉTODOS DE ACTUALIZACIÓN MASIVA
    // ====================================================================

    /**
     * Actualizar QR Code para toda una transacción
     *
     * @param string $transactionId
     * @param string $qrCodeUrl
     * @return int Cantidad de registros actualizados
     */
    public function updateQrCodeByTransaction(string $transactionId, string $qrCodeUrl): int;

    /**
     * Actualizar status de toda una transacción
     *
     * @param string $transactionId
     * @param string $status
     * @return int Cantidad de registros actualizados
     */
    public function updateStatusByTransaction(string $transactionId, string $status): int;

    /**
     * Actualizar status con condiciones específicas
     *
     * @param string $transactionId
     * @param string $newStatus
     * @param string|null $currentStatus Filtrar por status actual
     * @param bool|null $hasTicketNumber true = con número, false = sin número, null = todos
     * @return int Cantidad de registros actualizados
     */
    public function updateStatusByTransactionAndConditions(
        string $transactionId,
        string $newStatus,
        ?string $currentStatus = null,
        ?bool $hasTicketNumber = null
    ): int;

    /**
     * Asignar número de ticket a una compra específica
     *
     * @param int $purchaseId
     * @param string $ticketNumber
     * @param string $status
     * @return bool
     */
    public function assignTicketNumber(int $purchaseId, string $ticketNumber, string $status = 'completed'): bool;

    // ====================================================================
    // MÉTODOS DE CONSULTA POR TRANSACCIÓN
    // ====================================================================

    /**
     * Obtener compras por transaction_id
     */
    public function getPurchasesByTransaction($transactionId);

    /**
     * Obtener compras pendientes por transaction_id con lock
     *
     * @param string $transactionId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingPurchasesByTransaction(string $transactionId);

    /**
     * Contar compras por transacción
     *
     * @param string $transactionId
     * @return int
     */
    public function countPurchasesByTransaction(string $transactionId): int;

    // ====================================================================
    // MÉTODOS DE CONSULTA GENERAL
    // ====================================================================

    /**
     * Obtener compras de un usuario
     */
    public function getUserPurchases($userId);

    /**
     * Obtener compras de un evento
     */
    public function getPurchasesByEvent($eventId);

    /**
     * Obtener compras por WhatsApp
     *
     * @param string $whatsapp
     * @return \Illuminate\Support\Collection
     */
    public function getPurchasesByWhatsApp(string $whatsapp);

    /**
     * Obtener compras por identificación
     *
     * @param string $identificacion
     * @return \Illuminate\Support\Collection
     */
    public function getPurchasesByIdentificacion(string $identificacion);

    // ====================================================================
    // MÉTODOS DE AGRUPACIÓN
    // ====================================================================

    /**
     * Obtener compras agrupadas con filtros
     *
     * @param DTOsPurchaseFilter|null $filters
     * @return array
     */
    public function getGroupedPurchases(?DTOsPurchaseFilter $filters = null);

    /**
     * Obtener compras agrupadas de un usuario
     *
     * @param mixed $userId
     * @return \Illuminate\Support\Collection
     */
    public function getGroupedUserPurchases($userId);

    /**
     * Obtener compra agrupada por transaction_id
     *
     * @param string $transactionId
     * @return array|null
     */
    public function getPurchaseByTransaction(string $transactionId);

    /**
     * Obtener compras agrupadas de un evento
     *
     * @param string $eventId
     * @return \Illuminate\Support\Collection
     */
    public function getGroupedPurchasesByEvent(string $eventId);

    // ====================================================================
    // MÉTODOS DE GESTIÓN DE TICKETS
    // ====================================================================

    /**
     * Verificar disponibilidad de un ticket
     *
     * @param int $eventId
     * @param string $ticketNumber
     * @return array
     */
    public function checkTicketAvailability(int $eventId, string $ticketNumber): array;

    /**
     * Rechazar compra y liberar números
     *
     * @param string $transactionId
     * @param string|null $reason
     * @return int Cantidad de compras rechazadas
     */
    public function rejectPurchaseAndFreeNumbers(string $transactionId, ?string $reason = null): int;

    /**
     * Agregar tickets a una transacción existente
     *
     * @param DTOsAddTickets $dto
     * @return array
     */
    public function addTicketsToTransaction(DTOsAddTickets $dto): array;

    /**
     * Remover tickets de una transacción
     *
     * @param string $transactionId
     * @param array $ticketNumbersToRemove
     * @return int Cantidad de tickets removidos
     */
    public function removeTicketsFromTransaction(
        string $transactionId,
        array $ticketNumbersToRemove
    ): int;

    /**
     * Obtener tickets de una transacción (sin rechazados)
     *
     * @param string $transactionId
     * @return array
     */
    public function getTransactionTickets(string $transactionId): array;

    /**
     * ✅ NUEVO: Ajustar cantidad de compras pendientes sin números asignados
     *
     * @param string $transactionId
     * @param int $newQuantity
     * @return array [action, previous_quantity, new_quantity, added_count|removed_count, message]
     */
    public function adjustPendingPurchaseQuantity(string $transactionId, int $newQuantity): array;

    // ====================================================================
    // MÉTODOS LEGACY (Para compatibilidad - DEPRECADOS)
    // ====================================================================

    /**
     * @deprecated Usar bulkInsertPurchases() en su lugar
     */
    public function createSinglePurchase(DTOsPurchase $data, $amount, $transactionId, $ticketNumber): Purchase;

    /**
     * @deprecated Usar bulkInsertPurchases() en su lugar
     */
    public function createAdminPurchase(DTOsPurchase $data, $amount, $transactionId, $ticketNumber, $status): Purchase;

    /**
     * @deprecated Usar bulkInsertPurchases() en su lugar
     */
    public function createAdminRandomPurchase(DTOsPurchase $data, $amount, $transactionId): Purchase;
}
