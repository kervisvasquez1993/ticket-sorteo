<?php

namespace App\Interfaces\Purchase;

use App\DTOs\Purchase\DTOsAddTickets;
use App\DTOs\Purchase\DTOsPurchase;
use App\DTOs\Purchase\DTOsPurchaseFilter;

interface IPurchaseServices
{
    public function getAllPurchases(?DTOsPurchaseFilter $filters = null);
    public function getPurchaseById($id);
    public function createPurchase(DTOsPurchase $data);
    public function updatePurchase(DTOsPurchase $data, $id);
    public function deletePurchase($id);
    public function getUserPurchases($userId);
    public function getPurchaseSummary($transactionId);
    public function getPurchaseByTransaction(string $transactionId);
    public function approvePurchase(string $transactionId);
    public function rejectPurchase(string $transactionId);
    public function getPurchasesByEvent(string $eventId);
    public function createSinglePurchase(DTOsPurchase $data);
    public function createAdminPurchase(DTOsPurchase $data, bool $autoApprove = false);
    public function createAdminRandomPurchase(DTOsPurchase $data, bool $autoApprove = true);
    public function getPurchasesByWhatsApp(string $whatsapp);
    public function getPurchasesByIdentificacion(string $identificacion);
    public function checkTicketAvailability(int $eventId, string $ticketNumber): array;
    public function getMassivePurchaseStatus(string $transactionId): array;
    public function createMassivePurchaseAsync(DTOsPurchase $data, bool $autoApprove = true): array;
    public function addTicketsToTransaction(DTOsAddTickets $dto): array;
    public function removeTicketsFromTransaction(
        string $transactionId,
        array $ticketNumbersToRemove
    ): array;
}
