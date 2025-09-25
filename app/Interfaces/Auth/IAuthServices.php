<?php

namespace App\Interfaces\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;

interface IAuthServices
{
    public function register(DTOsRegister $data);
    public function login(DTOsLogin $data);
}
