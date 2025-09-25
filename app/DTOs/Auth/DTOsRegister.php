<?php

namespace App\DTOs\Auth;

use App\Http\Requests\Auth\RegisterRequest;

class DTOsRegister
{
    public function __construct(
        private readonly string $username,
        private readonly string $email,
        private readonly string $password,
        private readonly string $role = 'client',


    ) {}

    public static function fromRequest(RegisterRequest $request): self
    {
        return new self(
            username: $request->validated('username'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            role: 'client'
        );
    }

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'role' => $this->role
        ];
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRole(): string
    {
        return $this->role;
    }


    public function getRoleList(): array
    {
        return ['client', 'admin'];
    }
    public function isValidRole(string $role): bool
    {
        return in_array($role, $this->getRoleList());
    }
    public function isValid(): bool
    {
        return $this->isValidRole($this->role);
    }
}
