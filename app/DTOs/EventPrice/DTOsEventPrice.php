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
        private readonly ?bool $is_default = false,  // 👈 Cambiar a ?bool
        private readonly ?bool $is_active = true,    // 👈 Cambiar a ?bool
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
            event_id: $validated['event_id'] ?? 0,
            amount: isset($validated['amount']) ? (float) $validated['amount'] : 0,
            currency: $validated['currency'] ?? '',
            is_default: $validated['is_default'] ?? null,  // 👈 null cuando no viene
            is_active: $validated['is_active'] ?? null,    // 👈 null cuando no viene
        );
    }

    public function toArray(): array
    {
        $data = [];

        // Solo agregar campos que tienen valores válidos
        if ($this->event_id > 0) {
            $data['event_id'] = $this->event_id;
        }

        if ($this->amount > 0) {
            $data['amount'] = $this->amount;
        }

        if (!empty($this->currency)) {
            $data['currency'] = $this->currency;
        }

        // Solo agregar booleanos si no son null
        if ($this->is_default !== null) {
            $data['is_default'] = $this->is_default;
        }

        if ($this->is_active !== null) {
            $data['is_active'] = $this->is_active;
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

    public function isDefault(): ?bool
    {
        return $this->is_default;
    }

    public function isActive(): ?bool
    {
        return $this->is_active;
    }
}
