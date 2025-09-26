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
        return Event::with('purchases.user')
                    ->orderBy('created_at', 'desc')
                    ->get();
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
            'purchases' => function($query) {
                $query->where('status', 'completed')
                      ->orderBy('created_at', 'desc');
            },
            'purchases.user'
        ])->findOrFail($id);

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
