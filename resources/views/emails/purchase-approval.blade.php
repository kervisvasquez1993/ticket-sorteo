<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compra Aprobada</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .success-icon {
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
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: bold;
            color: #667eea;
        }
        .tickets {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: bold;
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
        <div class="success-icon">✅</div>
        <h1>¡Tu Compra ha sido Aprobada!</h1>
    </div>

    <div class="content">
        <p>Hola,</p>
        <p>Tu compra ha sido aprobada exitosamente. A continuación encontrarás los detalles:</p>

        <div class="info-box">
            <div class="info-row">
                <span class="label">Evento:</span>
                <span>{{ $event_name }}</span>
            </div>
            <div class="info-row">
                <span class="label">ID de Transacción:</span>
                <span>{{ $transaction_id }}</span>
            </div>
            <div class="info-row">
                <span class="label">Cantidad de Tickets:</span>
                <span>{{ $quantity }}</span>
            </div>
        </div>

        <div class="tickets">
            <strong>🎫 Tus números de ticket:</strong><br>
            @if(count($ticket_numbers) <= 10)
                <span style="font-size: 18px; font-weight: bold; color: #667eea;">
                    {{ implode(', ', $ticket_numbers) }}
                </span>
            @else
                <span>{{ count($ticket_numbers) }} tickets asignados</span>
            @endif
        </div>

        <center>
            <a href="{{ $purchase_url }}" class="button">
                Ver Detalles Completos
            </a>
        </center>

        <p style="margin-top: 30px;">
            <strong>¿Qué sigue?</strong><br>
            Haz clic en el botón de arriba para ver todos los detalles de tu compra, incluyendo el código QR que podrás usar para el evento.
        </p>
    </div>

    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
        <p>&copy; {{ date('Y') }} - Todos los derechos reservados</p>
    </div>
</body>
</html>
