<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateAdminRandomPurchaseRequest extends FormRequest
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
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:100',
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
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.min' => 'Debe comprar al menos 1 ticket.',
            'quantity.max' => 'Solo puede comprar hasta 100 tickets por vez.',
            'currency.required' => 'La moneda es obligatoria.',
            'currency.in' => 'La moneda debe ser USD o VES.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
            'whatsapp.required' => 'El número de WhatsApp es obligatorio.',
            'whatsapp.regex' => 'El formato del número de WhatsApp no es válido. Debe incluir el código de país (ejemplo: +584244444161).',
            'whatsapp.max' => 'El número de WhatsApp no puede superar los 20 caracteres.',
            'payment_proof_url.mimes' => 'El comprobante debe ser jpg, jpeg, png o pdf.',
            'payment_proof_url.max' => 'El comprobante no debe pesar más de 5MB.',
            'auto_approve.boolean' => 'El campo auto_approve debe ser verdadero o falso.',
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
