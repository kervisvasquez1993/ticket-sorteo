<?php

namespace App\Interfaces\Purchase;

use App\DTOs\Purchase\DTOsPurchase;

interface IPurchaseServices
{
    public function getAllPurchases();
    public function getPurchaseById($id);
    public function createPurchase(DTOsPurchase $data);
    public function updatePurchase(DTOsPurchase $data, $id);
    public function deletePurchase($id);
    public function getUserPurchases($userId);
}
