<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'start_number',
        'end_number',
        'start_date',
        'end_date',
        'status',
        'winner_number'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'purchases')
            ->withPivot('ticket_number', 'amount', 'status')
            ->withTimestamps();
    }

    public function prices()
    {
        return $this->hasMany(EventPrice::class);
    }

    public function activePrices()
    {
        return $this->hasMany(EventPrice::class)->where('is_active', true);
    }

    public function defaultPrice()
    {
        return $this->hasOne(EventPrice::class)->where('is_default', true);
    }
    public function getPriceByCurrency($currency)
    {
        return $this->prices()
            ->where('currency', $currency)
            ->where('is_active', true)
            ->first();
    }
}
