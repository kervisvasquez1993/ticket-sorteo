<?php
// app/DTOs/Purchase/DTOsAddTickets.php

namespace App\DTOs\Purchase;

use App\Http\Requests\Purchase\AddTicketsToTransactionRequest;

class DTOsAddTickets
{
    private static function formatTicketNumbers(?array $numbers): ?array
    {
        if (is_null($numbers) || empty($numbers)) {
            return null;
        }

        return array_map(function ($number) {
            if (is_string($number) && str_starts_with($number, 'RECHAZADO')) {
                return $number;
            }
            return str_pad((int)$number, 4, '0', STR_PAD_LEFT);
        }, $numbers);
    }

    public function __construct(
        private readonly string $transaction_id,
        private readonly ?int $quantity = null,
        private readonly ?array $ticket_numbers = null,
    ) {}

    /**
     * ✅ Crear DTO desde el Request validado
     */
    public static function fromRequest(
        AddTicketsToTransactionRequest $request,
        string $transactionId
    ): self {
        $validated = $request->validated();

        return new self(
            transaction_id: $transactionId,
            quantity: $validated['quantity'] ?? null,
            ticket_numbers: self::formatTicketNumbers($validated['ticket_numbers'] ?? null),
        );
    }

    /**
     * ✅ Determinar el modo de operación
     */
    public function getMode(): string
    {
        return $this->ticket_numbers !== null ? 'specific' : 'random';
    }

    /**
     * ✅ Verificar si es modo aleatorio
     */
    public function isRandomMode(): bool
    {
        return $this->quantity !== null;
    }

    /**
     * ✅ Verificar si es modo específico
     */
    public function isSpecificMode(): bool
    {
        return $this->ticket_numbers !== null;
    }

    /**
     * ✅ Obtener cantidad de tickets a agregar
     */
    public function getTicketCount(): int
    {
        return $this->isRandomMode()
            ? $this->quantity
            : count($this->ticket_numbers);
    }

    // Getters
    public function getTransactionId(): string
    {
        return $this->transaction_id;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function getTicketNumbers(): ?array
    {
        return $this->ticket_numbers;
    }

    public function toArray(): array
    {
        return array_filter([
            'transaction_id' => $this->transaction_id,
            'quantity' => $this->quantity,
            'ticket_numbers' => $this->ticket_numbers,
            'mode' => $this->getMode(),
            'ticket_count' => $this->getTicketCount(),
        ], fn($value) => !is_null($value));
    }
}
