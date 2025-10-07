<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateAdminPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }

    protected function prepareForValidation()
    {
        if ($this->has('ticket_numbers') && is_string($this->ticket_numbers)) {
            $this->merge([
                'ticket_numbers' => json_decode($this->ticket_numbers, true)
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'event_id' => [
                'required',
                'integer',
                'exists:events,id',
            ],
            'event_price_id' => [
                'required',
                'integer',
                'exists:event_prices,id',
                function ($attribute, $value, $fail) {
                    $eventPrice = \App\Models\EventPrice::find($value);
                    if ($eventPrice && $this->input('event_id') && $eventPrice->event_id != $this->input('event_id')) {
                        $fail('El precio no corresponde al evento seleccionado.');
                    }
                },
            ],
            'payment_method_id' => [
                'required',
                'integer',
                'exists:payment_methods,id',
            ],
            'ticket_numbers' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],
            'ticket_numbers.*' => [
                'required',
                'integer',
                'distinct',
                function ($attribute, $value, $fail) {
                    if ($this->input('event_id')) {
                        $event = \App\Models\Event::find($this->input('event_id'));

                        if ($event && ($value < $event->start_number || $value > $event->end_number)) {
                            $fail("El número {$value} está fuera del rango del evento ({$event->start_number} - {$event->end_number}).");
                        }

                        $isUsed = \App\Models\Purchase::where('event_id', $this->input('event_id'))
                            ->where('ticket_number', $value)
                            ->exists();

                        if ($isUsed) {
                            $fail("El número {$value} ya está reservado.");
                        }
                    }
                },
            ],
            'currency' => [
                'nullable',
                'string',
                Rule::in(['USD', 'BS']),
            ],
            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'payment_proof_url' => [
                'nullable', // ✅ OPCIONAL para admin
                'file',
                'mimes:jpeg,jpg,png,pdf',
                'max:5120',
            ],
            'email' => [
                'required',
                'email:rfc,dns',
                'max:255',
            ],
            'whatsapp' => [
                'required',
                'string',
                'regex:/^\+?[1-9]\d{1,14}$/',
                'max:20',
            ],
            'auto_approve' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'event_id.required' => 'El evento es obligatorio.',
            'event_id.exists' => 'El evento seleccionado no existe.',
            'event_price_id.required' => 'El precio es obligatorio.',
            'payment_method_id.required' => 'El método de pago es obligatorio.',
            'ticket_numbers.required' => 'Debes seleccionar al menos un número de ticket.',
            'ticket_numbers.array' => 'Los números de ticket deben ser un array válido.',
            'ticket_numbers.min' => 'Debes seleccionar al menos un número de ticket.',
            'ticket_numbers.max' => 'No puedes seleccionar más de 50 números a la vez.',
            'ticket_numbers.*.integer' => 'Cada número de ticket debe ser un número entero.',
            'ticket_numbers.*.distinct' => 'No puedes seleccionar el mismo número dos veces.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'whatsapp.required' => 'El número de WhatsApp es obligatorio.',
            'whatsapp.regex' => 'El formato del número de WhatsApp no es válido.',
            'payment_proof_url.mimes' => 'El comprobante debe ser jpg, jpeg, png o pdf.',
            'payment_proof_url.max' => 'El comprobante no debe pesar más de 5MB.',
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
