<?php

namespace App\Http\Requests\Event;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class CreateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_number' => 'required|integer|min:0',
            'end_number' => 'required|integer|gt:start_number',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|in:active,completed,cancelled',

            // Validación para imagen (requerida)
            // 'image_url' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // Máximo 2MB

            // Validación para prices
            'prices' => 'required|array|min:1',
            'prices.*.amount' => 'required|numeric|min:0',
            'prices.*.currency' => 'required|string|in:BS,USD',
            'prices.*.is_default' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del evento es obligatorio',
            'end_number.gt' => 'El número final debe ser mayor que el número inicial',
            'start_date.after_or_equal' => 'La fecha de inicio debe ser hoy o posterior',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',

            // Mensajes para imagen
            'image.required' => 'La imagen del evento es obligatoria',
            'image.image' => 'El archivo debe ser una imagen válida',
            'image.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif o webp',
            'image.max' => 'La imagen no debe superar los 2MB',

            // Mensajes para prices
            'prices.required' => 'Debe incluir al menos un precio',
            'prices.*.amount.required' => 'El monto es obligatorio',
            'prices.*.amount.min' => 'El monto debe ser mayor o igual a 0',
            'prices.*.currency.required' => 'La moneda es obligatoria',
            'prices.*.currency.in' => 'La moneda debe ser BS o USD',
            'prices.*.is_default.required' => 'Debe especificar si es precio por defecto',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $prices = $this->input('prices', []);

            // Validar que solo haya un precio por defecto
            $defaultCount = collect($prices)->where('is_default', true)->count();
            if ($defaultCount !== 1) {
                $validator->errors()->add('prices', 'Debe haber exactamente un precio marcado como predeterminado');
            }

            // Validar que no haya monedas duplicadas
            $currencies = collect($prices)->pluck('currency');
            if ($currencies->count() !== $currencies->unique()->count()) {
                $validator->errors()->add('prices', 'No puede haber precios duplicados para la misma moneda');
            }
        });
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Errores de validación',
            'data' => $validator->errors()
        ], 422));
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No tienes permisos para crear eventos. Solo administradores.',
        ], 403));
    }
}
