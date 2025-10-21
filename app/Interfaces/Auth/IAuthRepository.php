<?php

namespace App\Interfaces\Auth;

use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;
use App\DTOs\Auth\DTOsUserFilter;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface IAuthRepository
{
    public function login(DTOsLogin $loginDTO): array;
    public function createAccessToken(User $user): array;
    public function createUser(DTOsRegister $registerDTO): User;
    public function revokeToken(User $user): bool;
    public function updatePassword(int $userId, string $password): bool;
    public function getAllUsers(DTOsUserFilter $filters, int $excludeUserId): LengthAwarePaginator;

}
