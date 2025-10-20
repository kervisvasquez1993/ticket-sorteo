<?php

namespace App\Http\Requests\EventPrize;

use App\Models\Event;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class CreateEventPrizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'event_id' => [
                'required',
                'exists:events,id',
                function ($attribute, $value, $fail) {
                    $event = Event::find($value);

                    if (!$event) {
                        $fail('The selected event does not exist.');
                        return;
                    }

                    // Validar que el evento esté completado
                    if ($event->status !== 'completed') {
                        $fail('Cannot add prizes to an event that is not completed. Current status: ' . $event->status);
                        return;
                    }

                    // Validar que tenga winner_number asignado
                    if (is_null($event->winner_number)) {
                        $fail('Cannot add prizes to an event without a winner number assigned.');
                        return;
                    }
                },
            ],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120', // 5MB max
            'is_main' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'event_id.required' => 'Event ID is required',
            'event_id.exists' => 'The selected event does not exist',
            'image.required' => 'Prize image is required',
            'image.image' => 'The file must be an image',
            'image.mimes' => 'Image must be: jpeg, png, jpg or webp',
            'image.max' => 'Image size must not exceed 5MB',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(
            [
                'message' => 'Validation errors',
                'data' => $validator->errors()
            ],
            422
        ));
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'message' => 'You are not authorized to perform this action.',
        ], 403));
    }
}
