<?php

namespace App\DTOs\EventPrice;

use App\Http\Requests\EventPrice\CreateEventPriceRequest;
use App\Http\Requests\EventPrice\UpdateEventPriceRequest;

class DTOsEventPrice
{
    public function __construct(
        private readonly int $event_id,
        private readonly float $amount,
        private readonly string $currency,
        private readonly bool $is_default = false,
        private readonly bool $is_active = true,
    ) {}

    public static function fromRequest(CreateEventPriceRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            event_id: $validated['event_id'],
            amount: (float) $validated['amount'],
            currency: strtoupper($validated['currency']),
            is_default: $validated['is_default'] ?? false,
            is_active: $validated['is_active'] ?? true,
        );
    }

    public static function fromUpdateRequest(UpdateEventPriceRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            event_id: $validated['event_id'] ?? 0, // Se manejará en el servicio
            amount: isset($validated['amount']) ? (float) $validated['amount'] : 0,
            currency: isset($validated['currency']) ? strtoupper($validated['currency']) : '',
            is_default: $validated['is_default'] ?? false,
            is_active: $validated['is_active'] ?? true,
        );
    }

    public function toArray(): array
    {
        $data = [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
        ];

        // Solo agregar event_id si no es 0 (para updates)
        if ($this->event_id > 0) {
            $data['event_id'] = $this->event_id;
        }

        return $data;
    }

    public function getEventId(): int
    {
        return $this->event_id;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
