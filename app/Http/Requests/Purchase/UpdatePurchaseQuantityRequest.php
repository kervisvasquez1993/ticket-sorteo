<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => [
                'required',
                'string',
                'exists:purchases,transaction_id'
            ],
            'new_quantity' => [
                'required',
                'integer',
                'min:1',
                'max:1000' // Ajusta según tus necesidades
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_id.required' => 'El ID de transacción es requerido',
            'transaction_id.exists' => 'La transacción no existe',
            'new_quantity.required' => 'La nueva cantidad es requerida',
            'new_quantity.integer' => 'La cantidad debe ser un número entero',
            'new_quantity.min' => 'La cantidad mínima es 1',
            'new_quantity.max' => 'La cantidad máxima es 1000',
        ];
    }
}
