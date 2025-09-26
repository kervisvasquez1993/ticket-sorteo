<?php

namespace App\Services\Event;

use App\DTOs\Event\DTOsEvent;
use App\Interfaces\Event\IEventServices;
use App\Interfaces\Event\IEventRepository;
use Exception;

class EventServices implements IEventServices
{
    protected IEventRepository $eventRepository;

    public function __construct(IEventRepository $eventRepositoryInterface)
    {
        $this->eventRepository = $eventRepositoryInterface;
    }

    public function getAllEvents()
    {
        try {
            $results = $this->eventRepository->getAllEvents();
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

    public function getActiveEvents()
    {
        try {
            $results = $this->eventRepository->getActiveEvents();
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

    public function getEventById($id)
    {
        try {
            $results = $this->eventRepository->getEventById($id);
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

    public function getEventWithParticipants($id)
    {
        try {
            $event = $this->eventRepository->getEventWithParticipants($id);

            // Calcular estadísticas
            $totalSold = $event->purchases->where('status', 'completed')->count();
            $totalRevenue = $event->purchases->where('status', 'completed')->sum('amount');
            $totalNumbers = ($event->end_number - $event->start_number) + 1;
            $availableCount = $totalNumbers - $totalSold;

            return [
                'success' => true,
                'data' => [
                    'event' => $event,
                    'statistics' => [
                        'total_numbers' => $totalNumbers,
                        'sold_numbers' => $totalSold,
                        'available_numbers' => $availableCount,
                        'total_revenue' => $totalRevenue,
                        'participants_count' => $event->purchases->unique('user_id')->count()
                    ]
                ]
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function createEvent(DTOsEvent $data)
    {
        try {
            // Validar que el rango sea razonable
            $totalNumbers = ($data->getEndNumber() - $data->getStartNumber()) + 1;
            if ($totalNumbers > 100000) {
                throw new \Exception('El rango de números es demasiado grande (máximo 100,000)');
            }

            $results = $this->eventRepository->createEvent($data);

            return [
                'success' => true,
                'data' => $results,
                'message' => 'Evento y precios creados exitosamente'
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function updateEvent(DTOsEvent $data, $id)
    {
        try {
            $event = $this->eventRepository->getEventById($id);

            // Validar que no se modifiquen rangos si ya hay compras
            $hasPurchases = $event->purchases()->exists();
            if (
                $hasPurchases &&
                ($data->getStartNumber() != $event->start_number ||
                    $data->getEndNumber() != $event->end_number)
            ) {
                throw new Exception('No se pueden modificar los rangos de números si ya hay compras registradas');
            }

            $results = $this->eventRepository->updateEvent($data, $event);
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Evento actualizado exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function deleteEvent($id)
    {
        try {
            $event = $this->eventRepository->getEventById($id);

            // Validar que no tenga compras completadas
            $hasCompletedPurchases = $event->purchases()->where('status', 'completed')->exists();
            if ($hasCompletedPurchases) {
                throw new Exception('No se puede eliminar un evento con compras completadas');
            }

            $results = $this->eventRepository->deleteEvent($event);
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Evento eliminado exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function getAvailableNumbers($eventId)
    {
        try {
            $event = $this->eventRepository->getEventById($eventId);

            if ($event->status !== 'active') {
                throw new Exception('Este evento no está activo');
            }

            $availableNumbers = $this->eventRepository->getAvailableNumbers($event);

            return [
                'success' => true,
                'data' => [
                    'available_count' => count($availableNumbers),
                    'numbers' => $availableNumbers
                ]
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function selectWinner($eventId)
    {
        try {
            $event = $this->eventRepository->getEventById($eventId);

            if ($event->status === 'completed') {
                throw new Exception('Este evento ya tiene un ganador');
            }

            $winner = $this->eventRepository->selectWinner($event);

            if (!$winner) {
                throw new Exception('No hay participantes para este evento');
            }

            return [
                'success' => true,
                'data' => $winner,
                'message' => 'Ganador seleccionado exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}
