<?php

namespace App\Http\Requests\Event;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class CreateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo admin puede crear eventos
        return Auth::check() && Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_number' => 'required|integer|min:0',
            'end_number' => 'required|integer|gt:start_number',
            'price' => 'required|numeric|min:0',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|in:active,completed,cancelled',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del evento es obligatorio',
            'end_number.gt' => 'El número final debe ser mayor que el número inicial',
            'price.min' => 'El precio debe ser mayor o igual a 0',
            'start_date.after_or_equal' => 'La fecha de inicio debe ser hoy o posterior',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(
            [
                'message' => 'Errores de validación',
                'data' => $validator->errors()
            ],
            422
        ));
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No tienes permisos para crear eventos. Solo administradores.',
        ], 403));
    }
}
