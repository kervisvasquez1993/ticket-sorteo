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
                'required',
                'string',
                Rule::in(['USD', 'VES']),
                function ($attribute, $value, $fail) {
                    $paymentMethodId = $this->input('payment_method_id');

                    if (!$paymentMethodId) {
                        return;
                    }

                    $paymentMethod = \App\Models\PaymentMethod::find($paymentMethodId);

                    if (!$paymentMethod) {
                        return;
                    }

                    // Definir qué monedas acepta cada tipo de método de pago
                    $allowedCurrencies = [
                        'pago_movil' => ['VES'],
                        'zelle' => ['USD'],
                        'binance' => ['USD'],
                        // Agrega más tipos según tu sistema
                    ];

                    $methodType = $paymentMethod->type;

                    if (isset($allowedCurrencies[$methodType])) {
                        if (!in_array($value, $allowedCurrencies[$methodType])) {
                            $allowed = implode(' o ', $allowedCurrencies[$methodType]);
                            $methodName = $paymentMethod->name;
                            $fail("El método de pago '{$methodName}' solo acepta pagos en {$allowed}. Por favor, selecciona un precio en la moneda correcta.");
                        }
                    }
                },
            ],
            'payment_reference' => [
                'required',  // ✅ AHORA ES OBLIGATORIO
                'string',
                'max:255',
            ],
            'payment_proof_url' => [
                'required',  // ✅ AHORA ES OBLIGATORIO
                'file',
                'mimes:jpeg,jpg,png,pdf',
                'max:5120',
            ],
            'identificacion' => [
                'required',
                'string',
                'max:20',
            ],

            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
                'required_without:whatsapp',
            ],
            'whatsapp' => [
                'nullable',
                'string',
                'regex:/^\+?[1-9]\d{1,14}$/',
                'max:20',
                'required_without:email',
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
            'currency.required' => 'La moneda es obligatoria.',
            'currency.in' => 'La moneda debe ser USD o VES.',

            'payment_reference.required' => 'El número de referencia es obligatorio.',
            'payment_reference.string' => 'La referencia de pago debe ser texto.',
            'payment_reference.max' => 'La referencia de pago no puede superar los 255 caracteres.',

            'payment_proof_url.required' => 'El comprobante de pago es obligatorio.',
            'payment_proof_url.file' => 'El comprobante debe ser un archivo.',
            'payment_proof_url.mimes' => 'El comprobante debe ser jpg, jpeg, png o pdf.',
            'payment_proof_url.max' => 'El comprobante no debe pesar más de 5MB.',
            'identificacion.required' => 'La cédula de identidad es obligatoria.',
            'identificacion.max' => 'La cédula no puede superar los 20 caracteres.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
            'email.required_without' => 'Debes proporcionar al menos un email o un WhatsApp.',

            'whatsapp.regex' => 'El formato del número de WhatsApp no es válido. Debe incluir el código de país (ejemplo: +584244444161).',
            'whatsapp.max' => 'El número de WhatsApp no puede superar los 20 caracteres.',
            'whatsapp.required_without' => 'Debes proporcionar al menos un WhatsApp o un email.',
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
