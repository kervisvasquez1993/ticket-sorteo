<?php

namespace App\Http\Requests\Event;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class SelectWinnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'winner_number' => [
                'required',
                'integer',
                'min:0',
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'winner_number.required' => 'El número ganador es obligatorio',
            'winner_number.integer' => 'El número ganador debe ser un número entero',
            'winner_number.min' => 'El número ganador debe ser mayor o igual a 0',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Errores de validación',
            'data' => $validator->errors()
        ], 422));
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No tienes permisos para seleccionar ganador. Solo administradores.',
        ], 403));
    }
}
