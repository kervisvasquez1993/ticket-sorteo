<?php

namespace App\Http\Requests\EventPrice;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use App\Models\EventPrice;

class SetDefaultEventPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Verificar que el usuario esté autenticado y sea admin
        return Auth::check() && Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            // El ID viene por la ruta, pero podemos validar que exista
        ];
    }

    /**
     * Validaciones adicionales después de las reglas básicas
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $eventPriceId = $this->route('id');

            // Verificar que el EventPrice exista
            $eventPrice = EventPrice::find($eventPriceId);

            if (!$eventPrice) {
                $validator->errors()->add(
                    'id',
                    'El precio de rifa especificado no existe'
                );
                return;
            }

            // Verificar que el precio esté activo
            if (!$eventPrice->is_active) {
                $validator->errors()->add(
                    'is_active',
                    'No se puede establecer como predeterminado un precio inactivo'
                );
            }

            // Verificar que no sea ya el precio por defecto
            if ($eventPrice->is_default) {
                $validator->errors()->add(
                    'is_default',
                    'Este precio ya está establecido como predeterminado'
                );
            }

            // Verificar que el evento asociado exista y esté activo
            if ($eventPrice->event) {
                if ($eventPrice->event->status !== 'active') {
                    $validator->errors()->add(
                        'event',
                        'No se puede modificar el precio predeterminado de un evento inactivo'
                    );
                }
            } else {
                $validator->errors()->add(
                    'event',
                    'El evento asociado a este precio no existe'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'id.exists' => 'El precio de rifa especificado no existe',
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
