import{n as e,t}from"./empresa-zKPnZAG1.js";var n=e(),r=`
@page{size:80mm auto;margin:4mm}body{margin:0}.ticket{font-family:Arial,sans-serif;font-size:11px;color:#111}
h2{text-align:center;margin:0}.center{text-align:center}.line{border-top:1px dashed #333;margin:7px 0}
.logo{display:block;width:70px;max-height:70px;object-fit:contain;margin:0 auto 4px}
table{width:100%;border-collapse:collapse}th,td{padding:2px}.right{text-align:right}.bold{font-weight:bold}
.total{font-size:15px}.cancelled{font-size:28px;color:#f57c00;text-align:center;font-weight:bold}
`,i=e=>String(e??``).replaceAll(`&`,`&amp;`).replaceAll(`<`,`&lt;`).replaceAll(`>`,`&gt;`),a=e=>Number(e||0).toFixed(2);function o(e){let o=t(),s=o.logo_url||`${window.location.origin}/bean-logo.svg`,c=(e.detalles||[]).map(e=>`<tr><td>${i(e.nombre)}<br><small>${e.unidad===`KG`?Number(e.cantidad).toFixed(3):Number(e.cantidad).toFixed(0)} ${i(e.unidad)} × ${a(e.precio_venta)}</small></td><td class="right">${a(e.total)}</td></tr>`).join(``),l=document.createElement(`div`);l.innerHTML=`<div class="ticket"><img class="logo" src="${s}" alt="Bean"><h2>${i(o.nombre_empresa||`Bean`)}</h2><div class="center">${i(o.direccion||``)}<br>Tel: ${i(o.telefono||``)} ${o.nit?`· NIT: ${i(o.nit)}`:``}<br><b>COMPROBANTE DE VENTA</b></div><div class="line"></div>
  <div><b>${i(e.numero)}</b><br>Fecha: ${new Date(e.fecha).toLocaleString(`es-BO`)}<br>Cajero: ${i(e.usuario_nombre)}<br>Pago: ${i(e.tipo_pago)}</div>
  <div class="line"></div><table><thead><tr><th>Producto</th><th class="right">Total</th></tr></thead><tbody>${c}</tbody></table><div class="line"></div>
  <table><tr><td>Subtotal</td><td class="right">${a(e.subtotal)}</td></tr><tr><td>Descuento</td><td class="right">-${a(e.descuento)}</td></tr>
  <tr><td>Efectivo</td><td class="right">${a(e.monto_efectivo)}</td></tr><tr><td>QR</td><td class="right">${a(e.monto_qr)}</td></tr>
  <tr class="bold total"><td>TOTAL Bs</td><td class="right">${a(e.total)}</td></tr></table>
  ${e.estado===`ANULADA`?`<div class="cancelled">ANULADA</div>`:``}<div class="line"></div><div class="center">¡Gracias por su compra!</div></div>`,new n.Printd().print(l,[r])}export{o as t};