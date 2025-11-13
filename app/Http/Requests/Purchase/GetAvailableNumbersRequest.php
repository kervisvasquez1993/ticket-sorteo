<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailableNumbersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:50',
            'min_number' => 'nullable|integer|min:0',
            'max_number' => 'nullable|integer|min:0',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'La búsqueda debe ser un texto válido',
            'search.max' => 'La búsqueda no puede exceder 50 caracteres',
            'min_number.integer' => 'El número mínimo debe ser un entero',
            'max_number.integer' => 'El número máximo debe ser un entero',
            'page.integer' => 'La página debe ser un número entero',
            'page.min' => 'La página debe ser mayor a 0',
            'per_page.integer' => 'Los resultados por página deben ser un número entero',
            'per_page.max' => 'No puedes solicitar más de 100 resultados por página',
        ];
    }
}
