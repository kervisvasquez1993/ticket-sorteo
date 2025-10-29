<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateAdminMassivePurchaseRequest extends FormRequest
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

            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'currency' => [
                'nullable',
                'string',
                Rule::in(['USD', 'VES']),
            ],

            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
            ],

            'payment_proof_url' => [
                'nullable',
                'file',
                'mimes:jpeg,jpg,png,pdf',
                'max:5120',
            ],

            'identificacion' => [
                'required',
                'string',
                'max:20',
            ],

            // ✅ MODIFICADO: Ahora ambos son completamente opcionales
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
            ],

            'whatsapp' => [
                'nullable',
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
            'event_price_id.required' => 'Debes seleccionar un tipo de precio.',
            'payment_method_id.required' => 'El método de pago es obligatorio.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.min' => 'Debe crear al menos 1 ticket.',
            'identificacion.required' => 'La cédula de identidad es obligatoria.',
            'identificacion.max' => 'La cédula no puede superar los 20 caracteres.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
            'whatsapp.regex' => 'El formato del número de WhatsApp no es válido.',
            'whatsapp.max' => 'El número de WhatsApp no puede superar los 20 caracteres.',
            'payment_proof_url.mimes' => 'El comprobante debe ser jpg, jpeg, png o pdf.',
            'payment_proof_url.max' => 'El comprobante no debe pesar más de 5MB.',
            'auto_approve.boolean' => 'El campo auto_approve debe ser verdadero o falso.',
        ];
    }

    /**
     * ✅ Preparar datos para validación
     * - Si no se envía currency, usar USD por defecto
     * - Establecer valores predeterminados
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'currency' => $this->currency ?? 'USD',
            'auto_approve' => $this->auto_approve ?? true,
        ]);
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
