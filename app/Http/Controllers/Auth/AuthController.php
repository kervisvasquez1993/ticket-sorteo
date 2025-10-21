<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\DTOs\Auth\DTOsChangePassword;
use App\DTOs\Auth\DTOsLogin;
use App\DTOs\Auth\DTOsRegister;
use App\DTOs\Auth\DTOsUserFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\GetUsersRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;


use App\Interfaces\Auth\IAuthServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    protected IAuthServices $AuthServices;

    public function __construct(IAuthServices $AuthServicesInterface)
    {
        $this->AuthServices = $AuthServicesInterface;
    }
    /**
     * Listar todos los usuarios del sistema (excluyendo el usuario autenticado)
     * Solo accesible para administradores
     *
     * Ejemplos de uso:
     *
     * Listar todos los usuarios (sin filtros):
     * GET /api/users
     * Authorization: Bearer {admin_token}
     *
     * Filtrar por email:
     * GET /api/users?email=juan@ejemplo.com
     * Authorization: Bearer {admin_token}
     *
     * Filtrar por rol:
     * GET /api/users?role=customer
     * Authorization: Bearer {admin_token}
     *
     * Búsqueda general (nombre o email):
     * GET /api/users?search=juan
     * Authorization: Bearer {admin_token}
     *
     * Combinando filtros y paginación:
     * GET /api/users?role=customer&sort_by=created_at&sort_order=desc&page=2&per_page=20
     * Authorization: Bearer {admin_token}
     *
     * @param GetUsersRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(GetUsersRequest $request)
    {
        $filters = DTOsUserFilter::fromRequest($request);
        $user = Auth::user()->id;

        $result = $this->AuthServices->getAllUsers(
            $filters,
            $user
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message']
            ], 422);
        }

        return response()->json($result['data'], 200);
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
    public function logout(Request $request)
    {
        $result = $this->AuthServices->logout($request->user());

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message']
            ], 500);
        }

        return response()->json([
            'message' => 'Successfully logged out'
        ], 200);
    }
    public function test()
    {
        return response()->json([
            'message' => "test"
        ], 200);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $result = $this->AuthServices->changePassword(
            DTOsChangePassword::fromRequest($request)
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message']
            ], 422);
        }

        return response()->json([
            'message' => $result['message']
        ], 200);
    }
}
