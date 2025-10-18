<?php

namespace App\Services\Event;

use App\DTOs\Event\DTOsEvent;
use App\Interfaces\Event\IEventServices;
use App\Interfaces\Event\IEventRepository;
use Exception;
use Illuminate\Support\Facades\Log;

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
            $event = $this->eventRepository->getEventById($id);

            // Obtener estadísticas básicas
            $statistics = $event->getStatistics();

            // Contar participantes únicos (usuarios registrados)
            $totalParticipants = $event->purchases()
                ->where('status', 'completed')
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');

            // Contar compras de invitados (sin user_id)
            $guestPurchases = $event->purchases()
                ->where('status', 'completed')
                ->whereNull('user_id')
                ->count();

            // Análisis por moneda
            $revenueByurrency = $event->purchases()
                ->where('status', 'completed')
                ->select('currency')
                ->selectRaw('SUM(amount) as total_amount')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('currency')
                ->get()
                ->map(function ($item) {
                    return [
                        'currency' => $item->currency,
                        'total_amount' => floatval($item->total_amount),
                        'count' => $item->count,
                    ];
                });

            // Resumen de ventas por estado
            $purchasesSummary = $event->purchases()
                ->select('status')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(total_amount) as total_amount')
                ->selectRaw('SUM(quantity) as total_tickets')
                ->groupBy('status')
                ->get()
                ->keyBy('status');

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
                    'participants_summary' => [
                        'total_registered_users' => $totalParticipants,
                        'total_guest_purchases' => $guestPurchases,
                        'total_participants' => $totalParticipants + $guestPurchases,
                    ],
                    'revenue_by_currency' => $revenueByurrency,
                    'purchases_summary' => [
                        'completed' => [
                            'count' => $purchasesSummary->get('completed')->count ?? 0,
                            'total_amount' => floatval($purchasesSummary->get('completed')->total_amount ?? 0),
                            'total_tickets' => intval($purchasesSummary->get('completed')->total_tickets ?? 0),
                        ],
                        'pending' => [
                            'count' => $purchasesSummary->get('pending')->count ?? 0,
                            'total_amount' => floatval($purchasesSummary->get('pending')->total_amount ?? 0),
                            'total_tickets' => intval($purchasesSummary->get('pending')->total_tickets ?? 0),
                        ],
                        'processing' => [
                            'count' => $purchasesSummary->get('processing')->count ?? 0,
                            'total_amount' => floatval($purchasesSummary->get('processing')->total_amount ?? 0),
                            'total_tickets' => intval($purchasesSummary->get('processing')->total_tickets ?? 0),
                        ],
                        'failed' => [
                            'count' => $purchasesSummary->get('failed')->count ?? 0,
                            'total_amount' => floatval($purchasesSummary->get('failed')->total_amount ?? 0),
                            'total_tickets' => intval($purchasesSummary->get('failed')->total_tickets ?? 0),
                        ],
                    ],
                    'prices' => [
                        'all_prices' => $event->prices,
                        'default_price' => $event->defaultPrice,
                    ],
                    'ticket_analysis' => [
                        'total_available' => ($event->end_number - $event->start_number) + 1,
                        'available_numbers' => $event->getAvailableNumbersCount(),
                        'sold_numbers' => (($event->end_number - $event->start_number) + 1) - $event->getAvailableNumbersCount(),
                    ],
                ]
            ];
        } catch (Exception $exception) {
            Log::error('Error getting event summary: ' . $exception->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener el evento: ' . $exception->getMessage()
            ];
        }
    }

    public function createEvent(DTOsEvent $data)
    {
        try {
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

    // public function selectWinner($eventId)
    // {
    //     try {
    //         $event = $this->eventRepository->getEventById($eventId);

    //         if ($event->status === 'completed') {
    //             throw new Exception('Este evento ya tiene un ganador');
    //         }

    //         $winner = $this->eventRepository->selectWinner($event);

    //         if (!$winner) {
    //             throw new Exception('No hay participantes para este evento');
    //         }

    //         return [
    //             'success' => true,
    //             'data' => $winner,
    //             'message' => 'Ganador seleccionado exitosamente'
    //         ];
    //     } catch (Exception $exception) {
    //         return [
    //             'success' => false,
    //             'message' => $exception->getMessage()
    //         ];
    //     }
    // }
    public function selectWinner($eventId, int $winnerNumber)
    {
        try {
            $event = $this->eventRepository->getEventById($eventId);

            // Validar estado del evento
            if ($event->status === 'completed') {
                throw new Exception('Este evento ya tiene un ganador');
            }

            if (!$event->isActive() && $event->status !== 'active') {
                throw new Exception('El evento debe estar activo para seleccionar un ganador');
            }

            // Validar que el número esté en el rango permitido
            if ($winnerNumber < $event->start_number || $winnerNumber > $event->end_number) {
                throw new Exception(
                    "El número ganador debe estar entre {$event->start_number} y {$event->end_number}"
                );
            }

            // Verificar que el número tenga una compra completada
            if (!$event->hasCompletedPurchaseForNumber($winnerNumber)) {
                throw new Exception(
                    'No existe una compra completada con ese número de ticket'
                );
            }

            // Seleccionar ganador
            $winner = $this->eventRepository->selectWinner($event, $winnerNumber);

            if (!$winner) {
                throw new Exception('Error al seleccionar el ganador');
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
