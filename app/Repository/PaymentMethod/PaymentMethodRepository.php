<?php

namespace App\Repository\PaymentMethod;

use App\DTOs\PaymentMethod\DTOsPaymentMethod;
use App\Interfaces\PaymentMethod\IPaymentMethodRepository;
use App\Models\PaymentMethod;

class PaymentMethodRepository implements IPaymentMethodRepository
{
    public function getAllPaymentMethods()
    {
        return PaymentMethod::ordered()->get();
    }

    public function getActivePaymentMethods()
    {
        return PaymentMethod::active()->ordered()->get();
    }

    public function getPaymentMethodById($id): PaymentMethod
    {
        $paymentMethod = PaymentMethod::find($id);

        if (!$paymentMethod) {
            throw new \Exception("Método de pago no encontrado con ID {$id}");
        }

        return $paymentMethod;
    }

    public function createPaymentMethod(DTOsPaymentMethod $data): PaymentMethod
    {
        return PaymentMethod::create($data->toArray());
    }

    public function updatePaymentMethod(DTOsPaymentMethod $data, PaymentMethod $paymentMethod): PaymentMethod
    {
        $paymentMethod->update($data->toArray());
        return $paymentMethod->fresh();
    }

    public function deletePaymentMethod(PaymentMethod $paymentMethod): PaymentMethod
    {
        $paymentMethod->delete();
        return $paymentMethod;
    }

    public function toggleActive(PaymentMethod $paymentMethod): PaymentMethod
    {
        $paymentMethod->update([
            'is_active' => !$paymentMethod->is_active
        ]);
        return $paymentMethod->fresh();
    }
}
