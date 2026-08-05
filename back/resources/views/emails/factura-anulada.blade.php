<div style="font-family:Arial,sans-serif;max-width:620px;margin:auto;color:#263238">
    <div style="background:#c62828;color:white;padding:22px;border-radius:12px 12px 0 0">
        <h2 style="margin:0">Factura anulada</h2>
        <div>Area Fresca</div>
    </div>
    <div style="padding:22px;border:1px solid #eceff1;border-top:0;border-radius:0 0 12px 12px">
        <p>Estimado(a) {{ $sale->cliente_nombre ?: 'cliente' }},</p>
        <p>Le informamos que la factura electrónica indicada a continuación fue anulada.</p>
        <div style="background:#ffebee;padding:14px;border-radius:8px;border-left:4px solid #c62828">
            <div><b>Factura:</b> N° {{ $sale->id }}</div>
            <div><b>CUF:</b> {{ $sale->cuf }}</div>
            <div><b>Total:</b> Bs {{ number_format((float) $sale->total, 2) }}</div>
            @if($reason)<div><b>Motivo:</b> {{ $reason }}</div>@endif
        </div>
        <p>Se adjunta la representación gráfica actualizada con la marca <b>ANULADA</b>.</p>
        <p style="color:#607d8b;font-size:12px">Si necesita mayor información, comuníquese con Area Fresca.</p>
    </div>
</div>
