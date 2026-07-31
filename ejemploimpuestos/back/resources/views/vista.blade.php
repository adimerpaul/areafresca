<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; background: #f7f7f7; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .header { background: #276749; padding: 24px 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .header p { color: #c6f6d5; margin: 6px 0 0; font-size: 13px; }
        .body { padding: 28px 32px; }
        .body p { line-height: 1.6; font-size: 15px; }
        .info-box { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 20px; margin: 16px 0; }
        .info-box table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 4px 0; font-size: 14px; }
        .info-box td:first-child { color: #666; width: 160px; }
        .info-box td:last-child { font-weight: bold; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .badge-online  { background: #c6f6d5; color: #276749; }
        .badge-offline { background: #fed7d7; color: #9b2c2c; }
        .footer { background: #f0f4f8; padding: 16px 32px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ env('RAZON', 'Farmacia Eliseo') }}</h1>
        <p>Su salud, nuestra prioridad</p>
    </div>
    <div class="body">
        <p>Estimado(a) cliente,</p>
        <p>Gracias por su compra. Adjunto encontrará su factura en formato <strong>PDF</strong> y <strong>XML</strong>.</p>

        <div class="info-box">
            <table>
                <tr>
                    <td>N° Factura:</td>
                    <td>{{ $numeroFactura ?? '' }}</td>
                </tr>
                @isset($total)
                <tr>
                    <td>Monto total:</td>
                    <td>Bs {{ number_format((float) $total, 2) }}</td>
                </tr>
                @endisset
                @isset($fecha)
                <tr>
                    <td>Fecha de emisión:</td>
                    <td>{{ $fecha }}</td>
                </tr>
                @endisset
            </table>
        </div>

        @if(!empty($online))
            <p><span class="badge badge-online">&#10003; Factura en línea</span>&nbsp;
            Su factura fue registrada exitosamente en el sistema tributario SIAT.</p>
        @else
            <p><span class="badge badge-offline">&#9888; Fuera de línea</span>&nbsp;
            Su factura fue emitida fuera de línea. Puede verificar su estado en
            <a href="https://www.impuestos.gob.bo" target="_blank">www.impuestos.gob.bo</a>.</p>
        @endif

        <p>Conserve este documento como respaldo de su compra.</p>
        <p>Atentamente,<br><strong>{{ env('RAZON', 'Farmacia Eliseo') }}</strong><br>
        Tel. {{ env('TELEFONO') }}</p>
    </div>
    <div class="footer">
        {{ env('DIRECCION') }} &mdash; Oruro, Bolivia
    </div>
</div>
</body>
</html>
