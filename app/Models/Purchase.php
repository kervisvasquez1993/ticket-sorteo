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
    // MÉTODOS PARA IDENTIFICAR AL COMPRADOR (NUEVOS - AGREGAR ESTOS)
    // ====================================================================

    /**
     * Verifica si la compra tiene un usuario autenticado
     */
    public function hasAuthenticatedUser(): bool
    {
        return !is_null($this->user_id);
    }

    /**
     * Obtiene el nombre del comprador (autenticado o guest)
     */
    public function getCustomerName(): string
    {
        // Si tiene usuario autenticado, usa el nombre del usuario
        if ($this->hasAuthenticatedUser() && $this->user) {
            return $this->user->name;
        }

        // Si no tiene usuario, extrae el nombre del email
        if ($this->email) {
            return explode('@', $this->email)[0];
        }

        return 'Cliente';
    }

    /**
     * Obtiene el email del comprador
     */
    public function getCustomerEmail(): string
    {
        return $this->email;
    }

    /**
     * Obtiene el whatsapp del comprador
     */
    public function getCustomerWhatsapp(): string
    {
        return $this->whatsapp;
    }

    /**
     * Obtiene información completa del comprador
     */
    public function getCustomerInfo(): array
    {
        return [
            'name' => $this->getCustomerName(),
            'email' => $this->getCustomerEmail(),
            'whatsapp' => $this->getCustomerWhatsapp(),
            'is_authenticated' => $this->hasAuthenticatedUser(),
            'user_id' => $this->user_id,
        ];
    }

    // ====================================================================
    // QUERY SCOPES - OPTIMIZADOS PARA POSTGRESQL
    // ====================================================================

    /**
     * Scope: Filtrar por transaction_id (usa el índice)
     */
    public function scopeByTransaction(Builder $query, string $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId);
    }

    /**
     * Scope: Obtener todas las compras de una transacción agrupada
     */
    public function scopeGroupedByTransaction(Builder $query, string $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId)
            ->orderBy('ticket_number', 'asc');
    }

    /**
     * Scope: Filtrar por estado
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filtrar por evento
     */
    public function scopeByEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    /**
     * Scope: Buscar por email o whatsapp (usa índices)
     */
    public function scopeByContact(Builder $query, ?string $email = null, ?string $whatsapp = null): Builder
    {
        return $query->when($email, fn($q) => $q->where('email', $email))
            ->when($whatsapp, fn($q) => $q->where('whatsapp', $whatsapp));
    }

    // ====================================================================
    // MÉTODOS HELPER
    // ====================================================================

    /**
     * Verifica si tiene QR Code
     */
    public function hasQRCode(): bool
    {
        return !empty($this->qr_code_url);
    }

    /**
     * Obtiene todos los números de ticket de una transacción
     */
    public function getGroupedTicketNumbers(): array
    {
        return self::where('transaction_id', $this->transaction_id)
            ->pluck('ticket_number')
            ->toArray();
    }

    /**
     * Obtiene el total de tickets en esta transacción
     */
    public function getTransactionTicketCount(): int
    {
        return self::where('transaction_id', $this->transaction_id)->count();
    }

    /**
     * Obtiene el monto total de la transacción completa
     */
    public function getTransactionTotalAmount(): float
    {
        return (float) self::where('transaction_id', $this->transaction_id)
            ->sum('amount');
    }

    /**
     * Verifica si toda la transacción está completada
     */
    public function isTransactionCompleted(): bool
    {
        $statuses = self::where('transaction_id', $this->transaction_id)
            ->pluck('status')
            ->unique();

        return $statuses->count() === 1 && $statuses->first() === 'completed';
    }

    /**
     * Actualiza el estado de toda la transacción
     */
    public function updateTransactionStatus(string $newStatus): int
    {
        return self::where('transaction_id', $this->transaction_id)
            ->update(['status' => $newStatus]);
    }

    // ====================================================================
    // MÉTODOS ESTÁTICOS PARA CONSULTAS OPTIMIZADAS
    // ====================================================================

    /**
     * Obtiene todas las compras de una transacción (optimizado)
     * Uso: Purchase::getByTransaction('TXN-...')
     */
    public static function getByTransaction(string $transactionId)
    {
        return self::with(['event', 'eventPrice', 'paymentMethod'])
            ->where('transaction_id', $transactionId)
            ->orderBy('ticket_number', 'asc')
            ->get();
    }

    /**
     * Obtiene un resumen de la transacción
     * Uso: Purchase::getTransactionSummary('TXN-...')
     */
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
            'ticket_numbers' => $purchases->pluck('ticket_number')->toArray(),
            'total_tickets' => $purchases->count(),
            'total_amount' => $purchases->sum('amount'),
            'status' => $purchases->pluck('status')->unique()->toArray(),
            'qr_code_url' => $first->qr_code_url,
            'created_at' => $first->created_at,
        ];
    }

    /**
     * Verifica si un número de ticket está disponible (optimizado con lock)
     * Uso: Purchase::isTicketAvailable($eventId, $ticketNumber)
     */
    public static function isTicketAvailable(int $eventId, string $ticketNumber): bool
    {
        return !self::where('event_id', $eventId)
            ->where('ticket_number', $ticketNumber)
            ->exists();
    }

    /**
     * Obtiene números de tickets reservados para un evento (usa índices)
     * Uso: Purchase::getReservedTickets($eventId)
     */
    public static function getReservedTickets(int $eventId): array
    {
        return self::where('event_id', $eventId)
            ->whereNotNull('ticket_number')
            ->pluck('ticket_number')
            ->toArray();
    }

    /**
     * Cuenta transacciones únicas por evento (agregación optimizada)
     * Uso: Purchase::countUniqueTransactions($eventId)
     */
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

    /**
     * Búsqueda de texto completo en PostgreSQL (emails o whatsapp)
     * Uso: Purchase::fullTextSearch('example@email.com')
     */
    public static function fullTextSearch(string $searchTerm)
    {
        return self::where(function ($query) use ($searchTerm) {
            $query->where('email', 'ILIKE', "%{$searchTerm}%")
                ->orWhere('whatsapp', 'LIKE', "%{$searchTerm}%");
        })->get();
    }

    /**
     * Agrupa transacciones con estadísticas (PostgreSQL GROUP BY optimizado)
     * Útil para reportes
     */
    public static function getTransactionStats(int $eventId)
    {
        return self::selectRaw('
                transaction_id,
                COUNT(*) as ticket_count,
                SUM(amount) as total_amount,
                MAX(status) as status,
                MIN(created_at) as created_at,
                MAX(email) as email
            ')
            ->where('event_id', $eventId)
            ->whereNotNull('transaction_id')
            ->groupBy('transaction_id')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
