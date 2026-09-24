import { imprimirDocumento } from '../printing/printerService.js';
import { obtenerPrinterConfig } from '../printing/printerConfig.js';
import { obtenerConfigTicket } from './ticketConfigStore.js';
import { obtenerImpresoraTicket } from '../qzTray.js';
import { crearSvgBarcode } from '../etiquetas.js';

const esc = value => String(value ?? '')
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
const dinero = value => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0));
const fecha = value => value ? new Date(value).toLocaleString('es-MX', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
}) : '-';
const fechaCorta = value => value ? new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString('es-MX', {
    day: '2-digit', month: 'short', year: 'numeric',
}) : 'Sin fecha';

export function crearHtmlTicketEncargo(pedido, { reimpresion = false } = {}) {
    const diseno = obtenerConfigTicket();
    const ancho = Number(diseno?.ancho_mm ?? 80);
    // Da medio milímetro adicional por lado: varias térmicas económicas no
    // imprimen exactamente hasta el borde declarado por el controlador.
    const margen = Number(diseno?.margen_mm ?? 3.5) + 0.5;
    const interior = Math.max(20, ancho - margen * 2);
    const tipo = pedido?.tipo === 'apartado' ? 'APARTADO' : 'PEDIDO';
    const empresa = pedido?.empresa ?? {};
    const sucursal = pedido?.sucursal ?? {};
    const detalles = Array.isArray(pedido?.detalles) ? pedido.detalles : [];
    const filas = detalles.map(detalle => {
        const rasgos = [detalle.marca_texto, detalle.modelo_texto, detalle.color_texto, detalle.talla_texto].filter(Boolean).join(' · ');
        return `<div class="producto">
            <div class="linea"><strong>${esc(detalle.descripcion || detalle.producto?.nombre || 'Artículo')}</strong><strong>${dinero(detalle.subtotal)}</strong></div>
            ${rasgos ? `<div class="muted">${esc(rasgos)}</div>` : ''}
            <div class="muted">${Number(detalle.cantidad || 0)} × ${dinero(detalle.precio_acordado)}</div>
            ${detalle.notas ? `<div class="nota">${esc(detalle.notas)}</div>` : ''}
        </div>`;
    }).join('');
    const formaPagoId = pedido?.saldos?.[0]?.forma_pago ?? pedido?.saldos?.[0]?.movimiento_caja?.forma_pago;
    const formaPago = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', transferencia: 'Transferencia', saldo_favor: 'Saldo a favor' }[formaPagoId] ?? formaPagoId;
    const pieConfigurado = pedido?.tipo === 'apartado' ? pedido?.pie_ticket_apartado : pedido?.pie_ticket_pedido;
    const pie = String(pieConfigurado || `Comprobante de ${tipo.toLowerCase()}. No representa una venta final.`)
        .trim().split(/\r?\n/).map(esc).join('<br>');
    const logo = empresa.logo_url
        ? `<img src="${esc(empresa.logo_url)}" alt="Logo" data-ticket-logo data-logo-fallback="${esc(empresa.nombre || 'Empresa')}" class="logo">`
        : `<div class="empresa">${esc(empresa.nombre || 'Empresa')}</div>`;
    return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${tipo} ${esc(pedido?.folio)}</title>
<style>
@page{size:auto;margin:${margen}mm}*{box-sizing:border-box}body{margin:0;color:#111827;font-family:Arial,sans-serif;font-size:8.5px;line-height:1.15}.ticket{width:${interior}mm;margin:0 auto}.center{text-align:center}.logo{display:block;max-width:100%;height:11mm;object-fit:contain;margin:0 auto .5mm}.empresa{font-size:13px;font-weight:800;text-align:center}.tipo{font-size:14px;font-weight:900;letter-spacing:1.2px;margin-top:.8mm}.copia{font-size:7px;font-weight:800}.sep{border-top:1px dashed #111827;margin:1mm 0}.linea{display:flex;justify-content:space-between;gap:2mm}.linea>:last-child{white-space:nowrap}.datos{display:grid;gap:.35mm}.producto{padding:.65mm 0;border-bottom:1px dotted #9ca3af;break-inside:avoid}.muted{color:#4b5563;font-size:7.5px}.nota{margin-top:.25mm;font-style:italic}.total{font-size:12px;font-weight:900;border-top:1px solid #111827;padding-top:.5mm;margin-top:.5mm}.saldo{font-size:11px;font-weight:900}.aviso{margin-top:1mm;padding:.7mm;border:1px solid #111827;text-align:center;font-size:11px}.barcode{margin-top:.5mm}.barcode img{max-width:100%;height:10mm;object-fit:contain}
</style></head><body><main class="ticket">
${logo}<div class="center">${esc(sucursal.nombre || '')}</div><div class="center muted">${esc(sucursal.direccion || '')}</div><div class="center muted">${sucursal.telefono ? `Tel. ${esc(sucursal.telefono)}` : ''}</div>
<div class="sep"></div><div class="center tipo">${tipo}</div>${reimpresion ? '<div class="center copia">COPIA / REIMPRESIÓN</div>' : ''}
<div class="datos"><div class="linea"><span>Fecha</span><strong>${esc(fecha(pedido?.created_at))}</strong></div><div class="linea"><span>Fecha prometida</span><strong>${esc(fechaCorta(pedido?.fecha_promesa))}</strong></div><div><span>Cliente: </span><strong>${esc(pedido?.cliente?.nombre || '-')}</strong>${pedido?.cliente?.telefono ? ` · ${esc(pedido.cliente.telefono)}` : ''}</div></div>
<div class="sep"></div>${filas || '<div>Sin artículos</div>'}<div class="sep"></div>
<div class="datos"><div class="linea"><span>Total</span><strong>${dinero(pedido?.subtotal)}</strong></div><div class="linea"><span>Anticipo</span><strong>${dinero(pedido?.anticipo)}</strong></div>${formaPago ? `<div class="linea"><span>Forma de pago</span><strong>${esc(formaPago)}</strong></div>` : ''}<div class="linea saldo"><span>Saldo pendiente</span><span>${dinero(pedido?.saldo_pendiente)}</span></div></div>
${pedido?.notas ? `<div class="sep"></div><div><strong>Notas:</strong> ${esc(pedido.notas)}</div>` : ''}
<div class="aviso">${pie}</div><div class="barcode">${crearSvgBarcode(pedido?.folio || '', interior, 10, true, 9)}</div>
</main></body></html>`;
}

export async function imprimirTicketEncargo(pedido, opciones = {}) {
    const printerName = obtenerImpresoraTicket();
    const config = obtenerPrinterConfig(printerName);
    const diseno = obtenerConfigTicket();
    return imprimirDocumento({
        renderHtml: () => crearHtmlTicketEncargo(pedido, opciones),
        config,
        paperWidth: Number(diseno?.ancho_mm ?? 80),
    });
}
