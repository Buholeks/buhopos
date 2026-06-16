import { imprimirTicketHtml } from "@/helpers/qzTray";
import { crearSvgBarcode } from "@/helpers/etiquetas";
import http from "@/lib/http";

const CONFIG_KEY = "buhopos_ticket_config";

export function obtenerConfigTicket() {
    try {
        return JSON.parse(localStorage.getItem(CONFIG_KEY) || "{}");
    } catch {
        return {};
    }
}

export async function cargarConfigTicketDesdeServidor() {
    try {
        const { data } = await http.get("/api/ticket-config");
        if (data && typeof data === "object" && data.encabezado) {
            localStorage.setItem(CONFIG_KEY, JSON.stringify(data));
        }
    } catch {
        // silencioso — usa lo que haya en localStorage
    }
}

export async function guardarConfigTicket(config) {
    localStorage.setItem(CONFIG_KEY, JSON.stringify(config));
    try {
        await http.put("/api/ticket-config", { config });
    } catch {
        // silencioso — ya quedó en localStorage
    }
}

export async function imprimirTicketVenta(ticket, impresoraQz = null) {
    const config = obtenerConfigTicket();
    const html = crearHtmlTicket(ticket, config);

    if (impresoraQz) {
        await imprimirTicketHtml(impresoraQz, html, Number(config.ancho_mm ?? 80));
        return;
    }

    const ventana = window.open("", "ticket_venta", "width=420,height=720");
    if (!ventana) throw new Error("No se pudo abrir la ventana de impresion.");
    ventana.document.write(html);
    ventana.document.close();
    ventana.focus();
    ventana.onload = () => { ventana.print(); ventana.close(); };
}

export function crearHtmlTicket(ticket, cfg = {}) {
    if (cfg.encabezado && typeof cfg.encabezado === "object") {
        return crearHtmlTicketCanvas(ticket, cfg);
    }
    return crearHtmlTicketLegacy(ticket, cfg);
}

// ─── Renderizado nuevo (canvas) ──────────────────────────────────────────────

