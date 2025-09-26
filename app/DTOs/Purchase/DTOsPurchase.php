<?php

namespace App\DTOs\Purchase;

use App\Http\Requests\Purchase\CreatePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use Illuminate\Support\Facades\Auth;

class DTOsPurchase
{
    public function __construct(
        private readonly int $event_id,
        private readonly int $event_price_id,
        private readonly int $payment_method_id,
        private readonly int $quantity,
        private readonly ?string $currency = null,
        private readonly ?int $user_id = null,
        private readonly ?array $specific_numbers = null, // Para permitir números específicos
    ) {}

    public static function fromRequest(CreatePurchaseRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            event_id: $validated['event_id'],
            event_price_id: $validated['event_price_id'],
            payment_method_id: $validated['payment_method_id'],
            quantity: $validated['quantity'] ?? 1,
            currency: $validated['currency'] ?? null,
            user_id: Auth::id(),
            specific_numbers: $validated['specific_numbers'] ?? null,
        );
    }

    public static function fromUpdateRequest(UpdatePurchaseRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            event_id: $validated['event_id'],
            event_price_id: $validated['event_price_id'],
            payment_method_id: $validated['payment_method_id'],
            quantity: $validated['quantity'] ?? 1,
            currency: $validated['currency'] ?? null,
            user_id: Auth::id(),
            specific_numbers: $validated['specific_numbers'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'event_id' => $this->event_id,
            'event_price_id' => $this->event_price_id,
            'payment_method_id' => $this->payment_method_id,
            'quantity' => $this->quantity,
            'currency' => $this->currency,
            'user_id' => $this->user_id,
            'specific_numbers' => $this->specific_numbers,
        ], fn($value) => !is_null($value));
    }

    public function getEventId(): int
    {
        return $this->event_id;
    }

    public function getEventPriceId(): int
    {
        return $this->event_price_id;
    }

    public function getPaymentMethodId(): int
    {
        return $this->payment_method_id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getSpecificNumbers(): ?array
    {
        return $this->specific_numbers;
    }
}
