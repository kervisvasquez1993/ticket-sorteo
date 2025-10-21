<?php

namespace App\DTOs\Auth;

use Illuminate\Http\Request;

class DTOsUserFilter
{
    public function __construct(
        private readonly ?string $email = null,
        private readonly ?string $name = null,
        private readonly ?string $role = null,
        private readonly ?string $search = null,
        private readonly ?string $sort_by = 'created_at',
        private readonly ?string $sort_order = 'desc',
        private readonly int $page = 1,
        private readonly int $per_page = 15
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            email: $request->get('email'),
            name: $request->get('name'),
            role: $request->get('role'),
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
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
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
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getRole(): ?string
    {
        return $this->role;
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
        return !empty($this->email) ||
            !empty($this->name) ||
            !empty($this->role) ||
            !empty($this->search);
    }

    public function getValidRoles(): array
    {
        return ['admin', 'customer'];
    }

    public function isValidRole(): bool
    {
        if (empty($this->role)) {
            return true;
        }
        return in_array($this->role, $this->getValidRoles());
    }

    public function getValidSortFields(): array
    {
        return ['created_at', 'name', 'email', 'role'];
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

        if (!empty($this->email)) {
            $filters['email'] = $this->email;
        }

        if (!empty($this->name)) {
            $filters['name'] = $this->name;
        }

        if (!empty($this->role) && $this->isValidRole()) {
            $filters['role'] = $this->role;
        }

        if (!empty($this->search)) {
            $filters['search'] = $this->search;
        }

        return $filters;
    }
}
