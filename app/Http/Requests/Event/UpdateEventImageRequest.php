<?php

namespace App\Http\Requests\Event;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class UpdateEventImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'La imagen es requerida',
            'image.image' => 'El archivo debe ser una imagen',
            'image.mimes' => 'La imagen debe ser jpeg, png, jpg o webp',
            'image.max' => 'La imagen no debe superar 5MB',
        ];
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
            'message' => 'No tienes permisos para actualizar la imagen del evento.',
        ], 403));
    }
}
