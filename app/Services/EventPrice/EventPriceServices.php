<?php

namespace App\Services\EventPrice;

use App\DTOs\EventPrice\DTOsEventPrice;
use App\DTOs\EventPrice\DTOsEventPriceFilter;
use App\Interfaces\EventPrice\IEventPriceServices;
use App\Interfaces\EventPrice\IEventPriceRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class EventPriceServices implements IEventPriceServices
{
    protected IEventPriceRepository $EventPriceRepository;

    public function __construct(IEventPriceRepository $EventPriceRepositoryInterface)
    {
        $this->EventPriceRepository = $EventPriceRepositoryInterface;
    }

    public function getAllEventPrices(?DTOsEventPriceFilter $filters = null)
    {
        try {
            $results = $this->EventPriceRepository->getAllEventPrices($filters);
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
    public function getEventPriceById($id)
    {
        try {
            $results = $this->EventPriceRepository->getEventPriceById($id);
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function createEventPrice(DTOsEventPrice $data)
    {
        try {
            DB::beginTransaction();
            $isFirstPrice = $this->EventPriceRepository->countEventPrices($data->getEventId()) === 0;

            // Si es el primer precio, forzar que sea default
            if ($isFirstPrice) {
                $data = new DTOsEventPrice(
                    event_id: $data->getEventId(),
                    amount: $data->getAmount(),
                    currency: $data->getCurrency(),
                    is_default: true,
                    is_active: $data->isActive()
                );
            } else {
                // Si NO es el primer precio y viene marcado como default
                if ($data->isDefault()) {
                    // Quitar el default de otros precios del mismo evento
                    $this->EventPriceRepository->removeDefaultFromEvent($data->getEventId());
                }
                // Si NO viene marcado como default, simplemente se crea sin ser default
            }

            $results = $this->EventPriceRepository->createEventPrice($data);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Precio de rifa creado exitosamente',
                'data' => $results
            ];
        } catch (Exception $exception) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Error al crear el precio: ' . $exception->getMessage()
            ];
        }
    }

    public function updateEventPrice(DTOsEventPrice $data, $id)
    {
        try {
            DB::beginTransaction();

            $EventPrice = $this->EventPriceRepository->getEventPriceById($id);
            $eventId = $data->getEventId() > 0 ? $data->getEventId() : $EventPrice->event_id;

            // Contar cuántos precios tiene el evento
            $totalPrices = $this->EventPriceRepository->countEventPrices($eventId);

            // Si es el único precio del evento, forzar que sea default
            if ($totalPrices === 1) {
                $data = new DTOsEventPrice(
                    event_id: $data->getEventId(),
                    amount: $data->getAmount() > 0 ? $data->getAmount() : $EventPrice->amount,
                    currency: !empty($data->getCurrency()) ? $data->getCurrency() : $EventPrice->currency,
                    is_default: true, // Forzar a true
                    is_active: $data->isActive()
                );
            } else {
                // Si hay múltiples precios y se está marcando como default
                if ($data->isDefault()) {
                    // Quitar el default de otros precios del mismo evento
                    $this->EventPriceRepository->removeDefaultFromEvent($eventId, $id);
                }
                // Si NO viene marcado como default, no hacer nada adicional
            }

            $results = $this->EventPriceRepository->updateEventPrice($data, $EventPrice);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Precio de rifa actualizado exitosamente',
                'data' => $results
            ];
        } catch (Exception $exception) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Error al actualizar el precio: ' . $exception->getMessage()
            ];
        }
    }

    public function deleteEventPrice($id)
    {
        try {
            DB::beginTransaction();

            $EventPrice = $this->EventPriceRepository->getEventPriceById($id);
            $eventId = $EventPrice->event_id;
            $wasDefault = $EventPrice->is_default;

            // Eliminar el precio
            $results = $this->EventPriceRepository->deleteEventPrice($EventPrice);

            // Si el precio eliminado era default, asignar default al primer precio activo restante
            if ($wasDefault) {
                $this->EventPriceRepository->assignDefaultToFirstActive($eventId);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Precio de rifa eliminado exitosamente',
                'data' => $results
            ];
        } catch (Exception $exception) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Error al eliminar el precio: ' . $exception->getMessage()
            ];
        }
    }
}
