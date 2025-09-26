<?php

namespace App\Interfaces\PaymentMethod;

use App\DTOs\PaymentMethod\DTOsPaymentMethod;
use App\Models\PaymentMethod;

interface IPaymentMethodRepository
{
    public function getAllPaymentMethods();
    public function getActivePaymentMethods();
    public function getPaymentMethodById($id): PaymentMethod;
    public function createPaymentMethod(DTOsPaymentMethod $data): PaymentMethod;
    public function updatePaymentMethod(DTOsPaymentMethod $data, PaymentMethod $paymentMethod): PaymentMethod;
    public function deletePaymentMethod(PaymentMethod $paymentMethod): PaymentMethod;
    public function toggleActive(PaymentMethod $paymentMethod): PaymentMethod;
}
