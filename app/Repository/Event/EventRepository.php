<?php

namespace App\Repository\Event;

use App\DTOs\Event\DTOsEvent;
use App\Interfaces\Event\IEventRepository;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class EventRepository implements IEventRepository
{
    public function getAllEvents()
    {
        return Event::select([
            'events.id',
            'events.name',
            'events.description',
            'events.start_number',
            'events.end_number',
            'events.start_date',
            'events.end_date',
            'events.status',
            'events.winner_number',
            'events.image_url', // Nuevo campo agregado
            'events.created_at',
            'events.updated_at'
        ])
            ->withCount([
                'purchases as total_purchases',
                'purchases as pending_purchases' => function ($query) {
                    $query->where('status', 'pending');
                },
                'purchases as completed_purchases' => function ($query) {
                    $query->where('status', 'completed');
                },
                'purchases as processing_purchases' => function ($query) {
                    $query->where('status', 'processing');
                },
                'purchases as failed_purchases' => function ($query) {
                    $query->where('status', 'failed');
                },
                'purchases as refunded_purchases' => function ($query) {
                    $query->where('status', 'refunded');
                }
            ])
            ->addSelect([
                // Suma total de ingresos por estado
                'total_revenue' => function ($query) {
                    $query->selectRaw('COALESCE(SUM(total_amount), 0)')
                        ->from('purchases')
                        ->whereColumn('purchases.event_id', 'events.id');
                },

                'pending_revenue' => function ($query) {
                    $query->selectRaw('COALESCE(SUM(total_amount), 0)')
                        ->from('purchases')
                        ->whereColumn('purchases.event_id', 'events.id')
                        ->where('purchases.status', 'pending');
                },

                'completed_revenue' => function ($query) {
                    $query->selectRaw('COALESCE(SUM(total_amount), 0)')
                        ->from('purchases')
                        ->whereColumn('purchases.event_id', 'events.id')
                        ->where('purchases.status', 'completed');
                },

                // Cantidad total de tickets vendidos
                'total_tickets_sold' => function ($query) {
                    $query->selectRaw('COALESCE(SUM(quantity), 0)')
                        ->from('purchases')
                        ->whereColumn('purchases.event_id', 'events.id');
                }
            ])
            ->orderBy('events.created_at', 'desc')
            ->get()
            ->map(function ($event) {
                $totalTickets = $event->end_number - $event->start_number + 1;
                $ticketsSold = intval($event->total_tickets_sold ?? 0);
                $percentageSold = $totalTickets > 0 ? round(($ticketsSold / $totalTickets) * 100, 2) : 0;
                $event->statistics = [
                    'total_purchases' => $event->total_purchases,
                    'purchases_by_status' => [
                        'pending' => $event->pending_purchases,
                        'completed' => $event->completed_purchases,
                        'processing' => $event->processing_purchases,
                        'failed' => $event->failed_purchases,
                        'refunded' => $event->refunded_purchases,
                    ],
                    'revenue_summary' => [
                        'total' => floatval($event->total_revenue ?? 0),
                        'pending' => floatval($event->pending_revenue ?? 0),
                        'completed' => floatval($event->completed_revenue ?? 0),
                    ],
                    'tickets_summary' => [
                        'total_available' => $totalTickets,
                        'total_sold' => $ticketsSold,
                        'available' => $totalTickets - $ticketsSold,
                        'percentage_sold' => $percentageSold
                    ]
                ];
                unset(
                    $event->total_purchases,
                    $event->pending_purchases,
                    $event->completed_purchases,
                    $event->processing_purchases,
                    $event->failed_purchases,
                    $event->refunded_purchases,
                    $event->total_revenue,
                    $event->pending_revenue,
                    $event->completed_revenue,
                    $event->total_tickets_sold
                );

                return $event;
            });
    }

    public function getAllEventsSimple()
    {
        return Event::select([
            'id',
            'name',
            'description',
            'start_number',
            'end_number',
            'start_date',
            'end_date',
            'status',
            'winner_number',
            'image_url', // Nuevo campo agregado
            'created_at',
            'updated_at'
        ])
            ->withCount('purchases as total_purchases')
            ->with([
                'purchases' => function ($query) {
                    $query->select([
                        'id',
                        'event_id',
                        'user_id',
                        'status',
                        'total_amount',
                        'quantity',
                        'created_at'
                    ])
                        ->with('user:id,name,email')
                        ->latest()
                        ->limit(5);
                }
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($event) {
                // Calcular estadísticas a partir de las compras cargadas
                $purchases = $event->purchases;

                $event->statistics = [
                    'total_purchases' => $event->total_purchases,
                    'purchases_by_status' => [
                        'pending' => $purchases->where('status', 'pending')->count(),
                        'completed' => $purchases->where('status', 'completed')->count(),
                        'processing' => $purchases->where('status', 'processing')->count(),
                        'failed' => $purchases->where('status', 'failed')->count(),
                        'refunded' => $purchases->where('status', 'refunded')->count(),
                    ],
                    'recent_revenue' => $purchases->sum('total_amount'),
                ];

                $event->recent_purchases = $purchases->take(5);
                unset($event->purchases, $event->total_purchases);

                return $event;
            });
    }

    public function getActiveEvents()
    {
        return Event::where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getEventById($id): Event
    {
        $event = Event::findOrFail($id);
        return $event;
    }

    public function getEventWithParticipants($id): Event
    {
        $event = Event::with([
            // Purchases con toda la información relacionada
            'purchases' => function ($query) {
                $query->where('status', 'completed')
                    ->orderBy('created_at', 'desc');
            },
            'purchases.user:id,name,email,role', // Solo campos necesarios del usuario
            'purchases.eventPrice:id,event_id,amount,currency,is_default',
            'purchases.paymentMethod:id,name,type', // Asumiendo que tienes estos campos

            // Precios del evento
            'prices' => function ($query) {
                $query->where('is_active', true);
            },
            'defaultPrice',

            // Participantes únicos (si necesitas listarlos aparte)
            'participants' => function ($query) {
                $query->select('users.id', 'users.name', 'users.email')
                    ->distinct();
            }
        ])
            ->withCount([
                'purchases as total_purchases' => function ($query) {
                    $query->where('status', 'completed');
                },
                'purchases as pending_purchases' => function ($query) {
                    $query->where('status', 'pending');
                }
            ])
            ->findOrFail($id);

        return $event;
    }




    public function createEvent(DTOsEvent $data): Event
    {
        return DB::transaction(function () use ($data) {
            $event = Event::create($data->toArray());

            // Crear los precios asociados
            foreach ($data->getPrices() as $priceData) {
                $event->prices()->create([
                    'amount' => $priceData['amount'],
                    'currency' => $priceData['currency'],
                    'is_default' => $priceData['is_default'],
                    'is_active' => true
                ]);
            }
            $event->load('prices');

            return $event;
        });
    }

    public function updateEvent(DTOsEvent $data, Event $event): Event
    {
        $event->update($data->toArray());
        return $event;
    }

    public function deleteEvent(Event $event): Event
    {
        $event->delete();
        return $event;
    }

    public function getAvailableNumbers(Event $event): array
    {
        // Obtener todos los números usados en UNA consulta
        $usedNumbers = $event->purchases()
            ->where('status', 'completed')
            ->pluck('ticket_number')
            ->toArray();

        // Crear rango completo
        $allNumbers = range($event->start_number, $event->end_number);

        // Excluir los usados
        $availableNumbers = array_diff($allNumbers, $usedNumbers);

        return array_values($availableNumbers);
    }

    public function selectWinner(Event $event): ?object
    {
        // Seleccionar un ticket ganador aleatorio
        $winningPurchase = $event->purchases()
            ->where('status', 'completed')
            ->inRandomOrder()
            ->first();

        if ($winningPurchase) {
            // Actualizar el evento con el número ganador
            $event->update([
                'winner_number' => $winningPurchase->ticket_number,
                'status' => 'completed'
            ]);

            return (object)[
                'winner_number' => $winningPurchase->ticket_number,
                'winner_user' => $winningPurchase->user,
                'purchase' => $winningPurchase
            ];
        }

        return null;
    }
}
