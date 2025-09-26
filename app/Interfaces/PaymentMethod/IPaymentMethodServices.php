<?php

namespace App\Interfaces\PaymentMethod;

use App\DTOs\PaymentMethod\DTOsPaymentMethod;

interface IPaymentMethodServices
{
    public function getAllPaymentMethods();
    public function getActivePaymentMethods();
    public function getPaymentMethodById($id);
    public function createPaymentMethod(DTOsPaymentMethod $data);
    public function updatePaymentMethod(DTOsPaymentMethod $data, $id);
    public function deletePaymentMethod($id);
    public function toggleActive($id);
}
