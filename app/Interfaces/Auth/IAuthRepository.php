<?php

namespace App\Interfaces\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\Models\Auth;

interface IAuthRepository 
{
    public function getAllAuths();
    public function getAuthById($id): Auth;
    public function createAuth(DTOsAuth $data): Auth;
    public function updateAuth(DTOsAuth $data, Auth $Auth): Auth;
    public function deleteAuth(Auth $Auth): Auth;
}
