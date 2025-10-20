<?php

namespace App\DTOs\EventPrize;

use App\Http\Requests\EventPrize\CreateEventPrizeRequest;
use App\Http\Requests\EventPrize\UpdateEventPrizeRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DTOsEventPrize
{
    public function __construct(
        private readonly int $event_id,
        private readonly ?string $title,
        private readonly ?string $description,
        private readonly string $image_url,
        private readonly bool $is_main = false,
    ) {}

    public static function fromRequest(CreateEventPrizeRequest $request): self
    {
        $validated = $request->validated();

        // ✅ Subir imagen a S3
        $imageUrl = self::uploadPrizeImageToS3($request);

        if (!$imageUrl) {
            throw new \Exception('Failed to upload prize image');
        }

        return new self(
            event_id: $validated['event_id'],
            title: $validated['title'] ?? null,
            description: $validated['description'] ?? null,
            image_url: $imageUrl,
            is_main: $validated['is_main'] ?? false,
        );
    }

    public static function fromUpdateRequest(UpdateEventPrizeRequest $request, ?string $currentImageUrl = null): self
    {
        $validated = $request->validated();

        // ✅ Si hay nueva imagen, subirla; si no, mantener la actual
        $imageUrl = $request->hasFile('image')
            ? self::uploadPrizeImageToS3($request)
            : $currentImageUrl;

        if (!$imageUrl) {
            throw new \Exception('Image URL is required');
        }

        return new self(
            event_id: $validated['event_id'],
            title: $validated['title'] ?? null,
            description: $validated['description'] ?? null,
            image_url: $imageUrl,
            is_main: $validated['is_main'] ?? false,
        );
    }

    /**
     * Subir imagen del premio a S3
     */
    private static function uploadPrizeImageToS3(CreateEventPrizeRequest|UpdateEventPrizeRequest $request): ?string
    {
        if (!$request->hasFile('image')) {
            return null;
        }

        try {
            $file = $request->file('image');

            // Generar nombre único para el archivo
            $fileName = 'prize-images/' . uniqid() . '.' . $file->getClientOriginalExtension();

            // Subir a S3 con visibilidad pública
            $uploaded = Storage::disk('s3')->put($fileName, file_get_contents($file), [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType()
            ]);

            if ($uploaded) {
                // Retornar URL completa
                $url = "https://backend-imagen-br.s3.us-east-2.amazonaws.com/" . $fileName;

                Log::info('Prize image uploaded successfully', [
                    'file_name' => $fileName,
                    'url' => $url
                ]);

                return $url;
            }

            Log::error('Failed to upload prize image to S3');
            return null;

        } catch (\Exception $e) {
            Log::error('Error uploading prize image', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->event_id,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'is_main' => $this->is_main,
        ];
    }

    // Getters
    public function getEventId(): int
    {
        return $this->event_id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getImageUrl(): string
    {
        return $this->image_url;
    }

    public function isMain(): bool
    {
        return $this->is_main;
    }
}
