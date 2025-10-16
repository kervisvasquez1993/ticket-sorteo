<?php

namespace App\Services\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;
use App\Interfaces\Auth\IAuthServices;
use App\Interfaces\Auth\IAuthRepository;
use App\Models\User;
use Exception;

class AuthServices implements IAuthServices
{
    protected IAuthRepository $AuthRepository;

    public function __construct(IAuthRepository $AuthRepositoryInterface)
    {
        $this->AuthRepository = $AuthRepositoryInterface;
    }

    public function login(DTOsLogin $loginDTO)
    {
        try {
            $authResult = $this->AuthRepository->login($loginDTO);

            if (!$authResult['success']) {
                return [
                    'success' => false,
                    'message' => 'The provided data is incorrect'
                ];
            }
            $user = $authResult['user'];
            $tokenResult = $this->AuthRepository->createAccessToken($user);

            return [
                'success' => true,
                'data' => [
                    'access_token' => $tokenResult['access_token'],
                    'data' => $user
                ]
            ];
        } catch (Exception $ex) {
            return [
                'success' => false,
                'message' => $ex->getMessage()
            ];
        }
    }

    public function register(DTOsRegister $registerDTO)
    {
        try {
            $user = $this->AuthRepository->createUser($registerDTO);
            return [
                'success' => true,
                'data' =>  $user,
                'error' => "test"
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'code' => "442"
            ];
        }
    }
    public function logout(User $user)
    {
        try {
            $this->AuthRepository->revokeToken($user);

            return [
                'success' => true,
                'message' => 'Token revoked successfully'
            ];
        } catch (Exception $ex) {
            return [
                'success' => false,
                'message' => $ex->getMessage()
            ];
        }
    }
}
