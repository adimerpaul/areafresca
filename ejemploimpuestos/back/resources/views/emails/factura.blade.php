@php
    $cliente = $venta->cliente;
@endphp

<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;padding:16px;">
    <h2 style="margin:0 0 8px;">¡Gracias por su compra!</h2>
    <p style="margin:0 0 16px;">
        Estimad@ {{ $cliente?->nombre ?? 'Cliente' }},<br>
        Adjuntamos su factura en formato PDF y el archivo XML correspondiente.
    </p>

    <div style="background:#f6f7f9;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:12px;">
        <p style="margin:4px 0;"><b>N° Factura:</b> {{ $venta->id }}</p>
        @if($venta->cuf)<p style="margin:4px 0;"><b>CUF:</b> {{ $venta->cuf }}</p>@endif
        <p style="margin:4px 0;"><b>Fecha:</b> {{ $venta->fecha }} {{ $venta->hora }}</p>
        <p style="margin:4px 0;"><b>Total:</b> Bs {{ number_format($venta->total, 2) }}</p>
        <p style="margin:4px 0;"><b>Método de pago:</b> {{ $venta->tipo_pago }}</p>
    </div>

    <h3 style="margin:16px 0 8px;">Detalle</h3>
    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #e5e7eb;">
        <thead>
        <tr style="background:#f3f4f6;">
            <th align="left">Producto</th>
            <th align="right">Cant.</th>
            <th align="right">Precio</th>
            <th align="right">Subtotal</th>
        </tr>
        </thead>
        <tbody>
        @foreach($venta->ventaDetalles as $d)
            <tr>
                <td style="border-top:1px solid #e5e7eb;">{{ $d->nombre ?? $d->producto?->nombre }}</td>
                <td style="border-top:1px solid #e5e7eb;" align="right">{{ rtrim(rtrim(number_format($d->cantidad, 2, '.', ''), '0'), '.') }}</td>
                <td style="border-top:1px solid #e5e7eb;" align="right">{{ number_format($d->precio, 2) }}</td>
                <td style="border-top:1px solid #e5e7eb;" align="right">{{ number_format($d->precio * $d->cantidad, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p style="margin:16px 0 0;">Si tiene alguna consulta, responda a este correo.</p>
    <p style="margin:0;color:#6b7280;">— {{ config('app.name') }}</p>
</div>
