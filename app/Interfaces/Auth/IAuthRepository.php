<?php

namespace App\Interfaces\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;
use App\Models\Auth;
use App\Models\User;

interface IAuthRepository
{
    public function login(DTOsLogin $loginDTO);
    public function createAccessToken(User $user);
    public function createUser(DTOsRegister $registerDTO);
}
