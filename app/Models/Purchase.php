<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_id',
        'event_price_id',
        'payment_method_id',
        'ticket_number',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'payment_reference',        // NUEVO
        'payment_proof_url',        // NUEVO
        'quantity',                 // NUEVO
        'total_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function eventPrice()
    {
        return $this->belongsTo(EventPrice::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
