<?php

namespace App\Interfaces\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\Models\Purchase;

interface IPurchaseRepository
{
    public function getAllPurchases();
    public function getPurchaseById($id): Purchase;

    public function updatePurchase(DTOsPurchase $data, Purchase $Purchase): Purchase;
    public function deletePurchase(Purchase $Purchase): Purchase;
    public function getUserPurchases($userId);
    public function getPurchasesByEvent($eventId);
    public function isNumberAvailable($eventId, $ticketNumber): bool;
    public function createPurchase(DTOsPurchase $data, $amount, $transactionId = null): Purchase;
    public function getPurchasesByTransaction($transactionId);
}
