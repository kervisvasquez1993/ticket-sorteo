<?php

namespace App\Interfaces\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;
use App\Models\User;

interface IAuthServices
{
    public function register(DTOsRegister $data);
    public function login(DTOsLogin $data);
    public function logout(User $user);
}
