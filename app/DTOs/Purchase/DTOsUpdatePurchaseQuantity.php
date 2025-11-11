<?php

namespace App\DTOs\Purchase;

class DTOsUpdatePurchaseQuantity
{
    public function __construct(
        private readonly string $transaction_id,
        private readonly int $new_quantity,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            transaction_id: $data['transaction_id'],
            new_quantity: $data['new_quantity'],
        );
    }

    public function getTransactionId(): string
    {
        return $this->transaction_id;
    }

    public function getNewQuantity(): int
    {
        return $this->new_quantity;
    }

    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transaction_id,
            'new_quantity' => $this->new_quantity,
        ];
    }
}
