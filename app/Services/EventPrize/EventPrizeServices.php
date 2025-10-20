<?php

namespace App\Services\EventPrize;

use App\DTOs\EventPrize\DTOsEventPrize;
use App\Interfaces\EventPrize\IEventPrizeServices;
use App\Interfaces\EventPrize\IEventPrizeRepository;
use Exception;

class EventPrizeServices implements IEventPrizeServices
{
    protected IEventPrizeRepository $eventPrizeRepository;

    public function __construct(IEventPrizeRepository $eventPrizeRepositoryInterface)
    {
        $this->eventPrizeRepository = $eventPrizeRepositoryInterface;
    }

    public function getAllEventPrizes()
    {
        try {
            $results = $this->eventPrizeRepository->getAllEventPrizes();
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

    public function getEventPrizeById($id)
    {
        try {
            $results = $this->eventPrizeRepository->getEventPrizeById($id);
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

    public function getEventPrizesByEventId($eventId)
    {
        try {
            $results = $this->eventPrizeRepository->getEventPrizesByEventId($eventId);
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

    public function getMainPrizeByEventId($eventId)
    {
        try {
            $results = $this->eventPrizeRepository->getMainPrizeByEventId($eventId);
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

    public function createEventPrize(DTOsEventPrize $data)
    {
        try {
            $results = $this->eventPrizeRepository->createEventPrize($data);
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Event prize created successfully'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function updateEventPrize(DTOsEventPrize $data, $id)
    {
        try {
            $eventPrize = $this->eventPrizeRepository->getEventPrizeById($id);
            $results = $this->eventPrizeRepository->updateEventPrize($data, $eventPrize);
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Event prize updated successfully'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function deleteEventPrize($id)
    {
        try {
            $eventPrize = $this->eventPrizeRepository->getEventPrizeById($id);
            $results = $this->eventPrizeRepository->deleteEventPrize($eventPrize);
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Event prize deleted successfully'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function setAsMainPrize($id)
    {
        try {
            $eventPrize = $this->eventPrizeRepository->getEventPrizeById($id);

            // Remover el flag de otros premios del mismo evento
            $this->eventPrizeRepository->removeAllMainPrizesFromEvent($eventPrize->event_id);

            // Actualizar este premio como principal
            $eventPrize->update(['is_main' => true]);

            return [
                'success' => true,
                'data' => $eventPrize->fresh(),
                'message' => 'Prize set as main successfully'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
    public function getAllMainPrizes()
    {
        try {
            $results = $this->eventPrizeRepository->getAllMainPrizes();
            return [
                'success' => true,
                'data' => $results,
                'total' => $results->count()
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}
