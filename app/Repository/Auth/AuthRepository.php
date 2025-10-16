<?php

namespace App\Repository\Auth;

use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;
use App\Interfaces\Auth\IAuthRepository;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthRepository implements IAuthRepository
{
    public function login(DTOsLogin $loginDTO): array
    {
        try {
            $credentials = $loginDTO->credentials();

            Log::info('Intentando login con:', ['email' => $credentials['email']]);

            if (!Auth::attempt($credentials)) {
                Log::warning('Login fallido. Credenciales inválidas.', $credentials);

                return [
                    'success' => false,
                    'message' => 'Invalid credentials'
                ];
            }

            $user = Auth::user();

            Log::info('Login exitoso para usuario:', ['id' => $user->id, 'email' => $user->email]);

            return [
                'success' => true,
                'user' => $user
            ];
        } catch (\Exception $e) {
            Log::error('Error en el login:', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Unexpected error during login',
                'error' => $e->getMessage()
            ];
        }
    }

    public function createAccessToken(User $user): array
    {
        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;
        $token->save();

        return [
            'access_token' => $tokenResult->accessToken
        ];
    }

    public function createUser(DTOsRegister $registerDTO): User
    {
        return User::create([
            'username' => $registerDTO->getUsername(),
            'email' => $registerDTO->getEmail(),
            'password' => Hash::make($registerDTO->getPassword()),
            'role' => $registerDTO->getRole(),
        ]);
    }

    public function revokeToken(User $user): bool
    {
        // Revoca el token actual del usuario
        $token = $user->token();

        if ($token) {
            $token->revoke();
            return true;
        }

        return false;
    }
}
