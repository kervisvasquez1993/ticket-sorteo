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
        'start_number',      // 0
        'end_number',        // 9999
        'price',
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
}
