<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GetPurchasesByWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sin autenticación requerida
    }

    public function rules(): array
    {
        return [
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
            'whatsapp.required' => 'El número de WhatsApp es obligatorio.',
            'whatsapp.regex' => 'El formato del número de WhatsApp no es válido. Debe incluir el código de país (ejemplo: +584244444161).',
            'whatsapp.max' => 'El número de WhatsApp no puede superar los 20 caracteres.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(
            [
                'message' => 'Error de validación',
                'data' => $validator->errors()
            ],
            422
        ));
    }

    protected function prepareForValidation()
    {
        // Normalizar el número de WhatsApp desde la ruta
        $this->merge([
            'whatsapp' => $this->route('whatsapp')
        ]);
    }
}
