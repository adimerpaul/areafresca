@php $c = $venta->cliente; @endphp
<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;padding:16px">
    <h2 style="margin:0 0 8px">¡Gracias por su compra!</h2>
    <p style="margin:0 0 12px">
        Estimad@ {{ $c?->nombre ?? 'Cliente' }}, adjuntamos su factura (PDF) y el XML correspondiente.
    </p>
    <div style="background:#f6f7f9;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:12px">
        <p style="margin:4px 0"><b>N° Factura:</b> {{ $venta->id }}</p>
        @if($venta->cuf)<p style="margin:4px 0"><b>CUF:</b> {{ $venta->cuf }}</p>@endif
        <p style="margin:4px 0"><b>Fecha:</b> {{ $venta->fecha }} {{ $venta->hora }}</p>
        <p style="margin:4px 0"><b>Total:</b> Bs {{ number_format($venta->total,2) }}</p>
        <p style="margin:4px 0"><b>Método de pago:</b> {{ $venta->tipo_pago }}</p>
    </div>
    <p style="margin:0">Si tiene alguna consulta, responda a este correo.</p>
    <p style="margin:8px 0 0;color:#6b7280">— {{ config('app.name') }}</p>
</div>
