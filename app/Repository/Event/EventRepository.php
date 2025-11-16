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
            'events.image_url',
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
            'image_url',
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
        return Event::with(['activePrices', 'defaultPrice'])
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->addSelect([
                'events.*',
                'total_tickets_sold' => function ($query) {
                    $query->selectRaw('COALESCE(SUM(quantity), 0)')
                        ->from('purchases')
                        ->whereColumn('purchases.event_id', 'events.id');
                }
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($event) {
                $totalTickets = $event->end_number - $event->start_number + 1;
                $ticketsSold = intval($event->total_tickets_sold ?? 0);
                $percentageSold = $totalTickets > 0 ? round(($ticketsSold / $totalTickets) * 100, 2) : 0;

                $event->tickets_summary = [
                    'total_available' => $totalTickets,
                    'total_sold' => $ticketsSold,
                    'available' => $totalTickets - $ticketsSold,
                    'percentage_sold' => $percentageSold
                ];

                unset($event->total_tickets_sold);

                return $event;
            });
    }

    public function getEventById($id): Event
    {
        return Event::findOrFail($id);
    }

    public function getEventWithParticipants($id): Event
    {
        return Event::with([
            'purchases' => function ($query) {
                $query->where('status', 'completed')
                    ->orderBy('created_at', 'desc');
            },
            'purchases.user:id,name,email,role',
            'purchases.eventPrice:id,event_id,amount,currency,is_default',
            'purchases.paymentMethod:id,name,type',
            'prices' => function ($query) {
                $query->where('is_active', true);
            },
            'defaultPrice',
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
    }

    public function createEvent(DTOsEvent $data): Event
    {
        return DB::transaction(function () use ($data) {
            return Event::create($data->toArray());
        });
    }

    public function updateEvent(DTOsEvent $data, Event $event): Event
    {
        // Filtrar solo los campos que no son null o vacíos
        $updateData = array_filter($data->toArray(), function ($value) {
            return $value !== null && $value !== '' && $value !== 0;
        });

        // Si no hay datos para actualizar, retornar el evento sin cambios
        if (empty($updateData)) {
            return $event;
        }

        $event->update($updateData);
        return $event->fresh();
    }



    public function updateEventImage(Event $event, string $imageUrl): Event
    {
        $event->update(['image_url' => $imageUrl]);
        return $event->fresh();
    }

    public function removeEventImage(Event $event): Event
    {
        $event->update(['image_url' => null]);
        return $event->fresh();
    }

    public function deleteEvent(Event $event): Event
    {
        $event->delete();
        return $event;
    }

    // ====================================================================
    // ✅ MÉTODOS FALTANTES AGREGADOS
    // ====================================================================

    /**
     * Obtener eventos por estado
     */
    public function getEventsByStatus(string $status)
    {
        return Event::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener eventos con paginación
     */
    public function getPaginatedEvents(int $perPage = 15)
    {
        return Event::orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Verificar si un evento tiene compras
     */
    public function hasEventPurchases(int $eventId): bool
    {
        return Event::where('id', $eventId)
            ->has('purchases')
            ->exists();
    }

    /**
     * Obtener estadísticas del evento
     */
    public function getEventStatistics(int $eventId): array
    {
        $event = $this->getEventById($eventId);
        return $event->getStatistics();
    }

    // ====================================================================
    // MÉTODOS DE NÚMEROS Y GANADORES
    // ====================================================================

    public function getAvailableNumbers(Event $event): array
    {
        $usedNumbers = $event->purchases()
            ->where('status', 'completed')
            ->pluck('ticket_number')
            ->toArray();

        $allNumbers = range($event->start_number, $event->end_number);
        $availableNumbers = array_diff($allNumbers, $usedNumbers);

        return array_values($availableNumbers);
    }

    public function selectWinner(Event $event, int $winnerNumber): object
    {
        $winningPurchase = $event->purchases()
            ->where('ticket_number', $winnerNumber)
            ->with('user')
            ->first();

        $event->update([
            'winner_number' => $winnerNumber,
            'status' => 'completed'
        ]);

        if ($winningPurchase) {
            return (object)[
                'winner_number' => $winnerNumber,
                'has_purchase' => true,
                'purchase_status' => $winningPurchase->status,
                'winner_name' => $winningPurchase->getCustomerName(),
                'winner_email' => $winningPurchase->getCustomerEmail(),
                'winner_whatsapp' => $winningPurchase->getCustomerWhatsapp(),
                'is_authenticated' => $winningPurchase->hasAuthenticatedUser(),
                'user_id' => $winningPurchase->user_id,
                'purchase_date' => $winningPurchase->created_at,
                'amount_paid' => $winningPurchase->amount,
                'currency' => $winningPurchase->currency,
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'status' => $event->status,
                ]
            ];
        }

        return (object)[
            'winner_number' => $winnerNumber,
            'has_purchase' => false,
            'purchase_status' => null,
            'winner_name' => 'Número sin comprador',
            'winner_email' => null,
            'winner_whatsapp' => null,
            'is_authenticated' => false,
            'user_id' => null,
            'purchase_date' => null,
            'amount_paid' => null,
            'currency' => null,
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'status' => $event->status,
            ]
        ];
    }

    public function getWinnerDetails(Event $event): ?array
    {
        if (!$event->winner_number) {
            return null;
        }

        $winningPurchase = $event->purchases()
            ->where('ticket_number', $event->winner_number)
            ->with('user')
            ->first();

        if ($winningPurchase) {
            return [
                'winner_number' => $event->winner_number,
                'has_purchase' => true,
                'purchase_status' => $winningPurchase->status,
                'winner_name' => $winningPurchase->getCustomerName(),
                'winner_email' => $winningPurchase->getCustomerEmail(),
                'winner_whatsapp' => $winningPurchase->getCustomerWhatsapp(),
                'is_authenticated' => $winningPurchase->hasAuthenticatedUser(),
                'user_id' => $winningPurchase->user_id,
                'purchase_date' => $winningPurchase->created_at->toISOString(),
                'amount_paid' => floatval($winningPurchase->amount),
                'currency' => $winningPurchase->currency,
                'transaction_id' => $winningPurchase->transaction_id,
                'qr_code_url' => $winningPurchase->qr_code_url,
            ];
        }

        return [
            'winner_number' => $event->winner_number,
            'has_purchase' => false,
            'purchase_status' => null,
            'winner_name' => 'Número sin comprador',
            'winner_email' => null,
            'winner_whatsapp' => null,
            'is_authenticated' => false,
            'user_id' => null,
            'purchase_date' => null,
            'amount_paid' => null,
            'currency' => null,
            'transaction_id' => null,
            'qr_code_url' => null,
        ];
    }
}
