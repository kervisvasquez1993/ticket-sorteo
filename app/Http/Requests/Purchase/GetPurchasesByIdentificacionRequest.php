<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GetPurchasesByIdentificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sin autenticación requerida
    }

    public function rules(): array
    {
        return [
            'identificacion' => [
                'required',
                'string',
                'max:20',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'identificacion.required' => 'La cédula de identidad es obligatoria.',
            'identificacion.regex' => 'La cédula debe tener el formato: V-12345678 o E-12345678',
            'identificacion.max' => 'La cédula no puede superar los 20 caracteres.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(
            [
                'message' => 'Error de validación',
                'data' => $validator->errors()
            ],
            422
        ));
    }

    protected function prepareForValidation()
    {
        // Normalizar la identificación desde la ruta
        // Convertir a mayúsculas y normalizar el formato
        $identificacion = $this->route('identificacion');

        // Asegurar que tenga el guion si no lo tiene
        if ($identificacion && !str_contains($identificacion, '-')) {
            $identificacion = preg_replace('/^([VE])(\d+)$/i', '$1-$2', $identificacion);
        }

        $this->merge([
            'identificacion' => strtoupper($identificacion)
        ]);
    }
}
