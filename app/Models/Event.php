<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'start_number',
        'end_number',
        'start_date',
        'end_date',
        'status',
        'winner_number',
        'image_url'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'purchases')
            ->withPivot('ticket_number', 'amount', 'status')
            ->withTimestamps();
    }

    public function prices()
    {
        return $this->hasMany(EventPrice::class);
    }

    public function activePrices()
    {
        return $this->hasMany(EventPrice::class)->where('is_active', true);
    }

    public function defaultPrice()
    {
        return $this->hasOne(EventPrice::class)->where('is_default', true);
    }

    public function getPriceByCurrency($currency)
    {
        return $this->prices()
            ->where('currency', $currency)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Obtener todos los números disponibles
     */
    public function getAvailableNumbers()
    {
        // Obtener TODOS los números usados en UNA sola consulta
        $usedNumbers = $this->purchases()
            ->whereNotNull('ticket_number')
            ->pluck('ticket_number')
            ->toArray();

        // Crear rango completo
        $allNumbers = range($this->start_number, $this->end_number);

        // Excluir los usados (array_diff es muy eficiente)
        $availableNumbers = array_diff($allNumbers, $usedNumbers);

        return array_values($availableNumbers); // reindexar
    }

    /**
     * Obtener conteo de números disponibles
     */
    public function getAvailableNumbersCount(): int
    {
        $totalNumbers = ($this->end_number - $this->start_number) + 1;
        $usedNumbers = $this->purchases()->whereNotNull('ticket_number')->count();

        return $totalNumbers - $usedNumbers;
    }

    /**
     * Método para asignar número aleatorio
     */
    public function assignRandomNumber()
    {
        $availableNumbers = $this->getAvailableNumbers();

        if (empty($availableNumbers)) {
            throw new \Exception('No hay números disponibles');
        }

        // Usar array_rand para selección aleatoria
        $randomNumber = $availableNumbers[array_rand($availableNumbers)];

        return $randomNumber;
    }

    /**
     * Verificar si un número está disponible
     */
    public function isNumberAvailable(int $number): bool
    {
        if ($number < $this->start_number || $number > $this->end_number) {
            return false;
        }

        return !$this->purchases()
            ->where('ticket_number', $number)
            ->exists();
    }

    /**
     * Obtener estadísticas del evento
     */
    // public function getStatistics(): array
    // {
    //     $totalNumbers = ($this->end_number - $this->start_number) + 1;
    //     $soldNumbers = $this->purchases()->whereNotNull('ticket_number')->count();
    //     $availableNumbers = $totalNumbers - $soldNumbers;
    //     $percentageSold = $totalNumbers > 0 ? ($soldNumbers / $totalNumbers) * 100 : 0;

    //     return [
    //         'total_numbers' => $totalNumbers,
    //         'sold_numbers' => $soldNumbers,
    //         'available_numbers' => $availableNumbers,
    //         'percentage_sold' => round($percentageSold, 2),
    //         'total_revenue' => $this->purchases()
    //             ->where('status', 'completed')
    //             ->sum('amount'),
    //         'total_participants' => $this->purchases()
    //             ->distinct('user_id')
    //             ->count('user_id'),
    //     ];
    // }

    /**
     * Scope para eventos activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    /**
     * Verificar si el evento está activo
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && now()->gte($this->start_date)
            && now()->lte($this->end_date);
    }

    public function getImageUrlAttribute($value)
    {
        return $value ?: null; // Retorna null si no hay imagen
    }

    /**
     * Verificar si tiene imagen
     */
    public function hasImage(): bool
    {
        return !empty($this->image_url);
    }

      public function hasCompletedPurchaseForNumber(int $number): bool
    {
        return $this->purchases()
            ->where('ticket_number', $number)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Obtener información completa de un ticket específico
     * Mejorado: Incluye información de usuario autenticado o guest
     */
    public function getTicketInfo(int $number): ?object
    {
        $purchase = $this->purchases()
            ->where('ticket_number', $number)
            ->with('user') // El user puede ser null
            ->first();

        if (!$purchase) {
            return null;
        }

        return (object)[
            'ticket_number' => $purchase->ticket_number,
            'status' => $purchase->status,
            'amount' => $purchase->amount,
            'currency' => $purchase->currency,
            'purchased_at' => $purchase->created_at,

            // Información del comprador (funciona para auth y guest)
            'customer' => [
                'name' => $purchase->getCustomerName(),
                'email' => $purchase->getCustomerEmail(),
                'whatsapp' => $purchase->getCustomerWhatsapp(),
                'is_authenticated' => $purchase->hasAuthenticatedUser(),
                'user_id' => $purchase->user_id,
            ],

            // Información adicional útil
            'transaction_id' => $purchase->transaction_id,
            'qr_code_url' => $purchase->qr_code_url,
        ];
    }

    /**
     * Obtener información del ganador (si existe)
     */
    public function getWinnerInfo(): ?object
    {
        if (!$this->winner_number) {
            return null;
        }

        return $this->getTicketInfo($this->winner_number);
    }

    /**
     * Verificar si un ticket específico es el ganador
     */
    public function isWinningTicket(int $number): bool
    {
        return $this->winner_number === $number;
    }

    /**
     * Obtener todos los participantes con sus tickets
     * Mejorado: Incluye usuarios autenticados y guests
     */
    public function getParticipantsWithTickets(): array
    {
        $purchases = $this->purchases()
            ->where('status', 'completed')
            ->whereNotNull('ticket_number')
            ->with('user')
            ->orderBy('ticket_number')
            ->get();

        return $purchases->map(function ($purchase) {
            return [
                'ticket_number' => $purchase->ticket_number,
                'customer_name' => $purchase->getCustomerName(),
                'email' => $purchase->getCustomerEmail(),
                'whatsapp' => $purchase->getCustomerWhatsapp(),
                'is_authenticated' => $purchase->hasAuthenticatedUser(),
                'purchased_at' => $purchase->created_at,
                'is_winner' => $this->isWinningTicket($purchase->ticket_number),
            ];
        })->toArray();
    }

    /**
     * Obtener estadísticas del evento
     * Mejorado: Incluye información de participantes autenticados y guests
     */
    public function getStatistics(): array
    {
        $totalNumbers = ($this->end_number - $this->start_number) + 1;
        $soldNumbers = $this->purchases()
            ->whereNotNull('ticket_number')
            ->where('status', 'completed')
            ->count();
        $availableNumbers = $totalNumbers - $soldNumbers;
        $percentageSold = $totalNumbers > 0 ? ($soldNumbers / $totalNumbers) * 100 : 0;

        // Contar participantes únicos (por email o user_id)
        $uniqueParticipants = $this->purchases()
            ->where('status', 'completed')
            ->get()
            ->unique(function ($purchase) {
                // Si tiene usuario autenticado, usa user_id, sino usa email
                return $purchase->user_id ?? $purchase->email;
            })
            ->count();

        // Separar participantes autenticados vs guests
        $authenticatedCount = $this->purchases()
            ->where('status', 'completed')
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $guestCount = $this->purchases()
            ->where('status', 'completed')
            ->whereNull('user_id')
            ->distinct('email')
            ->count('email');

        return [
            'total_numbers' => $totalNumbers,
            'sold_numbers' => $soldNumbers,
            'available_numbers' => $availableNumbers,
            'percentage_sold' => round($percentageSold, 2),
            'total_revenue' => $this->purchases()
                ->where('status', 'completed')
                ->sum('amount'),
            'total_participants' => $uniqueParticipants,
            'authenticated_participants' => $authenticatedCount,
            'guest_participants' => $guestCount,
            'has_winner' => !is_null($this->winner_number),
        ];
    }
}
