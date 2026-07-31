import { Printd } from 'printd'
import { companyData } from './empresa'

const css = `
@page{size:80mm auto;margin:4mm}body{margin:0}.ticket{font-family:Arial,sans-serif;font-size:11px;color:#111}
h2{text-align:center;margin:0}.center{text-align:center}.line{border-top:1px dashed #333;margin:7px 0}
.logo{display:block;width:70px;max-height:70px;object-fit:contain;margin:0 auto 4px}
table{width:100%;border-collapse:collapse}th,td{padding:2px}.right{text-align:right}.bold{font-weight:bold}
.total{font-size:15px}.cancelled{font-size:28px;color:#c62828;text-align:center;font-weight:bold}
`
const esc = value => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
const money = value => Number(value || 0).toFixed(2)

export function printSale (sale) {
  const company = companyData()
  const logoUrl = company.logo_url || `${window.location.origin}/bean-logo.svg`
  const rows = (sale.detalles || []).map(item => `<tr><td>${esc(item.nombre)}<br><small>${item.unidad === 'KG' ? Number(item.cantidad).toFixed(3) : Number(item.cantidad).toFixed(0)} ${esc(item.unidad)} × ${money(item.precio_venta)}</small></td><td class="right">${money(item.total)}</td></tr>`).join('')
  const element = document.createElement('div')
  element.innerHTML = `<div class="ticket"><img class="logo" src="${logoUrl}" alt="Area Fresca"><h2>${esc(company.nombre_empresa||'Area Fresca')}</h2><div class="center">${esc(company.direccion||'')}<br>Tel: ${esc(company.telefono||'')} ${company.nit?`· NIT: ${esc(company.nit)}`:''}<br><b>COMPROBANTE DE VENTA</b></div><div class="line"></div>
  <div><b>${esc(sale.numero)}</b><br>Fecha: ${new Date(sale.fecha).toLocaleString('es-BO')}<br>Cajero: ${esc(sale.usuario_nombre)}<br>Pago: ${esc(sale.tipo_pago)}</div>
  <div class="line"></div><table><thead><tr><th>Producto</th><th class="right">Total</th></tr></thead><tbody>${rows}</tbody></table><div class="line"></div>
  <table><tr><td>Subtotal</td><td class="right">${money(sale.subtotal)}</td></tr><tr><td>Descuento</td><td class="right">-${money(sale.descuento)}</td></tr>
  <tr><td>Efectivo</td><td class="right">${money(sale.monto_efectivo)}</td></tr><tr><td>QR</td><td class="right">${money(sale.monto_qr)}</td></tr>
  <tr class="bold total"><td>TOTAL Bs</td><td class="right">${money(sale.total)}</td></tr></table>
  ${sale.estado === 'ANULADA' ? '<div class="cancelled">ANULADA</div>' : ''}<div class="line"></div><div class="center">¡Gracias por su compra!</div></div>`
  new Printd().print(element, [css])
}
