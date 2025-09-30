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
            $statistics = $event->getStatistics();

            // Información adicional de participantes
            $participantsDetails = $event->purchases
                ->groupBy('user_id')
                ->map(function ($userPurchases) {
                    $user = $userPurchases->first()->user;
                    return [
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'total_tickets' => $userPurchases->count(),
                        'total_spent' => $userPurchases->sum('amount'),
                        'ticket_numbers' => $userPurchases->pluck('ticket_number')->toArray(),
                        'purchase_dates' => $userPurchases->pluck('created_at')->toArray(),
                        'currencies_used' => $userPurchases->pluck('currency')->unique()->toArray(),
                    ];
                })->values();

            // Análisis por moneda
            $revenueByurrency = $event->purchases
                ->where('status', 'completed')
                ->groupBy('currency')
                ->map(function ($purchases, $currency) {
                    return [
                        'currency' => $currency,
                        'total_amount' => $purchases->sum('amount'),
                        'count' => $purchases->count(),
                    ];
                })->values();

            // Números más vendidos (si quieres ver patrones)
            $ticketNumbersFrequency = $event->purchases
                ->where('status', 'completed')
                ->whereNotNull('ticket_number')
                ->pluck('ticket_number')
                ->countBy()
                ->sortDesc()
                ->take(10);

            return [
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'description' => $event->description,
                        'start_number' => $event->start_number,
                        'end_number' => $event->end_number,
                        'start_date' => $event->start_date,
                        'end_date' => $event->end_date,
                        'status' => $event->status,
                        'winner_number' => $event->winner_number,
                        'image_url' => $event->image_url,
                        'is_active' => $event->isActive(),
                        'has_image' => $event->hasImage(),
                        'created_at' => $event->created_at,
                        'updated_at' => $event->updated_at,
                    ],
                    'statistics' => $statistics,
                    'participants' => $participantsDetails,
                    'revenue_by_currency' => $revenueByurrency,
                    'prices' => [
                        'all_prices' => $event->prices,
                        'default_price' => $event->defaultPrice,
                    ],
                    'purchases' => [
                        'completed' => $event->purchases->where('status', 'completed')->values(),
                        'pending' => $event->purchases->where('status', 'pending')->values(),
                        'total_count' => $event->total_purchases,
                        'pending_count' => $event->pending_purchases,
                    ],
                    'ticket_analysis' => [
                        'available_numbers' => $event->getAvailableNumbersCount(),
                        'top_purchased_numbers' => $ticketNumbersFrequency,
                    ],
                ]
            ];
        } catch (Exception $exception) {
            Log::error('Error getting event with participants: ' . $exception->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener el evento: ' . $exception->getMessage()
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
