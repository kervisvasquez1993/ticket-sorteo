<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EmailController extends Controller
{
    /**
     * Enviar email de prueba usando Mail Facade
     */
    public function sendTestEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'title' => 'nullable|string',
            'message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = [
                'title' => $request->title ?? '¡Notificación de Prueba!',
                'message' => $request->message ?? 'Este es un email de prueba enviado desde Laravel con Resend.',
            ];

            Mail::to($request->email)->send(new TestNotification($data));

            return response()->json([
                'success' => true,
                'message' => 'Email enviado correctamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar email usando Resend Facade (alternativa)
     */
    public function sendWithResendFacade(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'title' => 'nullable|string',
            'message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = [
                'title' => $request->title ?? '¡Notificación de Prueba!',
                'message' => $request->message ?? 'Este es un email de prueba enviado con Resend Facade.',
            ];

            $mailable = new TestNotification($data);

            \Resend\Laravel\Facades\Resend::emails()->send([
                'from' => config('mail.from.address'),
                'to' => [$request->email],
                'subject' => 'Notificación de Prueba',
                'html' => $mailable->render(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email enviado correctamente con Resend Facade'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el email',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
