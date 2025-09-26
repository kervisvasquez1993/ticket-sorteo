<?php

namespace App\Interfaces\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\Models\Purchase;

interface IPurchaseRepository 
{
    public function getAllPurchases();
    public function getPurchaseById($id): Purchase;
    public function createPurchase(DTOsPurchase $data): Purchase;
    public function updatePurchase(DTOsPurchase $data, Purchase $Purchase): Purchase;
    public function deletePurchase(Purchase $Purchase): Purchase;
}
