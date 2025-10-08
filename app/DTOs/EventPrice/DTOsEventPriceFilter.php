<?php

namespace App\DTOs\EventPrice;

use Illuminate\Http\Request;

class DTOsEventPriceFilter
{
    public function __construct(
        private readonly ?int $event_id = null,
        private readonly ?string $currency = null,
        private readonly ?bool $is_default = null,
        private readonly ?bool $is_active = null,
        private readonly ?string $sort_by = 'created_at',
        private readonly ?string $sort_order = 'desc',
        private readonly int $page = 1,
        private readonly int $per_page = 15
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            event_id: $request->get('event_id') ? (int) $request->get('event_id') : null,
            currency: $request->get('currency'),
            is_default: $request->has('is_default') ? filter_var($request->get('is_default'), FILTER_VALIDATE_BOOLEAN) : null,
            is_active: $request->has('is_active') ? filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN) : null,
            sort_by: $request->get('sort_by', 'created_at'),
            sort_order: $request->get('sort_order', 'desc'),
            page: (int) $request->get('page', 1),
            per_page: (int) $request->get('per_page', 15)
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'event_id' => $this->event_id,
            'currency' => $this->currency,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'sort_by' => $this->sort_by,
            'sort_order' => $this->sort_order,
            'page' => $this->page,
            'per_page' => $this->per_page
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }

    // Getters
    public function getEventId(): ?int
    {
        return $this->event_id;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getIsDefault(): ?bool
    {
        return $this->is_default;
    }

    public function getIsActive(): ?bool
    {
        return $this->is_active;
    }

    public function getSortBy(): string
    {
        return $this->sort_by;
    }

    public function getSortOrder(): string
    {
        return $this->sort_order;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->per_page;
    }

    // Métodos de utilidad
    public function hasFilters(): bool
    {
        return !empty($this->event_id) ||
               !empty($this->currency) ||
               $this->is_default !== null ||
               $this->is_active !== null;
    }

    public function getValidCurrencies(): array
    {
        return ['USD', 'VES', 'EUR', 'SOL'];
    }

    public function isValidCurrency(): bool
    {
        if (empty($this->currency)) {
            return true;
        }
        return in_array($this->currency, $this->getValidCurrencies());
    }

    public function getValidSortFields(): array
    {
        return ['created_at', 'amount', 'currency', 'event_id'];
    }

    public function isValidSortField(): bool
    {
        return in_array($this->sort_by, $this->getValidSortFields());
    }

    public function getValidSortOrders(): array
    {
        return ['asc', 'desc'];
    }

    public function isValidSortOrder(): bool
    {
        return in_array($this->sort_order, $this->getValidSortOrders());
    }
}
