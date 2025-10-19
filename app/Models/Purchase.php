<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_id',
        'event_price_id',
        'payment_method_id',
        'ticket_number',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'payment_reference',
        'payment_proof_url',
        'quantity',
        'qr_code_url',
        'total_amount',
        'email',
        'whatsapp',
        'identificacion', // ✅ NUEVO
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // ====================================================================
    // RELATIONSHIPS
    // ====================================================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function eventPrice()
    {
        return $this->belongsTo(EventPrice::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    // ====================================================================
    // MÉTODOS PARA IDENTIFICAR AL COMPRADOR
    // ====================================================================

    public function hasAuthenticatedUser(): bool
    {
        return !is_null($this->user_id);
    }

    public function getCustomerName(): string
    {
        if ($this->hasAuthenticatedUser() && $this->user) {
            return $this->user->name;
        }

        if ($this->email) {
            return explode('@', $this->email)[0];
        }

        return 'Cliente';
    }

    public function getCustomerEmail(): ?string
    {
        return $this->email;
    }

    public function getCustomerWhatsapp(): ?string
    {
        return $this->whatsapp;
    }

    // ✅ NUEVO: Obtener identificación
    public function getCustomerIdentificacion(): ?string
    {
        return $this->identificacion;
    }

    public function getCustomerInfo(): array
    {
        return [
            'name' => $this->getCustomerName(),
            'email' => $this->getCustomerEmail(),
            'whatsapp' => $this->getCustomerWhatsapp(),
            'identificacion' => $this->getCustomerIdentificacion(), // ✅ NUEVO
            'is_authenticated' => $this->hasAuthenticatedUser(),
            'user_id' => $this->user_id,
        ];
    }

    // ====================================================================
    // QUERY SCOPES - OPTIMIZADOS PARA POSTGRESQL
    // ====================================================================

    public function scopeByTransaction(Builder $query, string $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId);
    }

    public function scopeGroupedByTransaction(Builder $query, string $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId)
            ->orderBy('ticket_number', 'asc');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    // ✅ NUEVO: Búsqueda optimizada por identificación
    public function scopeByIdentificacion(Builder $query, string $identificacion): Builder
    {
        return $query->where('identificacion', $identificacion);
    }

    public function scopeByContact(Builder $query, ?string $email = null, ?string $whatsapp = null): Builder
    {
        return $query->when($email, fn($q) => $q->where('email', $email))
            ->when($whatsapp, fn($q) => $q->where('whatsapp', $whatsapp));
    }

    // ✅ NUEVO: Búsqueda completa por cualquier dato de contacto
    public function scopeByAnyContact(Builder $query, ?string $email = null, ?string $whatsapp = null, ?string $identificacion = null): Builder
    {
        return $query->where(function($q) use ($email, $whatsapp, $identificacion) {
            if ($email) {
                $q->orWhere('email', $email);
            }
            if ($whatsapp) {
                $q->orWhere('whatsapp', $whatsapp);
            }
            if ($identificacion) {
                $q->orWhere('identificacion', $identificacion);
            }
        });
    }

    // ====================================================================
    // MÉTODOS HELPER
    // ====================================================================

    public function hasQRCode(): bool
    {
        return !empty($this->qr_code_url);
    }

    public function getGroupedTicketNumbers(): array
    {
        return self::where('transaction_id', $this->transaction_id)
            ->pluck('ticket_number')
            ->toArray();
    }

    public function getTransactionTicketCount(): int
    {
        return self::where('transaction_id', $this->transaction_id)->count();
    }

    public function getTransactionTotalAmount(): float
    {
        return (float) self::where('transaction_id', $this->transaction_id)
            ->sum('amount');
    }

    public function isTransactionCompleted(): bool
    {
        $statuses = self::where('transaction_id', $this->transaction_id)
            ->pluck('status')
            ->unique();

        return $statuses->count() === 1 && $statuses->first() === 'completed';
    }

    public function updateTransactionStatus(string $newStatus): int
    {
        return self::where('transaction_id', $this->transaction_id)
            ->update(['status' => $newStatus]);
    }

    // ====================================================================
    // MÉTODOS ESTÁTICOS PARA CONSULTAS OPTIMIZADAS
    // ====================================================================

    public static function getByTransaction(string $transactionId)
    {
        return self::with(['event', 'eventPrice', 'paymentMethod'])
            ->where('transaction_id', $transactionId)
            ->orderBy('ticket_number', 'asc')
            ->get();
    }

    public static function getTransactionSummary(string $transactionId): ?array
    {
        $purchases = self::where('transaction_id', $transactionId)->get();

        if ($purchases->isEmpty()) {
            return null;
        }

        $first = $purchases->first();

        return [
            'transaction_id' => $transactionId,
            'event_id' => $first->event_id,
            'email' => $first->email,
            'whatsapp' => $first->whatsapp,
            'identificacion' => $first->identificacion, // ✅ NUEVO
            'ticket_numbers' => $purchases->pluck('ticket_number')->toArray(),
            'total_tickets' => $purchases->count(),
            'total_amount' => $purchases->sum('amount'),
            'status' => $purchases->pluck('status')->unique()->toArray(),
            'qr_code_url' => $first->qr_code_url,
            'created_at' => $first->created_at,
        ];
    }

    public static function isTicketAvailable(int $eventId, string $ticketNumber): bool
    {
        return !self::where('event_id', $eventId)
            ->where('ticket_number', $ticketNumber)
            ->exists();
    }

    public static function getReservedTickets(int $eventId): array
    {
        return self::where('event_id', $eventId)
            ->whereNotNull('ticket_number')
            ->pluck('ticket_number')
            ->toArray();
    }

    public static function countUniqueTransactions(int $eventId): int
    {
        return self::where('event_id', $eventId)
            ->whereNotNull('transaction_id')
            ->distinct('transaction_id')
            ->count('transaction_id');
    }

    // ====================================================================
    // POSTGRESQL ESPECÍFICO: BÚSQUEDAS AVANZADAS
    // ====================================================================

    // ✅ MEJORADO: Búsqueda full-text incluyendo identificación
    public static function fullTextSearch(string $searchTerm)
    {
        return self::where(function ($query) use ($searchTerm) {
            $query->where('email', 'ILIKE', "%{$searchTerm}%")
                ->orWhere('whatsapp', 'LIKE', "%{$searchTerm}%")
                ->orWhere('identificacion', 'LIKE', "%{$searchTerm}%"); // ✅ NUEVO
        })->get();
    }

    // ✅ NUEVO: Buscar todas las compras por cédula
    public static function getByIdentificacion(string $identificacion)
    {
        return self::with(['event', 'eventPrice', 'paymentMethod'])
            ->where('identificacion', $identificacion)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public static function getTransactionStats(int $eventId)
    {
        return self::selectRaw('
                transaction_id,
                COUNT(*) as ticket_count,
                SUM(amount) as total_amount,
                MAX(status) as status,
                MIN(created_at) as created_at,
                MAX(email) as email,
                MAX(identificacion) as identificacion
            ')
            ->where('event_id', $eventId)
            ->whereNotNull('transaction_id')
            ->groupBy('transaction_id')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
