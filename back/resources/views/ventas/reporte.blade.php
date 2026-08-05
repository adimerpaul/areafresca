<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#263238}h2{margin:0;color:#b71c1c}.meta{margin:4px 0 12px;color:#607d8b}
table{width:100%;border-collapse:collapse}th{background:#b71c1c;color:#fff;padding:6px;text-align:left}td{padding:5px;border-bottom:1px solid #ddd}.right{text-align:right}.totals{margin-top:12px;text-align:right;font-size:12px}
</style></head><body><h2>BAJO CERO — REPORTE DE VENTAS</h2><div class="meta">Generado: {{ now()->format('d/m/Y H:i') }}</div>
<table><thead><tr><th>Nº</th><th>Fecha</th><th>Usuario</th><th>Pago</th><th class="right">Efectivo</th><th class="right">QR</th><th class="right">Descuento</th><th class="right">Total</th><th>Estado</th></tr></thead>
<tbody>@foreach($ventas as $v)<tr><td>{{$v->numero}}</td><td>{{$v->fecha->format('d/m/Y H:i')}}</td><td>{{$v->usuario_nombre}}</td><td>{{$v->tipo_pago}}</td><td class="right">{{number_format($v->monto_efectivo,2)}}</td><td class="right">{{number_format($v->monto_qr,2)}}</td><td class="right">{{number_format($v->descuento,2)}}</td><td class="right">{{number_format($v->total,2)}}</td><td>{{$v->estado}}</td></tr>@endforeach</tbody></table>
<div class="totals"><b>Efectivo: Bs {{number_format($resumen['efectivo'],2)}} &nbsp; QR: Bs {{number_format($resumen['qr'],2)}} &nbsp; Total: Bs {{number_format($resumen['total'],2)}}</b></div>
</body></html>
