<?php

namespace App\Repository\Purchase;

use App\DTOs\Purchase\DTOsPurchase;
use App\Interfaces\Purchase\IPurchaseRepository;
use App\Models\Purchase;

class PurchaseRepository implements IPurchaseRepository 
{
    public function getAllPurchases()
    {
        $Purchases = Purchase::all();
        return $Purchases;
    }
    
    public function getPurchaseById($id): Purchase
    {
        $Purchase = Purchase::where('id', $id)->first();
        if (!$Purchase) {
            throw new \Exception("No results found for Purchase with ID {$id}");
        }
        return $Purchase;
    }
    
    public function createPurchase(DTOsPurchase $data): Purchase
    {
        $result = Purchase::create($data->toArray());
        return $result;
    }
    
    public function updatePurchase(DTOsPurchase $data, Purchase $Purchase): Purchase
    {
        $Purchase->update($data->toArray());
        return $Purchase;
    }
    
    public function deletePurchase(Purchase $Purchase): Purchase
    {
        $Purchase->delete();
        return $Purchase;
    }
}
