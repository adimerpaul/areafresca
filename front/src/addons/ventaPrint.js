import { Printd } from 'printd'
import QRCode from 'qrcode'
import { companyData } from './empresa'

const css = `
@page{size:80mm auto;margin:4mm}body{margin:0;color:#111;font-family:Arial,sans-serif}
.ticket{width:72mm;font-size:10px}.center{text-align:center}.right{text-align:right}.bold{font-weight:700}
.title{font-size:13px;font-weight:700}.subtitle{font-size:11px;font-weight:700}.company{font-size:11px}
.logo{display:block;width:62px;max-height:62px;object-fit:contain;margin:0 auto 4px}
.line{border-top:1.5px dashed #222;margin:7px 0}.field{display:grid;grid-template-columns:47% 53%;margin:2px 0}
.field-label{text-align:right;font-weight:700;padding-right:8px}.break{overflow-wrap:anywhere}
table{width:100%;border-collapse:collapse}th,td{padding:2px 1px;vertical-align:top}th{font-weight:700}
.product{font-weight:700}.muted{font-size:9px}.totals td:first-child{text-align:right}.highlight{background:#f3f3f3;font-weight:700}
.amount-words{margin:26px 7px 8px}.legal{font-size:8.5px;margin:10px 5px}.qr{display:block;width:118px;height:118px;margin:8px auto 0}
.cancelled{font-size:28px;color:#c62828;text-align:center;font-weight:700}
`

const esc = value => String(value ?? '')
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')

const money = value => Number(value || 0).toFixed(2)
const quantity = item => item.unidad === 'KG' ? Number(item.cantidad).toFixed(3) : Number(item.cantidad).toFixed(2)
const invoiceNumber = sale => String(sale.id ?? sale.numero ?? '').replace(/^V-0*/, '')
const cashier = sale => sale.usuario?.username || sale.usuario_nombre || ''

function qrUrl (sale, company) {
  const base = company.siat_portal_url || 'https://siat.impuestos.gob.bo/'
  const url = new URL('consulta/QR', base.endsWith('/') ? base : `${base}/`)
  url.search = new URLSearchParams({
    nit: company.nit || '',
    cuf: sale.cuf || '',
    numero: invoiceNumber(sale),
    t: '1',
  }).toString()
  return url.toString()
}

export function openSiatInvoice (sale) {
  const company = companyData()

  if (!company.nit || !sale.cuf) {
    throw new Error('La factura no tiene NIT o CUF para consultarla en Impuestos')
  }

  window.open(qrUrl(sale, company), '_blank', 'noopener,noreferrer')
}

function detailRows (sale) {
  return (sale.detalles || []).map(item => `
    <tr><td colspan="2" class="product">${esc(item.codigo_barras || item.codigo)} - ${esc(item.nombre)}</td></tr>
    <tr><td class="muted">Unidad de medida: ${esc(item.unidad || 'Unidad')}</td><td></td></tr>
    <tr><td class="muted">${quantity(item)} X ${money(item.precio_venta)} - ${money(item.descuento)}</td><td class="right">${money(item.total)}</td></tr>
  `).join('')
}

