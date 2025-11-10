<?php
// app/Http/Requests/Purchase/AddTicketsToTransactionRequest.php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class AddTicketsToTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => 'nullable|integer|min:1|max:1000',
            'ticket_numbers' => 'nullable|array|min:1|max:100',
            'ticket_numbers.*' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.integer' => 'La cantidad debe ser un número entero',
            'quantity.min' => 'La cantidad mínima es 1',
            'quantity.max' => 'La cantidad máxima es 1000 tickets',
            'ticket_numbers.array' => 'Los números de ticket deben ser un array',
            'ticket_numbers.*.required' => 'Cada número de ticket es requerido',
        ];
    }

    /**
     * Validación adicional: solo quantity O ticket_numbers
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasQuantity = $this->has('quantity') && !is_null($this->input('quantity'));
            $hasTicketNumbers = $this->has('ticket_numbers') && !empty($this->input('ticket_numbers'));

            if ($hasQuantity && $hasTicketNumbers) {
                $validator->errors()->add(
                    'validation',
                    'Solo puedes especificar "quantity" para números aleatorios O "ticket_numbers" para números específicos, no ambos.'
                );
            }

            if (!$hasQuantity && !$hasTicketNumbers) {
                $validator->errors()->add(
                    'validation',
                    'Debes especificar "quantity" o "ticket_numbers"'
                );
            }
        });
    }
}