function crearHtmlTicketCanvas(ticket, cfg) {
    const ancho = Number(cfg.ancho_mm ?? 80);
    const margen = Number(cfg.margen_mm ?? 3.5);
    const interior = ancho - margen * 2;

    const mostrarFolio = cfg.mostrar_folio !== false;
    const mostrarVendedor = cfg.mostrar_vendedor !== false;
    const mostrarCliente = cfg.mostrar_cliente !== false;

    const encEls = cfg.encabezado?.elementos ?? [];
    const encAlto = cfg.encabezado?.alto_mm ?? 32;
    const pieEls = cfg.pie?.elementos ?? [];
    const pieAlto = cfg.pie?.alto_mm ?? 22;
    const prodCfg = cfg.productos ?? {};
    const resCfg = cfg.resumen ?? {};

    const productos = ticket.productos.map((p) => renderProductoHtml(p, prodCfg)).join("");

    return `<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Ticket ${escapeHtml(ticket.folio)}</title>
<style>
    @page { size: ${ancho}mm auto; margin: ${margen}mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 10px; line-height: 1.15; }
    .ticket { width: ${interior}mm; margin: 0 auto; }
    .sep { border-top: 1px dashed #0f172a; margin: 1.5mm 0; }
    .datos { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8mm 2mm; margin-bottom: 1.5mm; font-size: 8.5px; }
    .dato-label { display: block; color: #64748b; font-size: 6.5px; text-transform: uppercase; }
    .dato-valor { display: block; font-weight: 700; overflow-wrap: anywhere; }
    .sec { font-size: 6.5px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; margin-bottom: 1mm; }
    .bloque { border-top: 1px dashed #0f172a; padding-top: 1.5mm; margin-top: 1.5mm; }
    .producto { padding: 1mm 0; border-bottom: 1px dotted #cbd5e1; }
    .prod-top { display: grid; grid-template-columns: 1fr auto; gap: 2mm; align-items: start; }
    .prod-nombre { font-weight: 800; text-transform: uppercase; overflow-wrap: anywhere; }
    .prod-importe { font-weight: 800; white-space: nowrap; }
    .prod-meta { display: flex; flex-wrap: wrap; gap: 0.5mm 2mm; margin-top: 0.5mm; color: #475569; font-size: 7.5px; }
    .resumen { display: grid; gap: 0.8mm; font-size: 8.5px; }
    .fila { display: flex; justify-content: space-between; gap: 3mm; }
    .fila span:last-child { white-space: nowrap; font-weight: 700; }
    .total { margin: 1mm 0; padding: 1.5mm 2mm; color: #fff; background: #0f172a; font-size: 12px; font-weight: 800; display: flex; justify-content: space-between; }
    .pago { padding-top: 1mm; border-top: 1px dotted #cbd5e1; }
</style></head><body>
<main class="ticket">
    ${renderCanvasHtml(encEls, ticket, encAlto, interior)}
    <div class="sep"></div>
    <div class="datos">
        <div><span class="dato-label">Fecha</span><span class="dato-valor">${fmtFecha(ticket.fecha)}</span></div>
        ${mostrarFolio ? `<div><span class="dato-label">Folio</span><span class="dato-valor">${escapeHtml(ticket.folio)}</span></div>` : ""}
        ${mostrarVendedor && ticket.vendedor ? `<div><span class="dato-label">Vendedor</span><span class="dato-valor">${escapeHtml(ticket.vendedor?.name ?? "-")}</span></div>` : ""}
        ${mostrarCliente ? `<div><span class="dato-label">Cliente</span><span class="dato-valor">${escapeHtml(ticket.cliente?.nombre ?? "Público general")}</span></div>` : ""}
    </div>
    <div class="bloque">
        <div class="sec">Productos</div>
        ${productos}
    </div>
    <div class="bloque resumen">
        <div class="sec">Resumen</div>
        ${resCfg.mostrar_subtotal_lista !== false ? `<div class="fila"><span>Subtotal lista</span><span>${fmt(ticket.subtotal_lista ?? ticket.subtotal)}</span></div>` : ""}
        ${Number(ticket.descuento_precios ?? 0) > 0 && resCfg.mostrar_desc_precios !== false ? `<div class="fila"><span>Desc. precios</span><span>${fmt(ticket.descuento_precios)}</span></div>` : ""}
        <div class="fila"><span>Subtotal</span><span>${fmt(ticket.subtotal)}</span></div>
        ${Number(ticket.descuento ?? 0) > 0 ? `<div class="fila"><span>Desc. general</span><span>${fmt(ticket.descuento)}</span></div>` : ""}
        <div class="total"><span>Total</span><span>${fmt(ticket.total)}</span></div>
        ${Number(ticket.saldo_aplicado ?? 0) > 0 ? `<div class="fila"><span>Saldo a favor aplicado</span><span>-${fmt(ticket.saldo_aplicado)}</span></div><div class="fila"><span>Restante pagado</span><span>${fmt(ticket.restante_pagado)}</span></div>` : ""}
        ${resCfg.mostrar_forma_pago !== false ? `<div class="fila pago"><span>Forma de pago</span><span>${escapeHtml(labelPago(ticket.forma_pago))}</span></div>` : ""}
        ${ticket.forma_pago === "efectivo" && resCfg.mostrar_cambio !== false ? `<div class="fila"><span>Recibido</span><span>${fmt(ticket.monto_recibido)}</span></div><div class="fila"><span>Cambio</span><span>${fmt(ticket.cambio)}</span></div>` : ""}
    </div>
    <div class="sep"></div>
    ${renderCanvasHtml(pieEls, ticket, pieAlto, interior)}
</main>
</body></html>`;
}

function renderCanvasHtml(elementos, ticket, altoMm, interiorMm) {
    if (!elementos?.length) return `<div style="height:${altoMm}mm;"></div>`;
    const items = elementos.map((el) => renderElementoHtml(el, ticket)).join("");
    return `<div style="position:relative;width:100%;height:${altoMm}mm;overflow:hidden;">${items}</div>`;
}

