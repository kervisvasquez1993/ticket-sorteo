<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventBlacklistedIdentification extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'identificacion',
        'reason',
        'added_by',
    ];

    // Relaciones
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    // Mutator: Normalizar identificación al guardar
    public function setIdentificacionAttribute($value): void
    {
        $this->attributes['identificacion'] = Purchase::normalizeIdentificacion($value);
    }
}
