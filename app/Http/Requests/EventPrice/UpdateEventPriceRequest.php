<?php

namespace App\Http\Requests\EventPrice;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateEventPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        $eventPriceId = $this->route('id');

        return [
            'amount' => [
                'required',  // 👈 Solo este es required
                'numeric',
                'min:0.01',
                'regex:/^\d+(\.\d{1,2})?$/'
            ],
            // Los demás son opcionales
            'event_id' => [
                'sometimes',
                'integer',
                'exists:events,id'
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::unique('event_prices')->where(function ($query) {
                    return $query->where('event_id', $this->event_id ?? $this->getEventIdFromDb())
                                 ->where('currency', $this->currency)
                                 ->whereNull('deleted_at'); // Si usas soft deletes
                })->ignore($eventPriceId)
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
            'amount.required' => 'El monto es obligatorio',
            'amount.min' => 'El monto debe ser mayor a 0',
            'amount.regex' => 'El monto debe tener máximo 2 decimales',
            'event_id.exists' => 'El evento especificado no existe',
            'currency.size' => 'La moneda debe tener 3 caracteres',
            'currency.regex' => 'La moneda debe estar en mayúsculas',
            'currency.unique' => 'Ya existe un precio para esta rifa con la misma moneda',
            'is_default.boolean' => 'El campo is_default debe ser verdadero o falso',
            'is_active.boolean' => 'El campo is_active debe ser verdadero o falso'
        ];
    }

    /**
     * Obtener el event_id del registro actual si no viene en el request
     */
    private function getEventIdFromDb()
    {
        $eventPriceId = $this->route('id');
        $eventPrice = \App\Models\EventPrice::find($eventPriceId);
        return $eventPrice ? $eventPrice->event_id : null;
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
