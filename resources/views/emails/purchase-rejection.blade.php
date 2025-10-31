<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información sobre tu Compra</title>
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .warning-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .info-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .reason-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            color: #888;
            font-size: 12px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="warning-icon">❌</div>
        <h1>Información sobre tu Compra</h1>
    </div>

    <div class="content">
        <p>Hola,</p>
        <p>Lamentablemente tu compra no pudo ser procesada.</p>

        <div class="info-box">
            <p><strong>Evento:</strong> {{ $event_name }}</p>
            <p><strong>ID de Transacción:</strong> {{ $transaction_id }}</p>
        </div>

        @if($reason)
        <div class="reason-box">
            <strong>Motivo:</strong><br>
            {{ $reason }}
        </div>
        @endif

        <p>
            <strong>¿Qué puedes hacer?</strong><br>
            • Verifica que la información de pago sea correcta<br>
            • Intenta realizar una nueva compra<br>
            • Contacta con nuestro equipo de soporte si necesitas ayuda
        </p>

        <p style="margin-top: 30px;">
            Disculpa las molestias. Estamos aquí para ayudarte.
        </p>
    </div>

    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
        <p>Para soporte, contacta con nuestro equipo.</p>
        <p>&copy; {{ date('Y') }} - Todos los derechos reservados</p>
    </div>
</body>
</html>
