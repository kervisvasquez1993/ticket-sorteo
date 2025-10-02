<?php

namespace App\Repository\EventPrice;

use App\DTOs\EventPrice\DTOsEventPrice;
use App\Interfaces\EventPrice\IEventPriceRepository;
use App\Models\EventPrice;

class EventPriceRepository implements IEventPriceRepository
{
    public function getAllEventPrices()
    {
        return EventPrice::with('event')->get();
    }

    public function getEventPriceById($id): EventPrice
    {
        $EventPrice = EventPrice::with('event')->find($id);

        if (!$EventPrice) {
            throw new \Exception("No se encontró el precio con ID {$id}");
        }

        return $EventPrice;
    }

    public function createEventPrice(DTOsEventPrice $data): EventPrice
    {
        return EventPrice::create($data->toArray());
    }

    public function updateEventPrice(DTOsEventPrice $data, EventPrice $EventPrice): EventPrice
    {
        $updateData = array_filter($data->toArray(), function($value, $key) {
            // Permitir booleanos false, pero filtrar valores vacíos
            if ($key === 'is_default' || $key === 'is_active') {
                return true;
            }
            return $value !== 0 && $value !== '';
        }, ARRAY_FILTER_USE_BOTH);

        $EventPrice->update($updateData);
        return $EventPrice->fresh();
    }

    public function deleteEventPrice(EventPrice $EventPrice): EventPrice
    {
        $EventPrice->delete();
        return $EventPrice;
    }

    /**
     * Contar cuántos precios tiene un evento
     */
    public function countEventPrices(int $eventId): int
    {
        return EventPrice::where('event_id', $eventId)->count();
    }

    /**
     * Remover el flag default de otros precios del mismo evento
     */
    public function removeDefaultFromEvent(int $eventId, ?int $exceptId = null): void
    {
        $query = EventPrice::where('event_id', $eventId)
                          ->where('is_default', true);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    /**
     * Asignar default al primer precio activo del evento
     * (usado cuando se elimina el precio default)
     */
    public function assignDefaultToFirstActive(int $eventId): void
    {
        $firstActivePrice = EventPrice::where('event_id', $eventId)
                                     ->where('is_active', true)
                                     ->orderBy('created_at', 'asc')
                                     ->first();

        if ($firstActivePrice) {
            $firstActivePrice->update(['is_default' => true]);
        }
    }
}
