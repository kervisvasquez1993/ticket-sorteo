<?php

namespace App\Interfaces\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;
use App\DTOs\Auth\DTOsChangePassword;
use App\DTOs\Auth\DTOsUserFilter;
use App\Models\User;

interface IAuthServices
{
    public function register(DTOsRegister $data);
    public function login(DTOsLogin $data);
    public function logout(User $user);
    public function changePassword(DTOsChangePassword $data);
    public function getAllUsers(DTOsUserFilter $filters, int $excludeUserId);
}
