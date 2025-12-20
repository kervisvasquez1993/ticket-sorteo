<?php

namespace App\Services\EventBlacklist;

use App\DTOs\EventBlacklist\DTOsEventBlacklist;
use App\Interfaces\EventBlacklist\IEventBlacklistServices;
use App\Interfaces\EventBlacklist\IEventBlacklistRepository;
use App\Models\Event;
use Exception;
use Illuminate\Support\Facades\Log;

class EventBlacklistServices implements IEventBlacklistServices
{
    protected IEventBlacklistRepository $blacklistRepository;

    public function __construct(IEventBlacklistRepository $blacklistRepositoryInterface)
    {
        $this->blacklistRepository = $blacklistRepositoryInterface;
    }

    public function addToBlacklist(DTOsEventBlacklist $data)
    {
        try {
            $event = Event::findOrFail($data->getEventId());
            $blacklisted = $this->blacklistRepository->create($data);

            Log::info('Identificación agregada a lista negra', [
                'event_id' => $event->id,
                'event_name' => $event->name,
                'identificacion' => $data->getIdentificacion(),
                'added_by' => $data->getAddedBy(),
                'reason' => $data->getReason(),
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $blacklisted->id,
                    'event_id' => $blacklisted->event_id,
                    'identificacion' => $blacklisted->identificacion,
                    'reason' => $blacklisted->reason,
                    'added_by' => $blacklisted->addedBy->name ?? 'N/A',
                    'created_at' => $blacklisted->created_at->toDateTimeString(),
                ],
                'message' => 'Identificación agregada a lista negra exitosamente'
            ];
        } catch (Exception $exception) {
            Log::error('Error agregando a lista negra', [
                'error' => $exception->getMessage(),
                'data' => $data->toArray(),
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function removeFromBlacklist(int $eventId, string $identificacion)
    {
        try {
            // Verificar que existe antes de eliminar
            if (!$this->blacklistRepository->exists($eventId, $identificacion)) {
                throw new Exception('Esta identificación no está en la lista negra de este evento');
            }

            $deleted = $this->blacklistRepository->delete($eventId, $identificacion);

            if (!$deleted) {
                throw new Exception('No se pudo eliminar la identificación de la lista negra');
            }

            Log::info('Identificación removida de lista negra', [
                'event_id' => $eventId,
                'identificacion' => $identificacion,
            ]);

            return [
                'success' => true,
                'message' => 'Identificación removida de lista negra exitosamente'
            ];
        } catch (Exception $exception) {
            Log::error('Error removiendo de lista negra', [
                'error' => $exception->getMessage(),
                'event_id' => $eventId,
                'identificacion' => $identificacion,
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function getBlacklistByEvent(int $eventId)
    {
        try {
            // Verificar que el evento existe
            $event = Event::findOrFail($eventId);

            $blacklist = $this->blacklistRepository->getByEvent($eventId);

            return [
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                    ],
                    'blacklist' => $blacklist->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'identificacion' => $item->identificacion,
                            'reason' => $item->reason,
                            'added_by' => [
                                'id' => $item->addedBy->id ?? null,
                                'name' => $item->addedBy->name ?? 'N/A',
                                'email' => $item->addedBy->email ?? 'N/A',
                            ],
                            'created_at' => $item->created_at->toDateTimeString(),
                        ];
                    }),
                    'total' => $blacklist->count(),
                ],
                'message' => 'Lista negra obtenida exitosamente'
            ];
        } catch (Exception $exception) {
            Log::error('Error obteniendo lista negra', [
                'error' => $exception->getMessage(),
                'event_id' => $eventId,
            ]);

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}