function invoiceMarkup (sale, company, logoUrl, qrDataUrl) {
  const issuedAt = sale.fecha_emision_siat || sale.fecha
  const offline = sale.estado_siat === 'PENDIENTE_EVENTO'
  return `<div class="ticket">
    ${logoUrl ? `<img class="logo" src="${esc(logoUrl)}" alt="Logo">` : ''}
    <div class="center">
      <div class="title">FACTURA</div><div class="subtitle">CON DERECHO A CRÉDITO FISCAL</div>
      <div class="company">${esc(company.nombre_empresa || 'Area Fresca')}</div>
      <div>Casa Matriz</div><div>No. Punto de Venta 0</div>
      <div>${esc(company.direccion || '')}</div><div>Tel. ${esc(company.telefono || '')}</div><div>Oruro</div>
    </div>
    <div class="line"></div>
    <div class="center"><b>NIT</b><br>${esc(company.nit)}<br><b>FACTURA N°</b><br>${esc(invoiceNumber(sale))}<br><b>CÓD. AUTORIZACIÓN</b><br><span class="break">${esc(sale.cuf)}</span></div>
    <div class="line"></div>
    <div class="field"><span class="field-label">NOMBRE/RAZÓN SOCIAL:</span><span>${esc(sale.cliente_nombre || 'SIN NOMBRE')}</span></div>
    <div class="field"><span class="field-label">NIT/CI/CEX:</span><span>${esc(sale.numero_documento)}</span></div>
    <div class="field"><span class="field-label">CÓDIGO CLIENTE:</span><span>${esc(sale.numero_documento)}</span></div>
    <div class="field"><span class="field-label">FECHA DE EMISIÓN:</span><span>${issuedAt ? new Date(issuedAt).toLocaleString('es-BO') : ''}</span></div>
    <div class="field"><span class="field-label">USUARIO:</span><span>${esc(cashier(sale))}</span></div>
    <div class="line"></div><div class="center bold">DETALLE</div>
    <table><tbody>${detailRows(sale)}</tbody></table>
    <table class="totals">
      <tr><td>TOTAL Bs</td><td class="right">${money(sale.subtotal)}</td></tr>
      <tr><td>(-) DESCUENTO Bs</td><td class="right">${money(sale.descuento)}</td></tr>
      <tr class="highlight"><td>MONTO TOTAL A PAGAR Bs</td><td class="right">${money(sale.total)}</td></tr>
      <tr class="highlight"><td>IMPORTE BASE CRÉDITO FISCAL</td><td class="right">${money(sale.total)}</td></tr>
    </table>
    <div class="amount-words">Son: ${money(sale.total)} Bolivianos</div><div class="line"></div>
    <div class="center legal">ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY</div>
    <div class="center legal">${esc(sale.leyenda || 'Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los productos que consumes.')}</div>
    <div class="center legal bold">${offline?'“Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido fuera de línea. Verifique posteriormente su envío con su proveedor o en www.impuestos.gob.bo”':'“Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en línea”'}</div>
    ${offline?'<div class="center bold" style="color:#e65100;font-size:13px">EMITIDA FUERA DE LÍNEA</div>':''}
    <img class="qr" src="${qrDataUrl}" alt="QR de consulta SIAT">
    ${sale.estado === 'ANULADA' || sale.estado_siat === 'ANULADA' ? '<div class="cancelled">ANULADA</div>' : ''}
  </div>`
}

function receiptMarkup (sale, company, logoUrl) {
  const rows = (sale.detalles || []).map(item => `<tr><td>${esc(item.nombre)}<br><span class="muted">${quantity(item)} ${esc(item.unidad)} X ${money(item.precio_venta)}</span></td><td class="right">${money(item.total)}</td></tr>`).join('')
  return `<div class="ticket"><img class="logo" src="${esc(logoUrl)}" alt="Logo"><div class="center"><div class="title">${esc(company.nombre_empresa || 'Area Fresca')}</div>${esc(company.direccion || '')}<br>Tel. ${esc(company.telefono || '')}<br><b>RECIBO</b></div><div class="line"></div>
    <b>${esc(sale.numero)}</b><br>Fecha: ${new Date(sale.fecha).toLocaleString('es-BO')}<br>Cajero: ${esc(cashier(sale))}<br>Pago: ${esc(sale.tipo_pago)}
    <div class="line"></div><table><tbody>${rows}</tbody></table><div class="line"></div>
    <table class="totals"><tr><td>Subtotal</td><td class="right">${money(sale.subtotal)}</td></tr><tr><td>Descuento</td><td class="right">-${money(sale.descuento)}</td></tr><tr class="highlight"><td>TOTAL Bs</td><td class="right">${money(sale.total)}</td></tr></table>
    ${sale.estado === 'ANULADA' ? '<div class="cancelled">ANULADA</div>' : ''}<div class="line"></div><div class="center">¡Gracias por su compra!</div></div>`
}

export async function printSale (sale) {
  const company = companyData()
  const logoUrl = company.logo_url || `${window.location.origin}/bean-logo.svg`
  const isInvoice = sale.tipo_comprobante === 'FACTURA'
  let markup

  if (isInvoice && sale.cuf) {
    const qrDataUrl = await QRCode.toDataURL(qrUrl(sale, company), { width: 320, margin: 1, errorCorrectionLevel: 'M' })
    markup = invoiceMarkup(sale, company, logoUrl, qrDataUrl)
  } else {
    markup = receiptMarkup(sale, company, logoUrl)
  }

  const element = document.createElement('div')
  element.innerHTML = markup
  new Printd().print(element, [css])
}
