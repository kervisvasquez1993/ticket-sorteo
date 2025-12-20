<?php

namespace App\Http\Requests\EventBlacklist;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use App\Models\Purchase;

class CreateEventBlacklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'event_id' => [
                'required',
                'integer',
                'exists:events,id',
            ],
            'identificacion' => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) {
                    $normalized = Purchase::normalizeIdentificacion($value);
                    $eventId = $this->input('event_id');

                    // Verificar si ya existe en lista negra
                    $exists = \App\Models\EventBlacklistedIdentification::where('event_id', $eventId)
                        ->where('identificacion', $normalized)
                        ->exists();

                    if ($exists) {
                        $fail('Esta identificación ya está en la lista negra de este evento.');
                    }
                },
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'event_id.required' => 'El evento es obligatorio.',
            'event_id.exists' => 'El evento seleccionado no existe.',
            'identificacion.required' => 'La identificación es obligatoria.',
            'identificacion.max' => 'La identificación no puede superar los 20 caracteres.',
            'reason.max' => 'La razón no puede superar los 500 caracteres.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ], 422));
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No tienes permisos para realizar esta acción.',
        ], 403));
    }
}
