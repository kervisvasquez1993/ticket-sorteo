<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\ChangePasswordRequest;

class DTOsChangePassword
{
    public function __construct(
        private readonly int $userId,
        private readonly string $password,
    ) {}

    public static function fromRequest(ChangePasswordRequest $request): self
    {
        return new self(
            userId: $request->validated('user_id'),
            password: $request->validated('password')
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'password' => $this->password
        ];
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
