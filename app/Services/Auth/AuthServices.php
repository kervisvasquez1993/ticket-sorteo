<?php

namespace App\Services\Auth;

use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;
use App\DTOs\Auth\DTOsChangePassword; // ✅ Agregar
use App\DTOs\Auth\DTOsUserFilter;
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
                'data' =>  $user
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
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

    // ✅ Nuevo método
    public function changePassword(DTOsChangePassword $changePasswordDTO)
    {
        try {
            $updated = $this->AuthRepository->updatePassword(
                $changePasswordDTO->getUserId(),
                $changePasswordDTO->getPassword()
            );

            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'No se pudo actualizar la contraseña'
                ];
            }

            return [
                'success' => true,
                'message' => 'Contraseña actualizada exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
    public function getAllUsers(DTOsUserFilter $filters, int $excludeUserId)
    {
        try {
            // Validar filtros
            if (!$filters->isValidSortField()) {
                return [
                    'success' => false,
                    'message' => 'Campo de ordenamiento no válido'
                ];
            }

            if (!$filters->isValidSortOrder()) {
                return [
                    'success' => false,
                    'message' => 'Orden de clasificación no válido'
                ];
            }

            if (!$filters->isValidRole()) {
                return [
                    'success' => false,
                    'message' => 'Rol no válido'
                ];
            }

            $users = $this->AuthRepository->getAllUsers($filters, $excludeUserId);

            return [
                'success' => true,
                'data' => $users
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}
