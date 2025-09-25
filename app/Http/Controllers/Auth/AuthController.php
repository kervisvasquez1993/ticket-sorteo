<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CreateAuthRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateAuthRequest;
use App\Interfaces\Auth\IAuthServices;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected IAuthServices $AuthServices;

    public function __construct(IAuthServices $AuthServicesInterface)
    {
        $this->AuthServices = $AuthServicesInterface;
    }

    public function register(RegisterRequest $request)
    {
        $result = $this->AuthServices->register(DTOsRegister::fromRequest($request));
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 201);
    }

    public function login(LoginRequest $request)
    {

        $result = $this->AuthServices->login(DTOsLogin::fromRequest($request));

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message']
            ], 401);
        }
        return response()->json($result['data']);
    }
}
