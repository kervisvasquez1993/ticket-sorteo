<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CreateAuthRequest;
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
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $result = $this->AuthServices->getAllAuths();
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateAuthRequest $request)
    {
        $result = $this->AuthServices->createAuth(DTOsAuth::fromRequest($request));
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 201);
    }
    
    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $result = $this->AuthServices->getAuthById($id);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
    
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAuthRequest $request, string $id)
    {
        $result = $this->AuthServices->updateAuth(DTOsAuth::fromUpdateRequest($request), $id);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $result = $this->AuthServices->deleteAuth($id);
        if (!$result['success']) {
            return response()->json([
                'error' => $result['message']
            ], 422);
        }
        return response()->json($result['data'], 200);
    }
}