function renderElementoHtml(el, ticket) {
    const base = `position:absolute;left:${el.x}mm;top:${el.y}mm;width:${el.ancho}mm;height:${el.alto}mm;overflow:hidden;`;
    if (el.tipo === "separador") {
        const yMid = Number(el.y) + Number(el.alto) / 2;
        return `<div style="position:absolute;left:${el.x}mm;top:${yMid}mm;width:${el.ancho}mm;height:0;border-top:1px dashed #0f172a;"></div>`;
    }
    if (el.tipo === "codigo_barras") {
        const val = resolverCampoTicket(ticket, el.campo || "folio");
        const barcodeHtml = crearSvgBarcode(val, el.ancho, el.alto, el.mostrar_texto !== false, el.fuente_barcode || null);
        return `<div style="${base}">${barcodeHtml}</div>`;
    }
    const alin = el.alineacion === "centro" ? "center" : el.alineacion === "derecha" ? "right" : "left";
    const ff = el.familia_fuente ? escapeHtml(el.familia_fuente) : "Arial,Helvetica,sans-serif";
    const val = el.tipo === "texto" ? escapeHtml(el.texto || "") : escapeHtml(resolverCampoTicket(ticket, el.campo));
    return `<div style="${base}font-size:${el.fuente || 10}pt;font-weight:${el.negrita ? 800 : 400};font-family:${ff};text-align:${alin};display:flex;align-items:center;"><span style="width:100%;">${val}</span></div>`;
}

function resolverCampoTicket(ticket, campo) {
    if (!campo) return "";
    if (campo === "fecha") return fmtFecha(ticket.fecha);
    if (campo === "folio_corto") return String(ticket.folio ?? "").replace(/\D/g, "");
    return String(campo.split(".").reduce((o, k) => o?.[k], ticket) ?? "");
}

function renderProductoHtml(p, cfg = {}) {
    const mostrarVariante = cfg.mostrar_variante !== false;
    const mostrarId = cfg.mostrar_identificador !== false;
    const mostrarPrecio = cfg.mostrar_precio_unitario !== false;
    const mostrarDesc = cfg.mostrar_descuento !== false;
    const meta = [
        mostrarVariante && p.variante ? `<span>${escapeHtml(p.variante)}</span>` : "",
        mostrarId && p.identificador ? `<span>Serie: ${escapeHtml(p.identificador)}</span>` : "",
    ].filter(Boolean).join("");
    const precios = mostrarPrecio ? [
        `<span>${fmtCantidad(p.cantidad)} × ${fmt(p.precio_unitario)}</span>`,
        p.lista_precio_usada ? `<span>${escapeHtml(p.lista_precio_usada)}</span>` : "",
        mostrarDesc && p.descuento > 0 ? `<span>Desc. ${fmt(p.descuento)}</span>` : "",
    ].filter(Boolean).join("") : "";
    return `<div class="producto"><div class="prod-top"><div class="prod-nombre">${escapeHtml(p.nombre)}</div><div class="prod-importe">${fmt(p.importe)}</div></div>${meta ? `<div class="prod-meta">${meta}</div>` : ""}${precios ? `<div class="prod-meta">${precios}</div>` : ""}</div>`;
}

// ─── Renderizado legado (config anterior sin canvas) ─────────────────────────

