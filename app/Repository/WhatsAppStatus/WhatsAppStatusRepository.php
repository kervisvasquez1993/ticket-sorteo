<?php

namespace App\Repository\WhatsAppStatus;

use App\DTOs\WhatsAppStatus\DTOsWhatsAppStatus;
use App\Interfaces\WhatsAppStatus\IWhatsAppStatusRepository;
use App\Models\WhatsAppStatus;

class WhatsAppStatusRepository implements IWhatsAppStatusRepository 
{
    public function getAllWhatsAppStatuss()
    {
        $WhatsAppStatuss = WhatsAppStatus::all();
        return $WhatsAppStatuss;
    }
    
    public function getWhatsAppStatusById($id): WhatsAppStatus
    {
        $WhatsAppStatus = WhatsAppStatus::where('id', $id)->first();
        if (!$WhatsAppStatus) {
            throw new \Exception("No results found for WhatsAppStatus with ID {$id}");
        }
        return $WhatsAppStatus;
    }
    
    public function createWhatsAppStatus(DTOsWhatsAppStatus $data): WhatsAppStatus
    {
        $result = WhatsAppStatus::create($data->toArray());
        return $result;
    }
    
    public function updateWhatsAppStatus(DTOsWhatsAppStatus $data, WhatsAppStatus $WhatsAppStatus): WhatsAppStatus
    {
        $WhatsAppStatus->update($data->toArray());
        return $WhatsAppStatus;
    }
    
    public function deleteWhatsAppStatus(WhatsAppStatus $WhatsAppStatus): WhatsAppStatus
    {
        $WhatsAppStatus->delete();
        return $WhatsAppStatus;
    }
}
