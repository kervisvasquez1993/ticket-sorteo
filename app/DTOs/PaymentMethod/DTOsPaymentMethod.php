<?php

namespace App\DTOs\PaymentMethod;

use App\Http\Requests\PaymentMethod\CreatePaymentMethodRequest;
use App\Http\Requests\PaymentMethod\UpdatePaymentMethodRequest;

class DTOsPaymentMethod
{
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly ?string $description = null,
        private readonly ?array $configuration = null,
        private readonly bool $is_active = true,
        private readonly int $order = 0,
    ) {}

    public static function fromRequest(CreatePaymentMethodRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            name: $validated['name'],
            type: $validated['type'],
            description: $validated['description'] ?? null,
            configuration: $validated['configuration'] ?? null,
            is_active: $validated['is_active'] ?? true,
            order: $validated['order'] ?? 0,
        );
    }

    public static function fromUpdateRequest(UpdatePaymentMethodRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            name: $validated['name'],
            type: $validated['type'],
            description: $validated['description'] ?? null,
            configuration: $validated['configuration'] ?? null,
            is_active: $validated['is_active'] ?? true,
            order: $validated['order'] ?? 0,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'configuration' => $this->configuration,
            'is_active' => $this->is_active,
            'order' => $this->order,
        ], fn($value) => $value !== null);
    }

    // Getters
    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function getIsActive(): bool
    {
        return $this->is_active;
    }

    public function getOrder(): int
    {
        return $this->order;
    }
}
