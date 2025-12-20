<?php

namespace App\DTOs\EventBlacklist;

use App\Http\Requests\EventBlacklist\CreateEventBlacklistRequest;
use App\Models\Purchase;
use Illuminate\Support\Facades\Auth;

class DTOsEventBlacklist
{
    public function __construct(
        private readonly int $event_id,
        private readonly string $identificacion,
        private readonly ?string $reason = null,
        private readonly ?int $added_by = null,
    ) {}

    public static function fromRequest(CreateEventBlacklistRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            event_id: $validated['event_id'],
            identificacion: Purchase::normalizeIdentificacion($validated['identificacion']),
            reason: $validated['reason'] ?? null,
            added_by: Auth::id(),
        );
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->event_id,
            'identificacion' => $this->identificacion,
            'reason' => $this->reason,
            'added_by' => $this->added_by,
        ];
    }

    // Getters
    public function getEventId(): int
    {
        return $this->event_id;
    }

    public function getIdentificacion(): string
    {
        return $this->identificacion;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getAddedBy(): ?int
    {
        return $this->added_by;
    }
}
