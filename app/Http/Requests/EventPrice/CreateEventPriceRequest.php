<?php

namespace App\Http\Requests\EventPrice;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateEventPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Verificar que el usuario esté autenticado y sea admin
        return Auth::check() && Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'event_id' => [
                'required',
                'integer',
                'exists:events,id'
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'regex:/^\d+(\.\d{1,2})?$/' // Máximo 2 decimales
            ],
            'currency' => [
                'required',
                'string',
                'size:3', // ISO 4217 (USD, EUR, etc.)
                'regex:/^[A-Z]{3}$/',
                // Validación única: no puede haber dos precios con la misma moneda para el mismo evento
                Rule::unique('event_prices')->where(function ($query) {
                    return $query->where('event_id', $this->event_id)
                        ->where('currency', $this->currency);
                })
            ],
            'is_default' => [
                'sometimes',
                'boolean'
            ],
            'is_active' => [
                'sometimes',
                'boolean'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'event_id.required' => 'El ID del evento es obligatorio',
            'event_id.exists' => 'El evento especificado no existe',
            'amount.required' => 'El monto es obligatorio',
            'amount.min' => 'El monto debe ser mayor a 0',
            'amount.regex' => 'El monto debe tener máximo 2 decimales',
            'currency.required' => 'La moneda es obligatoria',
            'currency.size' => 'La moneda debe tener 3 caracteres (ej: USD, EUR)',
            'currency.regex' => 'La moneda debe estar en mayúsculas',
            'currency.unique' => 'Ya existe un precio para esta rifa con la misma moneda',
            'is_default.boolean' => 'El campo is_default debe ser verdadero o falso',
            'is_active.boolean' => 'El campo is_active debe ser verdadero o falso'
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Errores de validación',
            'errors' => $validator->errors()
        ], 422));
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'No tienes permisos de administrador para realizar esta acción',
        ], 403));
    }
}
