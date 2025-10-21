<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class GetUsersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo administradores pueden listar usuarios
        return Auth::check() && Auth::user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'nullable|email',
            'name' => 'nullable|string|max:255',
            'role' => 'nullable|in:admin,customer',
            'search' => 'nullable|string|max:255',
            'sort_by' => 'nullable|in:created_at,name,email,role',
            'sort_order' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => 'El email debe ser válido',
            'role.in' => 'El rol debe ser "admin" o "customer"',
            'sort_by.in' => 'El campo de ordenamiento no es válido',
            'sort_order.in' => 'El orden debe ser "asc" o "desc"',
            'page.integer' => 'La página debe ser un número',
            'page.min' => 'La página debe ser mayor a 0',
            'per_page.integer' => 'Los elementos por página deben ser un número',
            'per_page.min' => 'Debe haber al menos 1 elemento por página',
            'per_page.max' => 'No se pueden mostrar más de 100 elementos por página',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Errores de validación',
            'data' => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No tienes permisos para listar usuarios. Solo administradores.',
        ], 403));
    }
}