function crearHtmlTicketLegacy(ticket, cfg) {
    const ancho = Number(cfg.ancho_mm ?? 80);
    const margen = Number(cfg.margen_mm ?? 3.5);
    const interior = ancho - margen * 2;
    const mostrarRfc = cfg.mostrar_rfc !== false;
    const mostrarDireccion = cfg.mostrar_direccion !== false;
    const mostrarTelefono = cfg.mostrar_telefono !== false;
    const mostrarVendedor = cfg.mostrar_vendedor !== false;
    const mostrarCliente = cfg.mostrar_cliente !== false;
    const mostrarFolio = cfg.mostrar_folio !== false;
    const pieMensaje = cfg.pie_mensaje ?? "Gracias por su compra";
    const pieExtra = cfg.pie_extra ?? "";

    const productos = ticket.productos.map((p) => `
        <article class="producto">
            <div class="producto-top">
                <div class="producto-nombre">${escapeHtml(p.nombre)}</div>
                <div class="producto-importe">${fmt(p.importe)}</div>
            </div>
            ${p.variante || p.identificador ? `<div class="producto-meta">
                ${p.variante ? `<span>${escapeHtml(p.variante)}</span>` : ""}
                ${p.identificador ? `<span>Serie: ${escapeHtml(p.identificador)}</span>` : ""}
            </div>` : ""}
            <div class="producto-precios">
                <span>${fmtCantidad(p.cantidad)} x ${fmt(p.precio_unitario)}</span>
                ${p.lista_precio_usada ? `<span>${escapeHtml(p.lista_precio_usada)}</span>` : ""}
                ${p.descuento > 0 ? `<span>Aplicado ${fmt(p.precio_aplicado)} | Desc ${fmt(p.descuento)}</span>` : ""}
            </div>
        </article>`).join("");

    return `<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Ticket ${escapeHtml(ticket.folio)}</title>
<style>
    @page { size: ${ancho}mm auto; margin: ${margen}mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 10px; line-height: 1.15; }
    .ticket { width: ${interior}mm; margin: 0 auto; }
    .marca { padding: 2px 2px 4px; text-align: center; border-bottom: 1px dashed #0f172a; }
    .empresa { font-size: 14px; font-weight: 800; letter-spacing: 0.4px; text-transform: uppercase; }
    .sucursal { margin-top: 2px; font-weight: 700; }
    .copia { margin-top: 3px; font-size: 8px; font-weight: 800; letter-spacing: 1.8px; text-transform: uppercase; }
    .muted { color: #475569; }
    .mini { font-size: 8px; }
    .bloque { border-top: 1px dashed #0f172a; padding-top: 4px; margin-top: 4px; }
    .datos { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 6px; margin-top: 4px; }
    .dato-label { display: block; color: #64748b; font-size: 7px; text-transform: uppercase; }
    .dato-valor { display: block; font-weight: 700; overflow-wrap: anywhere; }
    .seccion-titulo { display: flex; align-items: center; gap: 4px; margin-bottom: 3px; font-size: 7px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; }
    .seccion-titulo::after { content: ""; height: 1px; flex: 1; border-top: 1px dashed #94a3b8; }
    .producto { padding: 3px 0; border-bottom: 1px dotted #cbd5e1; }
    .producto-top { display: grid; grid-template-columns: 1fr auto; gap: 6px; align-items: start; }
    .producto-nombre { font-weight: 800; text-transform: uppercase; overflow-wrap: anywhere; }
    .producto-importe { font-weight: 800; white-space: nowrap; }
    .producto-meta, .producto-precios { display: flex; flex-wrap: wrap; gap: 1px 5px; margin-top: 1px; color: #475569; font-size: 8px; }
    .resumen { display: grid; gap: 1.5px; }
    .fila { display: flex; justify-content: space-between; gap: 8px; }
    .fila span:last-child { white-space: nowrap; font-weight: 700; }
    .total { margin: 3px 0; padding: 3px 5px; color: #fff; background: #0f172a; font-size: 13px; font-weight: 800; }
    .pago { padding-top: 2px; border-top: 1px dotted #cbd5e1; }
    .pie { margin-top: 5px; text-align: center; }
    .gracias { font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .codigo { margin-top: 3px; display: block; width: 100%; }
    .pie-extra { margin-top: 2px; font-size: 8px; color: #475569; }
</style></head><body>
<main class="ticket">
    <section class="marca">
        <div class="empresa">${escapeHtml(ticket.empresa?.nombre ?? "BuhoPOS")}</div>
        ${mostrarRfc && ticket.empresa?.rfc ? `<div>RFC: ${escapeHtml(ticket.empresa.rfc)}</div>` : ""}
        ${ticket.sucursal?.nombre ? `<div class="sucursal">${escapeHtml(ticket.sucursal.nombre)}</div>` : ""}
        ${ticket.reimpresion ? `<div class="copia">Copia / reimpresion</div>` : ""}
        ${mostrarDireccion && ticket.sucursal?.direccion ? `<div class="muted mini">${escapeHtml(ticket.sucursal.direccion)}</div>` : ""}
        ${mostrarTelefono && ticket.sucursal?.telefono ? `<div class="muted mini">Tel: ${escapeHtml(ticket.sucursal.telefono)}</div>` : ""}
    </section>
    <section class="datos">
        <div><span class="dato-label">Fecha</span><span class="dato-valor">${fmtFecha(ticket.fecha)}</span></div>
        ${mostrarFolio ? `<div><span class="dato-label">Folio</span><span class="dato-valor">${escapeHtml(ticket.folio)}</span></div>` : ""}
        ${mostrarVendedor && ticket.vendedor ? `<div><span class="dato-label">Vendedor</span><span class="dato-valor">${escapeHtml(ticket.vendedor.name ?? "-")}</span></div>` : ""}
        ${mostrarCliente ? `<div><span class="dato-label">Cliente</span><span class="dato-valor">${escapeHtml(ticket.cliente?.nombre ?? "Publico general")}</span></div>` : ""}
    </section>
    <section class="bloque">
        <div class="seccion-titulo">Productos</div>
        ${productos}
    </section>
    <section class="bloque resumen">
        <div class="seccion-titulo">Resumen</div>
        <div class="fila"><span>Subtotal lista</span><span>${fmt(ticket.subtotal_lista ?? ticket.subtotal)}</span></div>
        ${Number(ticket.descuento_precios ?? 0) > 0 ? `<div class="fila"><span>Desc. precios</span><span>${fmt(ticket.descuento_precios)}</span></div>` : ""}
        <div class="fila"><span>Subtotal</span><span>${fmt(ticket.subtotal)}</span></div>
        ${Number(ticket.descuento ?? 0) > 0 ? `<div class="fila"><span>Desc. general</span><span>${fmt(ticket.descuento)}</span></div>` : ""}
        <div class="fila total"><span>Total</span><span>${fmt(ticket.total)}</span></div>
        ${Number(ticket.saldo_aplicado ?? 0) > 0 ? `<div class="fila"><span>Saldo a favor aplicado</span><span>-${fmt(ticket.saldo_aplicado)}</span></div><div class="fila"><span>Restante pagado</span><span>${fmt(ticket.restante_pagado)}</span></div>` : ""}
        <div class="fila pago"><span>Forma de pago</span><span>${escapeHtml(labelPago(ticket.forma_pago))}</span></div>
        ${ticket.forma_pago === "efectivo" ? `<div class="fila"><span>Recibido</span><span>${fmt(ticket.monto_recibido)}</span></div><div class="fila"><span>Cambio</span><span>${fmt(ticket.cambio)}</span></div>` : ""}
    </section>
    <section class="pie">
        ${pieMensaje ? `<div class="gracias">${escapeHtml(pieMensaje)}</div>` : ""}
        <div class="muted">Conserve este ticket</div>
        <div class="codigo">${crearSvgBarcode(ticket.folio, interior, 12, true)}</div>
        ${pieExtra ? `<div class="pie-extra">${escapeHtml(pieExtra)}</div>` : ""}
    </section>
</main>
</body></html>`;
}

// ─── Utilidades ──────────────────────────────────────────────────────────────

function fmt(v) {
    return new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN", minimumFractionDigits: 2 }).format(Number(v ?? 0));
}
function fmtCantidad(v) {
    const n = Number(v ?? 0);
    return Number.isInteger(n) ? String(n) : n.toFixed(3);
}
function fmtFecha(v) {
    return new Date(v).toLocaleString("es-MX", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
}
function labelPago(v) {
    return { efectivo: "Efectivo", credito: "Credito", transferencia: "Transferencia", tarjeta: "Tarjeta", tarjeta_debito: "Tarjeta débito", tarjeta_credito: "Tarjeta crédito" }[v] ?? v ?? "-";
}
function escapeHtml(value) {
    return String(value ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
}
