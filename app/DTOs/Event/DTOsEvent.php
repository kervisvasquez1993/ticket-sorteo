<?php

namespace App\DTOs\Event;

use App\Http\Requests\Event\CreateEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;

class DTOsEvent
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $description,
        private readonly int $start_number,
        private readonly int $end_number,
        private readonly string $start_date,
        private readonly string $end_date,
        private readonly array $prices = [],
        private readonly string $status = 'active',
    ) {}

    public static function fromRequest(CreateEventRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            start_number: $validated['start_number'],
            end_number: $validated['end_number'],
            start_date: $validated['start_date'],
            end_date: $validated['end_date'],
            prices: $validated['prices'] ?? [],
            status: $validated['status'] ?? 'active',
        );
    }

    public static function fromUpdateRequest(UpdateEventRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            start_number: $validated['start_number'],
            end_number: $validated['end_number'],
            start_date: $validated['start_date'],
            end_date: $validated['end_date'],
            prices: $validated['prices'] ?? [],
            status: $validated['status'] ?? 'active',
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'start_number' => $this->start_number,
            'end_number' => $this->end_number,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
        ];
    }

    // Getters
    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStartNumber(): int
    {
        return $this->start_number;
    }

    public function getEndNumber(): int
    {
        return $this->end_number;
    }

    public function getStartDate(): string
    {
        return $this->start_date;
    }

    public function getEndDate(): string
    {
        return $this->end_date;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPrices(): array
    {
        return $this->prices;
    }
}
