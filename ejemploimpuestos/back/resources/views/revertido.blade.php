<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; background: #f7f7f7; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .header { background: #319795; padding: 24px 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .body { padding: 28px 32px; }
        .body p { line-height: 1.6; font-size: 15px; }
        .info-box { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 20px; margin: 16px 0; }
        .info-box table { width: 100%; border-collapse: collapse; }
        .info-box td { padding: 4px 0; font-size: 14px; }
        .info-box td:first-child { color: #666; width: 160px; }
        .info-box td:last-child { font-weight: bold; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; background: #c6f6d5; color: #22543d; }
        .footer { background: #f0f4f8; padding: 16px 32px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ env('RAZON', 'Farmacia Eliseo') }}</h1>
    </div>
    <div class="body">
        <p>Estimado(a) cliente,</p>

        <p><span class="badge">&#10004; Factura Reactivada</span></p>
        <p>Le informamos que la anulación de la siguiente factura ha sido <strong>revertida</strong> en el sistema tributario SIAT. La factura se encuentra activa y vigente nuevamente.</p>

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
                @if(!empty($cuf))
                <tr>
                    <td>CUF:</td>
                    <td style="font-size:11px; word-break:break-all;">{{ $cuf }}</td>
                </tr>
                @endif
            </table>
        </div>

        <p>Si tiene alguna consulta sobre esta reversión, no dude en contactarnos.</p>
        <p>Atentamente,<br><strong>{{ env('RAZON', 'Farmacia Eliseo') }}</strong><br>
        Tel. {{ env('TELEFONO') }}</p>
    </div>
    <div class="footer">
        {{ env('DIRECCION') }} &mdash; Oruro, Bolivia
    </div>
</div>
</body>
</html>
