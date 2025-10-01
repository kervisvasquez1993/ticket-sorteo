<?php

namespace App\DTOs\Purchase;

use Illuminate\Http\Request;

class DTOsPurchaseFilter
{
    public function __construct(
        private readonly ?int $user_id = null,
        private readonly ?int $event_id = null,
        private readonly ?string $status = null,
        private readonly ?string $currency = null,
        private readonly ?int $payment_method_id = null,
        private readonly ?string $transaction_id = null,
        private readonly ?string $date_from = null,
        private readonly ?string $date_to = null,
        private readonly ?string $search = null,
        private readonly ?string $sort_by = 'created_at',
        private readonly ?string $sort_order = 'desc',
        private readonly int $page = 1,
        private readonly int $per_page = 15
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            user_id: $request->get('user_id') ? (int) $request->get('user_id') : null,
            event_id: $request->get('event_id') ? (int) $request->get('event_id') : null,
            status: $request->get('status'),
            currency: $request->get('currency'),
            payment_method_id: $request->get('payment_method_id') ? (int) $request->get('payment_method_id') : null,
            transaction_id: $request->get('transaction_id'),
            date_from: $request->get('date_from'),
            date_to: $request->get('date_to'),
            search: $request->get('search'),
            sort_by: $request->get('sort_by', 'created_at'),
            sort_order: $request->get('sort_order', 'desc'),
            page: (int) $request->get('page', 1),
            per_page: (int) $request->get('per_page', 15)
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'user_id' => $this->user_id,
            'event_id' => $this->event_id,
            'status' => $this->status,
            'currency' => $this->currency,
            'payment_method_id' => $this->payment_method_id,
            'transaction_id' => $this->transaction_id,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'search' => $this->search,
            'sort_by' => $this->sort_by,
            'sort_order' => $this->sort_order,
            'page' => $this->page,
            'per_page' => $this->per_page
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }

    // Getters
    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getEventId(): ?int
    {
        return $this->event_id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getPaymentMethodId(): ?int
    {
        return $this->payment_method_id;
    }

    public function getTransactionId(): ?string
    {
        return $this->transaction_id;
    }

    public function getDateFrom(): ?string
    {
        return $this->date_from;
    }

    public function getDateTo(): ?string
    {
        return $this->date_to;
    }

    public function getSearch(): ?string
    {
        return $this->search;
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
        return !empty($this->user_id) ||
               !empty($this->event_id) ||
               !empty($this->status) ||
               !empty($this->currency) ||
               !empty($this->payment_method_id) ||
               !empty($this->transaction_id) ||
               !empty($this->date_from) ||
               !empty($this->date_to) ||
               !empty($this->search);
    }

    public function getValidStatuses(): array
    {
        return ['pending', 'processing', 'completed', 'failed'];
    }

    public function isValidStatus(): bool
    {
        if (empty($this->status)) {
            return true;
        }
        return in_array($this->status, $this->getValidStatuses());
    }

    public function getValidCurrencies(): array
    {
        return ['BS', 'USD'];
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
        return ['created_at', 'total_amount', 'status', 'quantity'];
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

    public function getFiltersForQuery(): array
    {
        $filters = [];

        if (!empty($this->user_id)) {
            $filters['user_id'] = $this->user_id;
        }

        if (!empty($this->event_id)) {
            $filters['event_id'] = $this->event_id;
        }

        if (!empty($this->status) && $this->isValidStatus()) {
            $filters['status'] = $this->status;
        }

        if (!empty($this->currency) && $this->isValidCurrency()) {
            $filters['currency'] = $this->currency;
        }

        if (!empty($this->payment_method_id)) {
            $filters['payment_method_id'] = $this->payment_method_id;
        }

        if (!empty($this->transaction_id)) {
            $filters['transaction_id'] = $this->transaction_id;
        }

        if (!empty($this->date_from)) {
            $filters['date_from'] = $this->date_from;
        }

        if (!empty($this->date_to)) {
            $filters['date_to'] = $this->date_to;
        }

        if (!empty($this->search)) {
            $filters['search'] = $this->search;
        }

        return $filters;
    }
}
