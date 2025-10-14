<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewPurchaseNotification extends Notification
{
    use Queueable;

    private $purchaseData;
    private $purchaseType;

    public function __construct(array $purchaseData, string $purchaseType)
    {
        $this->purchaseData = $purchaseData;
        $this->purchaseType = $purchaseType;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $baseData = [
            'type' => $this->purchaseType,
            'transaction_id' => $this->purchaseData['transaction_id'],
            'quantity' => $this->purchaseData['quantity'],
            'total_amount' => $this->purchaseData['total_amount'],
            'client_email' => $this->purchaseData['client_email'],
            'client_whatsapp' => $this->purchaseData['client_whatsapp'],
            'event_id' => $this->purchaseData['event_id'],
            'event_name' => $this->purchaseData['event_name'],
            'status' => 'pending',
        ];

        if ($this->purchaseType === 'single') {
            $baseData['ticket_numbers'] = $this->purchaseData['ticket_numbers'];
            $baseData['message'] = "Nueva compra individual: {$this->purchaseData['quantity']} ticket(s) - Números: " .
                implode(', ', $this->purchaseData['ticket_numbers']);
        } else {
            $baseData['message'] = "Nueva compra por cantidad: {$this->purchaseData['quantity']} ticket(s) sin números asignados";
        }

        return $baseData;
    }
}
