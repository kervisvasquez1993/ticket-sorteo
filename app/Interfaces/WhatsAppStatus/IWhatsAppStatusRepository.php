<?php

namespace App\Interfaces\WhatsAppStatus;

use App\DTOs\WhatsAppStatus\DTOsWhatsAppStatus;
use App\Models\WhatsAppStatus;

interface IWhatsAppStatusRepository 
{
    public function getAllWhatsAppStatuss();
    public function getWhatsAppStatusById($id): WhatsAppStatus;
    public function createWhatsAppStatus(DTOsWhatsAppStatus $data): WhatsAppStatus;
    public function updateWhatsAppStatus(DTOsWhatsAppStatus $data, WhatsAppStatus $WhatsAppStatus): WhatsAppStatus;
    public function deleteWhatsAppStatus(WhatsAppStatus $WhatsAppStatus): WhatsAppStatus;
}
