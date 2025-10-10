<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateSinglePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
                function ($attribute, $value, $fail) {
                    $event = \App\Models\Event::find($value);
                    if ($event && $event->status !== 'active') {
                        $fail('El evento no está activo para compras.');
                    }
                    if ($event && now()->lt($event->start_date)) {
                        $fail('El evento aún no ha comenzado.');
                    }
                    if ($event && now()->gt($event->end_date)) {
                        $fail('El evento ya ha finalizado.');
                    }
                },
            ],
            'event_price_id' => [
                'required',
                'integer',
                'exists:event_prices,id',
                function ($attribute, $value, $fail) {
                    $eventPrice = \App\Models\EventPrice::find($value);
                    if ($eventPrice && !$eventPrice->is_active) {
                        $fail('El precio seleccionado no está disponible.');
                    }
                    if ($eventPrice && $this->input('event_id') && $eventPrice->event_id != $this->input('event_id')) {
                        $fail('El precio no corresponde al evento seleccionado.');
                    }
                },
            ],
            'payment_method_id' => [
                'required',
                'integer',
                'exists:payment_methods,id',
                function ($attribute, $value, $fail) {
                    $paymentMethod = \App\Models\PaymentMethod::find($value);
                    if ($paymentMethod && !$paymentMethod->is_active) {
                        $fail('El método de pago seleccionado no está disponible.');
                    }
                },
            ],
            'ticket_numbers' => [
                'required',
                'array',
                'min:1',
                'max:10',
                // ✅ Validación consolidada a nivel de array
                function ($attribute, $value, $fail) {
                    if (!is_array($value)) {
                        return;
                    }

                    $eventId = $this->input('event_id');
                    if (!$eventId) {
                        return;
                    }

                    $event = \App\Models\Event::find($eventId);
                    if (!$event) {
                        return;
                    }

                    $errors = [];
                    $outOfRange = [];
                    $alreadyReserved = [];

                    // Verificar números duplicados en la misma solicitud
                    $duplicates = array_diff_assoc($value, array_unique($value));
                    if (!empty($duplicates)) {
                        $fail('No puedes seleccionar el mismo número dos veces.');
                        return;
                    }

                    foreach ($value as $ticketNumber) {
                        // Verificar que sea un número entero
                        if (!is_int($ticketNumber)) {
                            $fail('Todos los números de ticket deben ser números enteros.');
                            return;
                        }

                        // Verificar rango
                        if ($ticketNumber < $event->start_number || $ticketNumber > $event->end_number) {
                            $outOfRange[] = $ticketNumber;
                            continue;
                        }

                        // Verificar si está reservado
                        $isUsed = \App\Models\Purchase::where('event_id', $eventId)
                            ->where('ticket_number', $ticketNumber)
                            ->exists();

                        if ($isUsed) {
                            $alreadyReserved[] = $ticketNumber;
                        }
                    }

                    // Reportar errores consolidados
                    if (!empty($outOfRange)) {
                        $numbers = implode(', ', $outOfRange);
                        $fail("Los siguientes números están fuera del rango del evento ({$event->start_number} - {$event->end_number}): {$numbers}");
                        return;
                    }

                    if (!empty($alreadyReserved)) {
                        $numbers = implode(', ', $alreadyReserved);
                        $fail("Los siguientes números ya están reservados: {$numbers}. Por favor, selecciona otros números.");
                        return;
                    }
                },
            ],
            'ticket_numbers.*' => [
                'required',
                'integer',
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
                'required',
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
            'ticket_numbers.max' => 'No puedes seleccionar más de 10 números a la vez.',
            'ticket_numbers.*.required' => 'Todos los números de ticket son obligatorios.',
            'ticket_numbers.*.integer' => 'Cada número de ticket debe ser un número entero.',
            'payment_proof_url.required' => 'El comprobante de pago es obligatorio.',
            'payment_proof_url.file' => 'El comprobante debe ser un archivo.',
            'payment_proof_url.mimes' => 'El comprobante debe ser jpg, jpeg, png o pdf.',
            'payment_proof_url.max' => 'El comprobante no debe pesar más de 5MB.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'whatsapp.required' => 'El número de WhatsApp es obligatorio.',
            'whatsapp.regex' => 'El formato del número de WhatsApp no es válido. Debe incluir el código de país (ejemplo: +584244444161).',
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
            'message' => 'You are not authorized to perform this action.',
        ], 403));
    }
}
