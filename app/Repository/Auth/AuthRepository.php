<?php

namespace App\Repository\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\Interfaces\Auth\IAuthRepository;
use App\Models\Auth;

class AuthRepository implements IAuthRepository 
{
    public function getAllAuths()
    {
        $Auths = Auth::all();
        return $Auths;
    }
    
    public function getAuthById($id): Auth
    {
        $Auth = Auth::where('id', $id)->first();
        if (!$Auth) {
            throw new \Exception("No results found for Auth with ID {$id}");
        }
        return $Auth;
    }
    
    public function createAuth(DTOsAuth $data): Auth
    {
        $result = Auth::create($data->toArray());
        return $result;
    }
    
    public function updateAuth(DTOsAuth $data, Auth $Auth): Auth
    {
        $Auth->update($data->toArray());
        return $Auth;
    }
    
    public function deleteAuth(Auth $Auth): Auth
    {
        $Auth->delete();
        return $Auth;
    }
}
