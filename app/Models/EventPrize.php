<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventPrize extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'image_url',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    // ============================================
    // RELACIONES
    // ============================================

    /**
     * Premio pertenece a un evento
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope para premio principal
     */
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    // ============================================
    // MÉTODOS AUXILIARES
    // ============================================

    /**
     * Verificar si tiene imagen
     */
    public function hasImage(): bool
    {
        return !empty($this->image_url);
    }

    /**
     * Obtener URL completa de la imagen
     */
    public function getFullImageUrl(): ?string
    {
        if (!$this->hasImage()) {
            return null;
        }

        // Si ya es una URL completa, retornarla
        if (filter_var($this->image_url, FILTER_VALIDATE_URL)) {
            return $this->image_url;
        }

        // Si es una ruta relativa, construir URL completa
        return url($this->image_url);
    }

    /**
     * Verificar si es el premio principal
     */
    public function isMainPrize(): bool
    {
        return $this->is_main === true;
    }
}
