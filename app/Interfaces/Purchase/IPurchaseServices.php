<?php

namespace App\Interfaces\Purchase;

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
}
