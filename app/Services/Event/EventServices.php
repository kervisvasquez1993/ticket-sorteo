<?php

namespace App\Services\Event;

use App\DTOs\Event\DTOsEvent;
use App\Interfaces\Event\IEventServices;
use App\Interfaces\Event\IEventRepository;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
            $statistics = $event->getStatistics();

            $totalParticipants = $event->purchases()
                ->where('status', 'completed')
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');

            $guestPurchases = $event->purchases()
                ->where('status', 'completed')
                ->whereNull('user_id')
                ->count();

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

            $purchasesSummary = $event->purchases()
                ->select('status')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(total_amount) as total_amount')
                ->selectRaw('SUM(quantity) as total_tickets')
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            $winnerInfo = null;
            if ($event->winner_number) {
                $winnerInfo = $this->eventRepository->getWinnerDetails($event);
            }

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
                    'winner_details' => $winnerInfo,
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

            // ✅ Validar rangos solo si vienen en el request
            $hasPurchases = $event->purchases()->exists();
            $newStartNumber = $data->getStartNumber();
            $newEndNumber = $data->getEndNumber();

            if ($hasPurchases) {
                // Solo validar si se están intentando cambiar
                if ($newStartNumber !== null && $newStartNumber != $event->start_number) {
                    throw new Exception('No se pueden modificar el número inicial si ya hay compras registradas');
                }

                if ($newEndNumber !== null && $newEndNumber != $event->end_number) {
                    throw new Exception('No se pueden modificar el número final si ya hay compras registradas');
                }
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

            if ($event->purchases()->exists()) {
                throw new Exception('No se puede eliminar un evento que tiene compras registradas');
            }

            if ($event->image_url) {
                $this->deleteEventImageFromS3($event->image_url);
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

    // ====================================================================
    // ✅ MÉTODOS DE GESTIÓN DE IMÁGENES
    // ====================================================================

    public function updateEventImage($request, string $id): array
    {
        try {
            $event = $this->eventRepository->getEventById($id);

            if ($event->image_url) {
                $this->deleteEventImageFromS3($event->image_url);
            }

            $imageUrl = $this->uploadEventImageToS3($request);

            if (!$imageUrl) {
                throw new Exception('Error al subir la imagen a S3');
            }

            $updatedEvent = $this->eventRepository->updateEventImage($event, $imageUrl);

            return [
                'success' => true,
                'data' => [
                    'id' => $updatedEvent->id,
                    'image_url' => $updatedEvent->image_url,
                ],
                'message' => 'Imagen del evento actualizada exitosamente'
            ];
        } catch (Exception $exception) {
            Log::error('Error updating event image', [
                'event_id' => $id,
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function deleteEventImage(string $id): array
    {
        try {
            $event = $this->eventRepository->getEventById($id);

            if (!$event->image_url) {
                throw new Exception('El evento no tiene imagen para eliminar');
            }

            $this->deleteEventImageFromS3($event->image_url);
            $updatedEvent = $this->eventRepository->removeEventImage($event);

            return [
                'success' => true,
                'data' => [
                    'id' => $updatedEvent->id,
                    'image_url' => null,
                ],
                'message' => 'Imagen del evento eliminada exitosamente'
            ];
        } catch (Exception $exception) {
            Log::error('Error deleting event image', [
                'event_id' => $id,
                'error' => $exception->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    // ====================================================================
    // ✅ MÉTODOS FALTANTES AGREGADOS
    // ====================================================================

    public function getEventsByStatus(string $status)
    {
        try {
            return [
                'success' => true,
                'data' => $this->eventRepository->getEventsByStatus($status)
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function getEventStatistics(int $eventId)
    {
        try {
            return [
                'success' => true,
                'data' => $this->eventRepository->getEventStatistics($eventId)
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    // ====================================================================
    // MÉTODOS DE NÚMEROS Y GANADORES
    // ====================================================================

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

    public function selectWinner($eventId, int $winnerNumber)
    {
        try {
            $event = $this->eventRepository->getEventById($eventId);

            if ($event->status === 'completed') {
                throw new Exception('Este evento ya tiene un ganador');
            }

            if (!$event->isActive() && $event->status !== 'active') {
                throw new Exception('El evento debe estar activo para seleccionar un ganador');
            }

            if ($winnerNumber < $event->start_number || $winnerNumber > $event->end_number) {
                throw new Exception(
                    "El número ganador debe estar entre {$event->start_number} y {$event->end_number}"
                );
            }

            $winner = $this->eventRepository->selectWinner($event, $winnerNumber);

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

    // ====================================================================
    // MÉTODOS PRIVADOS DE GESTIÓN DE S3
    // ====================================================================

    private function uploadEventImageToS3($request): ?string
    {
        if (!$request->hasFile('image')) {
            return null;
        }

        try {
            $file = $request->file('image');
            $fileName = 'event-images/' . uniqid() . '.' . $file->getClientOriginalExtension();

            $uploaded = Storage::disk('s3')->put($fileName, file_get_contents($file), [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType()
            ]);

            if ($uploaded) {
                $url = "https://backend-imagen-br.s3.us-east-2.amazonaws.com/" . $fileName;

                Log::info('Event image uploaded successfully', [
                    'file_name' => $fileName,
                    'url' => $url
                ]);

                return $url;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error uploading event image', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function deleteEventImageFromS3(?string $imageUrl): void
    {
        if (!$imageUrl) {
            return;
        }

        try {
            $path = str_replace(
                'https://backend-imagen-br.s3.us-east-2.amazonaws.com/',
                '',
                $imageUrl
            );

            Storage::disk('s3')->delete($path);
            Log::info('Event image deleted from S3', ['path' => $path]);
        } catch (\Exception $e) {
            Log::error('Error deleting event image from S3', [
                'url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
        }
    }
}
