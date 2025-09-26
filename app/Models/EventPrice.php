<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'amount',
        'currency',
        'is_default',
        'is_active'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'amount' => 'decimal:2'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
