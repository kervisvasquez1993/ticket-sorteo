<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class CreateAdminMassivePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        $maxQuantity = config('purchases.admin_massive.max_quantity', 10000);

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

            'quantity' => [
                'required',
                'integer',
                'min:1',
                "max:{$maxQuantity}",
            ],

            'identificacion' => [
                'nullable',
                'string',
                'max:20',
            ],

            'fullname' => [
                'nullable',
                'string',
                'max:255',
            ],

            'auto_approve' => [
                'nullable',
                'boolean',
            ],

            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'event_id.required' => 'El evento es obligatorio.',
            'event_id.exists' => 'El evento seleccionado no existe.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.min' => 'Debe crear al menos 1 ticket.',
            'quantity.max' => 'No puedes crear más de 10,000 tickets a la vez.',
            'identificacion.max' => 'La cédula no puede superar los 20 caracteres.',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
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
