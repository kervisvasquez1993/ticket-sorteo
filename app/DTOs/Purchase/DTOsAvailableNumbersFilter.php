<?php

namespace App\DTOs\Purchase;

use Illuminate\Http\Request;

class DTOsAvailableNumbersFilter
{
    private static function formatSearchNumber(?string $search): ?string
    {
        if (is_null($search) || empty($search)) {
            return null;
        }
        if (is_numeric($search)) {
            return str_pad((int)$search, 4, '0', STR_PAD_LEFT);
        }
        return $search;
    }
    public function __construct(
        private readonly int $event_id,
        private readonly ?string $search = null,
        private readonly ?int $min_number = null,
        private readonly ?int $max_number = null,
        private readonly int $page = 1,
        private readonly int $per_page = 30
    ) {}

    public static function fromRequest(Request $request, int $eventId): self
    {
        return new self(
            event_id: $eventId,
             search: self::formatSearchNumber($request->get('search')),
            min_number: $request->get('min_number') ? (int) $request->get('min_number') : null,
            max_number: $request->get('max_number') ? (int) $request->get('max_number') : null,
            page: (int) $request->get('page', 1),
            per_page: (int) $request->get('per_page', 30)
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'event_id' => $this->event_id,
            'search' => $this->search,
            'min_number' => $this->min_number,
            'max_number' => $this->max_number,
            'page' => $this->page,
            'per_page' => $this->per_page
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }

    // Getters
    public function getEventId(): int
    {
        return $this->event_id;
    }
    public function getSearch(): ?string
    {
        return $this->search;
    }
    public function getMinNumber(): ?int
    {
        return $this->min_number;
    }
    public function getMaxNumber(): ?int
    {
        return $this->max_number;
    }
    public function getPage(): int
    {
        return $this->page;
    }
    public function getPerPage(): int
    {
        return $this->per_page;
    }

    public function hasFilters(): bool
    {
        return !empty($this->search) ||
            !empty($this->min_number) ||
            !empty($this->max_number);
    }
}
