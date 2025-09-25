<?php

namespace App\Services\Auth;

use App\DTOs\Auth\DTOsAuth;
use App\Interfaces\Auth\IAuthServices;
use App\Interfaces\Auth\IAuthRepository;
use Exception;

class AuthServices implements IAuthServices 
{
    protected IAuthRepository $AuthRepository;
    
    public function __construct(IAuthRepository $AuthRepositoryInterface)
    {
        $this->AuthRepository = $AuthRepositoryInterface;
    }
    
    public function getAllAuths()
    {
        try {
            $results = $this->AuthRepository->getAllAuths();
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
    
    public function getAuthById($id)
    {
        try {
            $results = $this->AuthRepository->getAuthById($id);
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
    
    public function createAuth(DTOsAuth $data)
    {
        try {
            $results = $this->AuthRepository->createAuth($data);
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
    
    public function updateAuth(DTOsAuth $data, $id)
    {
        try {
            $Auth = $this->AuthRepository->getAuthById($id);
            $results = $this->AuthRepository->updateAuth($data, $Auth);
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
    
    public function deleteAuth($id)
    {
        try {
            $Auth = $this->AuthRepository->getAuthById($id);
            $results = $this->AuthRepository->deleteAuth($Auth);
            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}
