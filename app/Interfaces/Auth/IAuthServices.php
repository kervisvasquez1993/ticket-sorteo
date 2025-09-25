<?php

namespace App\Interfaces\Auth;

use App\DTOs\Auth\DTOsAuth;

interface IAuthServices 
{
    public function getAllAuths();
    public function getAuthById($id);
    public function createAuth(DTOsAuth $data);
    public function updateAuth(DTOsAuth $data, $id);
    public function deleteAuth($id);
}
