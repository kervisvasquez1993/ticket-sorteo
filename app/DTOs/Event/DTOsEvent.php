<?php

namespace App\DTOs\Event;

use App\Http\Requests\Event\CreateEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;

class DTOsEvent
{
    public function __construct(
        private readonly ?string $name = null,
        private readonly ?string $description = null,
        private readonly ?int $start_number = null,
        private readonly ?int $end_number = null,
        private readonly ?string $start_date = null,
        private readonly ?string $end_date = null,
        private readonly ?string $status = null,
        private readonly ?string $image_url = null,
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
            status: $validated['status'] ?? 'active',
            image_url: $validated['image_url'] ?? null,
        );
    }

    /**
     * ✅ ACTUALIZADO: Solo incluye los campos que vienen en el request
     */
    public static function fromUpdateRequest(
        UpdateEventRequest $request,
        ?string $currentImageUrl = null
    ): self {
        $validated = $request->validated();

        return new self(
            name: $validated['name'] ?? null,
            description: $validated['description'] ?? null,
            start_number: $validated['start_number'] ?? null,
            end_number: $validated['end_number'] ?? null,
            start_date: $validated['start_date'] ?? null,
            end_date: $validated['end_date'] ?? null,
            status: $validated['status'] ?? null,
            image_url: $currentImageUrl, // Siempre mantiene la imagen actual
        );
    }

    /**
     * ✅ ACTUALIZADO: Solo retorna campos no nulos
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'start_number' => $this->start_number,
            'end_number' => $this->end_number,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'image_url' => $this->image_url,
        ], function ($value) {
            return $value !== null;
        });
    }

    // Getters actualizados para retornar nullable
    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStartNumber(): ?int
    {
        return $this->start_number;
    }

    public function getEndNumber(): ?int
    {
        return $this->end_number;
    }

    public function getStartDate(): ?string
    {
        return $this->start_date;
    }

    public function getEndDate(): ?string
    {
        return $this->end_date;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }
}
