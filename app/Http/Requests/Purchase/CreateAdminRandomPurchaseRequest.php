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
                'max:100',
            ],
            'currency' => [
                'nullable',
                'string',
                Rule::in(['USD', 'BS']),
            ],
            'payment_reference' => [
                'nullable', // ✅ Opcional para admin
                'string',
                'max:255',
            ],
            'payment_proof_url' => [
                'nullable', // ✅ Opcional para admin
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
