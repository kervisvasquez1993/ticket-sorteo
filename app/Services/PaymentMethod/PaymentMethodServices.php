<?php

namespace App\Services\PaymentMethod;

use App\DTOs\PaymentMethod\DTOsPaymentMethod;
use App\Interfaces\PaymentMethod\IPaymentMethodServices;
use App\Interfaces\PaymentMethod\IPaymentMethodRepository;
use Exception;

class PaymentMethodServices implements IPaymentMethodServices
{
    protected IPaymentMethodRepository $paymentMethodRepository;

    public function __construct(IPaymentMethodRepository $paymentMethodRepositoryInterface)
    {
        $this->paymentMethodRepository = $paymentMethodRepositoryInterface;
    }

    public function getAllPaymentMethods()
    {
        try {
            $results = $this->paymentMethodRepository->getAllPaymentMethods();
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Métodos de pago obtenidos exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function getActivePaymentMethods()
    {
        try {
            $results = $this->paymentMethodRepository->getActivePaymentMethods();
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Métodos de pago activos obtenidos exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function getPaymentMethodById($id)
    {
        try {
            $results = $this->paymentMethodRepository->getPaymentMethodById($id);
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

    public function createPaymentMethod(DTOsPaymentMethod $data)
    {
        try {
            $results = $this->paymentMethodRepository->createPaymentMethod($data);
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Método de pago creado exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function updatePaymentMethod(DTOsPaymentMethod $data, $id)
    {
        try {
            $paymentMethod = $this->paymentMethodRepository->getPaymentMethodById($id);
            $results = $this->paymentMethodRepository->updatePaymentMethod($data, $paymentMethod);
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Método de pago actualizado exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function deletePaymentMethod($id)
    {
        try {
            $paymentMethod = $this->paymentMethodRepository->getPaymentMethodById($id);
            $results = $this->paymentMethodRepository->deletePaymentMethod($paymentMethod);
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Método de pago eliminado exitosamente'
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }

    public function toggleActive($id)
    {
        try {
            $paymentMethod = $this->paymentMethodRepository->getPaymentMethodById($id);
            $results = $this->paymentMethodRepository->toggleActive($paymentMethod);

            $status = $results->is_active ? 'activado' : 'desactivado';

            return [
                'success' => true,
                'data' => $results,
                'message' => "Método de pago {$status} exitosamente"
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}
