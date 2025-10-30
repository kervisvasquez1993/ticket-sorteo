<?php

namespace App\Repository\Auth;

use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;
use App\DTOs\Auth\DTOsUserFilter;
use App\Interfaces\Auth\IAuthRepository;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;


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
            'name' => $registerDTO->getName(),
            'email' => $registerDTO->getEmail(),
            'password' => Hash::make($registerDTO->getPassword()),
            'role' => $registerDTO->getRole(),
        ]);
    }

    public function revokeToken(User $user): bool
    {
        $token = $user->token();

        if ($token) {
            $token->revoke();
            return true;
        }

        return false;
    }

    // ✅ Nuevo método
    public function updatePassword(int $userId, string $password): bool
    {
        try {
            $user = User::findOrFail($userId);

            $user->password = Hash::make($password);
            $saved = $user->save();

            if ($saved) {
                Log::info('Contraseña actualizada para usuario:', ['user_id' => $userId]);
            }

            return $saved;
        } catch (\Exception $e) {
            Log::error('Error al actualizar contraseña:', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    public function getAllUsers(DTOsUserFilter $filters, int $authenticatedUserId): LengthAwarePaginator
    {
        try {
            $query = User::query();

            // **CAMBIO PRINCIPAL**: Ordenar poniendo primero al usuario autenticado
            $query->orderByRaw("CASE WHEN id = ? THEN 0 ELSE 1 END", [$authenticatedUserId]);

            // Aplicar filtros
            $this->applyFilters($query, $filters);

            // Ordenamiento secundario (después del usuario autenticado)
            if ($filters->isValidSortField() && $filters->isValidSortOrder()) {
                $query->orderBy($filters->getSortBy(), $filters->getSortOrder());
            }

            Log::info('Listando usuarios', [
                'filters' => $filters->toArray(),
                'authenticated_user_first' => $authenticatedUserId
            ]);

            return $query->paginate(
                $filters->getPerPage(),
                ['id', 'name', 'email', 'role', 'created_at'],
                'page',
                $filters->getPage()
            );
        } catch (\Exception $e) {
            Log::error('Error al listar usuarios:', [
                'error' => $e->getMessage(),
                'filters' => $filters->toArray()
            ]);
            throw $e;
        }
    }

    private function applyFilters($query, DTOsUserFilter $filters): void
    {
        $filtersData = $filters->getFiltersForQuery();

        // Filtrar por email exacto
        if (!empty($filtersData['email'])) {
            $query->where('email', $filtersData['email']);
        }

        // Filtrar por nombre (búsqueda parcial)
        if (!empty($filtersData['name'])) {
            $query->where('name', 'like', '%' . $filtersData['name'] . '%');
        }

        // Filtrar por rol
        if (!empty($filtersData['role'])) {
            $query->where('role', $filtersData['role']);
        }

        // Búsqueda general (nombre o email)
        if (!empty($filtersData['search'])) {
            $query->where(function ($q) use ($filtersData) {
                $q->where('name', 'like', '%' . $filtersData['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filtersData['search'] . '%');
            });
        }
    }
}
