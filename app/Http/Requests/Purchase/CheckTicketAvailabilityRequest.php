<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckTicketAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // ✅ Permitir a cualquier usuario verificar disponibilidad
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'event_id' => [
                'required',
                'integer',
                'exists:events,id',
                function ($attribute, $value, $fail) {
                    $event = \App\Models\Event::find($value);
                    if ($event && $event->status !== 'active') {
                        $fail('El evento no está activo.');
                    }
                    if ($event && now()->lt($event->start_date)) {
                        $fail('El evento aún no ha comenzado.');
                    }
                    if ($event && now()->gt($event->end_date)) {
                        $fail('El evento ya ha finalizado.');
                    }
                },
            ],
            'ticket_number' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    // ✅ Validar que sea un número válido
                    if (!is_numeric($value)) {
                        $fail('El número de ticket debe ser numérico.');
                    }
                },
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'event_id.required' => 'El evento es obligatorio.',
            'event_id.integer' => 'El ID del evento debe ser un número entero.',
            'event_id.exists' => 'El evento especificado no existe.',

            'ticket_number.required' => 'El número de ticket es obligatorio.',
            'ticket_number.string' => 'El número de ticket debe ser un texto válido.',
            'ticket_number.max' => 'El número de ticket no puede exceder 50 caracteres.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation errors',
            'data' => $validator->errors()
        ], 422));
    }
}
