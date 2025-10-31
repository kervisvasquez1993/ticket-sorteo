<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4F46E5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4F46E5;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $data['title'] ?? '¡Hola!' }}</h1>
    </div>

    <div class="content">
        <p>{{ $data['message'] ?? 'Este es un mensaje de prueba desde Resend.' }}</p>

        @if(isset($data['action_url']))
            <a href="{{ $data['action_url'] }}" class="button">
                {{ $data['action_text'] ?? 'Ver más' }}
            </a>
        @endif
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} Tu Empresa. Todos los derechos reservados.</p>
    </div>
</body>
</html>
