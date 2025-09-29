<?php

namespace App\DTOs\Event;

use App\Http\Requests\Event\CreateEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        private readonly ?string $image_url = null,
    ) {}

    public static function fromRequest(CreateEventRequest $request): self
    {
        $validated = $request->validated();
        $imageUrl = self::uploadEventImageToS3($request);

        return new self(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            start_number: $validated['start_number'],
            end_number: $validated['end_number'],
            start_date: $validated['start_date'],
            end_date: $validated['end_date'],
            prices: $validated['prices'] ?? [],
            status: $validated['status'] ?? 'active',
            image_url: $imageUrl,
        );
    }

    public static function fromUpdateRequest(UpdateEventRequest $request): self
    {
        $validated = $request->validated();
        $imageUrl = self::uploadEventImageToS3($request);

        return new self(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            start_number: $validated['start_number'],
            end_number: $validated['end_number'],
            start_date: $validated['start_date'],
            end_date: $validated['end_date'],
            prices: $validated['prices'] ?? [],
            status: $validated['status'] ?? 'active',
            image_url: $imageUrl,
        );
    }

    /**
     * Subir imagen del evento a S3
     */
    private static function uploadEventImageToS3(CreateEventRequest|UpdateEventRequest $request): ?string
    {
        if ($request->hasFile('image')) {
            $file = $request->file('image');

            // Generar nombre único para el archivo
            $fileName = 'event-images/' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Subir a S3 con visibilidad pública
            $uploaded = Storage::disk('s3')->put($fileName, file_get_contents($file), [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType()
            ]);

            if ($uploaded) {
                // Retornar URL completa
                $url = "https://backend-imagen-br.s3.us-east-2.amazonaws.com/" . $fileName;

                Log::info('Event image uploaded successfully', [
                    'file_name' => $fileName,
                    'url' => $url
                ]);

                return $url;
            }
        }

        return null;
    }

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
        ], fn($value) => !is_null($value));
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

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }
}
