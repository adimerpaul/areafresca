<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;color:#263238">
    <div style="background:#f57c00;color:white;padding:22px;border-radius:12px 12px 0 0">
        <h2 style="margin:0">¡Gracias por su compra!</h2>
        <div>Area Fresca</div>
    </div>
    <div style="padding:22px;border:1px solid #eceff1;border-top:0;border-radius:0 0 12px 12px">
        <p>Estimado(a) {{ $sale->cliente_nombre ?: 'cliente' }},</p>
        <p>Su factura electrónica fue validada por Impuestos Nacionales. Adjuntamos la representación gráfica en PDF y el archivo XML firmado.</p>
        <div style="background:#fff8e1;padding:14px;border-radius:8px">
            <div><b>Factura:</b> N° {{ $sale->id }}</div>
            <div><b>Fecha:</b> {{ optional($sale->fecha_emision_siat ?: $sale->fecha)->format('d/m/Y H:i') }}</div>
            <div><b>Total:</b> Bs {{ number_format((float) $sale->total, 2) }}</div>
            <div><b>Estado:</b> {{ $sale->estado_siat }}</div>
        </div>
        <p style="color:#607d8b;font-size:12px">Este correo fue generado automáticamente. Conserve los archivos adjuntos.</p>
    </div>
</div>
