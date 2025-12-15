<?php

namespace App\DTOs\Purchase;

use Illuminate\Http\Request;

class DTOsPurchaseFilter
{
    private static function formatTicketNumberSearch(?string $ticketNumber): ?string
    {
        if (is_null($ticketNumber) || empty($ticketNumber)) {
            return null;
        }
        if (is_numeric($ticketNumber)) {
            return str_pad((int)$ticketNumber, 4, '0', STR_PAD_LEFT);
        }
        return $ticketNumber;
    }

    public function __construct(
        private readonly ?int $user_id = null,
        private readonly ?int $event_id = null,
        private readonly ?string $status = null,
        private readonly ?string $currency = null,
        private readonly ?int $payment_method_id = null,
        private readonly ?string $transaction_id = null,
        private readonly ?string $date_from = null,
        private readonly ?string $date_to = null,
        private readonly ?string $ticket_number = null,
        private readonly ?string $fullname = null,
        private readonly ?string $email = null, // ✨ NUEVO
        private readonly ?string $whatsapp = null, // ✨ NUEVO
        private readonly ?string $identificacion = null, // ✨ NUEVO
        private readonly ?string $payment_reference = null, // ✨ NUEVO
        private readonly ?bool $is_admin_purchase = null, // ✨ NUEVO
        private readonly ?int $min_quantity = null,
        private readonly ?int $max_quantity = null, // ✨ NUEVO
        private readonly ?float $min_amount = null, // ✨ NUEVO
        private readonly ?float $max_amount = null, // ✨ NUEVO
        private readonly ?string $sort_by = 'quantity',
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
            ticket_number: self::formatTicketNumberSearch($request->get('ticket_number')),
            fullname: $request->get('fullname'),
            email: $request->get('email'), // ✨ NUEVO
            whatsapp: $request->get('whatsapp'), // ✨ NUEVO
            identificacion: $request->get('identificacion'), // ✨ NUEVO
            payment_reference: $request->get('payment_reference'), // ✨ NUEVO
            is_admin_purchase: $request->has('is_admin_purchase')
                ? filter_var($request->get('is_admin_purchase'), FILTER_VALIDATE_BOOLEAN)
                : null, // ✨ NUEVO
            min_quantity: $request->get('min_quantity') ? (int) $request->get('min_quantity') : null,
            max_quantity: $request->get('max_quantity') ? (int) $request->get('max_quantity') : null, // ✨ NUEVO
            min_amount: $request->get('min_amount') ? (float) $request->get('min_amount') : null, // ✨ NUEVO
            max_amount: $request->get('max_amount') ? (float) $request->get('max_amount') : null, // ✨ NUEVO
            sort_by: $request->get('sort_by', 'quantity'),
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
            'ticket_number' => $this->ticket_number,
            'fullname' => $this->fullname,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'identificacion' => $this->identificacion,
            'payment_reference' => $this->payment_reference,
            'is_admin_purchase' => $this->is_admin_purchase,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'min_amount' => $this->min_amount,
            'max_amount' => $this->max_amount,
            'sort_by' => $this->sort_by,
            'sort_order' => $this->sort_order,
            'page' => $this->page,
            'per_page' => $this->per_page
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }

    // ✨ GETTERS
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
    public function getTicketNumber(): ?string
    {
        return $this->ticket_number;
    }
    public function getFullname(): ?string
    {
        return $this->fullname;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function getWhatsapp(): ?string
    {
        return $this->whatsapp;
    }
    public function getIdentificacion(): ?string
    {
        return $this->identificacion;
    }
    public function getPaymentReference(): ?string
    {
        return $this->payment_reference;
    }
    public function getIsAdminPurchase(): ?bool
    {
        return $this->is_admin_purchase;
    }
    public function getMinQuantity(): ?int
    {
        return $this->min_quantity;
    }
    public function getMaxQuantity(): ?int
    {
        return $this->max_quantity;
    }
    public function getMinAmount(): ?float
    {
        return $this->min_amount;
    }
    public function getMaxAmount(): ?float
    {
        return $this->max_amount;
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
            !empty($this->ticket_number) ||
            !empty($this->fullname) ||
            !empty($this->email) ||
            !empty($this->whatsapp) ||
            !empty($this->identificacion) ||
            !empty($this->payment_reference) ||
            !is_null($this->is_admin_purchase) ||
            !empty($this->min_quantity) ||
            !empty($this->max_quantity) ||
            !empty($this->min_amount) ||
            !empty($this->max_amount);
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
        return ['created_at', 'total_amount', 'status', 'quantity', 'total_customer_purchased'];
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

        if (!empty($this->ticket_number)) {
            $filters['ticket_number'] = $this->ticket_number;
        }

        if (!empty($this->fullname)) {
            $filters['fullname'] = $this->fullname;
        }

        if (!empty($this->email)) {
            $filters['email'] = $this->email;
        }

        if (!empty($this->whatsapp)) {
            $filters['whatsapp'] = $this->whatsapp;
        }

        if (!empty($this->identificacion)) {
            $filters['identificacion'] = $this->identificacion;
        }

        if (!empty($this->payment_reference)) {
            $filters['payment_reference'] = $this->payment_reference;
        }

        if (!is_null($this->is_admin_purchase)) {
            $filters['is_admin_purchase'] = $this->is_admin_purchase;
        }

        if (!empty($this->min_quantity)) {
            $filters['min_quantity'] = $this->min_quantity;
        }

        if (!empty($this->max_quantity)) {
            $filters['max_quantity'] = $this->max_quantity;
        }

        if (!empty($this->min_amount)) {
            $filters['min_amount'] = $this->min_amount;
        }

        if (!empty($this->max_amount)) {
            $filters['max_amount'] = $this->max_amount;
        }

        return $filters;
    }
}
