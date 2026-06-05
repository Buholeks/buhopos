export function imprimirTicketVenta(ticket) {
    const ventana = window.open("", "ticket_venta", "width=420,height=720");

    if (!ventana) {
        throw new Error("No se pudo abrir la ventana de impresion.");
    }

    ventana.document.write(crearHtmlTicket(ticket));
    ventana.document.close();
    ventana.focus();

    ventana.onload = () => {
        ventana.print();
        ventana.close();
    };
}

function crearHtmlTicket(ticket) {
    const productos = ticket.productos
        .map(
            (p) => `
                <article class="producto">
                    <div class="producto-top">
                        <div class="producto-nombre">${escapeHtml(p.nombre)}</div>
                        <div class="producto-importe">${fmt(p.importe)}</div>
                    </div>

                    ${
                        p.variante || p.identificador
                            ? `
                                <div class="producto-meta">
                                    ${p.variante ? `<span>${escapeHtml(p.variante)}</span>` : ""}
                                    ${p.identificador ? `<span>Serie: ${escapeHtml(p.identificador)}</span>` : ""}
                                </div>
                            `
                            : ""
                    }

                    <div class="producto-precios">
                        <span>${fmtCantidad(p.cantidad)} x ${fmt(p.precio_unitario)}</span>
                        ${p.lista_precio_usada ? `<span>${escapeHtml(p.lista_precio_usada)}</span>` : ""}
                        ${
                            p.descuento > 0
                                ? `<span>Aplicado ${fmt(p.precio_aplicado)} | Desc ${fmt(p.descuento)}</span>`
                                : ""
                        }
                    </div>
                </article>
            `,
        )
        .join("");

    return `
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket ${escapeHtml(ticket.folio)}</title>
    <style>
        @page { size: 80mm auto; margin: 3.5mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.25;
        }
        .ticket { width: 73mm; margin: 0 auto; }
        .marca {
            padding: 2px 2px 7px;
            text-align: center;
            border-bottom: 1px dashed #0f172a;
        }
        .empresa {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .sucursal { margin-top: 3px; font-weight: 700; }
        .copia {
            margin-top: 5px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }
        .muted { color: #475569; }
        .mini { font-size: 9px; }
        .bloque {
            border-top: 1px dashed #0f172a;
            padding-top: 7px;
            margin-top: 7px;
        }
        .datos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 8px;
            margin-top: 7px;
        }
        .dato-label {
            display: block;
            color: #64748b;
            font-size: 8px;
            text-transform: uppercase;
        }
        .dato-valor {
            display: block;
            font-weight: 700;
            overflow-wrap: anywhere;
        }
        .seccion-titulo {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 5px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }
        .seccion-titulo::after {
            content: "";
            height: 1px;
            flex: 1;
            border-top: 1px dashed #94a3b8;
        }
        .producto {
            padding: 6px 0;
            border-bottom: 1px dotted #cbd5e1;
        }
        .producto-top {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            align-items: start;
        }
        .producto-nombre {
            font-weight: 800;
            text-transform: uppercase;
            overflow-wrap: anywhere;
        }
        .producto-importe {
            font-weight: 800;
            white-space: nowrap;
        }
        .producto-meta,
        .producto-precios {
            display: flex;
            flex-wrap: wrap;
            gap: 3px 7px;
            margin-top: 3px;
            color: #475569;
            font-size: 9px;
        }
        .resumen {
            display: grid;
            gap: 3px;
        }
        .fila {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        .fila span:last-child {
            white-space: nowrap;
            font-weight: 700;
        }
        .total {
            margin: 6px 0;
            padding: 6px;
            color: #fff;
            background: #0f172a;
            font-size: 15px;
            font-weight: 800;
        }
        .pago {
            padding-top: 4px;
            border-top: 1px dotted #cbd5e1;
        }
        .pie {
            margin-top: 9px;
            text-align: center;
        }
        .gracias {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .codigo {
            margin-top: 6px;
            font-family: "Courier New", monospace;
            font-size: 10px;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <main class="ticket">
        <section class="marca">
            <div class="empresa">${escapeHtml(ticket.empresa?.nombre ?? "BuhoPOS")}</div>
            ${ticket.empresa?.rfc ? `<div>RFC: ${escapeHtml(ticket.empresa.rfc)}</div>` : ""}
            ${ticket.sucursal?.nombre ? `<div class="sucursal">${escapeHtml(ticket.sucursal.nombre)}</div>` : ""}
            ${ticket.reimpresion ? `<div class="copia">Copia / reimpresion</div>` : ""}
            ${ticket.sucursal?.direccion ? `<div class="muted mini">${escapeHtml(ticket.sucursal.direccion)}</div>` : ""}
            ${ticket.sucursal?.telefono ? `<div class="muted mini">Tel: ${escapeHtml(ticket.sucursal.telefono)}</div>` : ""}
        </section>

        <section class="datos">
            <div>
                <span class="dato-label">Fecha</span>
                <span class="dato-valor">${fmtFecha(ticket.fecha)}</span>
            </div>
            <div>
                <span class="dato-label">Usuario</span>
                <span class="dato-valor">${escapeHtml(ticket.usuario?.name ?? "-")}</span>
            </div>
            <div>
                <span class="dato-label">Vendedor</span>
                <span class="dato-valor">${escapeHtml(ticket.vendedor?.name ?? "-")}</span>
            </div>
            <div>
                <span class="dato-label">Cliente</span>
                <span class="dato-valor">${escapeHtml(ticket.cliente?.nombre ?? "Publico general")}</span>
            </div>
        </section>

        <section class="bloque">
            <div class="seccion-titulo">Productos</div>
            ${productos}
        </section>

        <section class="bloque resumen">
            <div class="seccion-titulo">Resumen</div>
            <div class="fila"><span>Subtotal lista</span><span>${fmt(ticket.subtotal_lista ?? ticket.subtotal)}</span></div>
            ${
                Number(ticket.descuento_precios ?? 0) > 0
                    ? `<div class="fila"><span>Desc. precios</span><span>${fmt(ticket.descuento_precios)}</span></div>`
                    : ""
            }
            <div class="fila"><span>Subtotal</span><span>${fmt(ticket.subtotal)}</span></div>
            ${
                Number(ticket.descuento ?? 0) > 0
                    ? `<div class="fila"><span>Desc. general</span><span>${fmt(ticket.descuento)}</span></div>`
                    : ""
            }
            <div class="fila total"><span>Total</span><span>${fmt(ticket.total)}</span></div>
            ${
                Number(ticket.saldo_aplicado ?? 0) > 0
                    ? `
                        <div class="fila"><span>Saldo a favor aplicado</span><span>-${fmt(ticket.saldo_aplicado)}</span></div>
                        <div class="fila"><span>Restante pagado</span><span>${fmt(ticket.restante_pagado)}</span></div>
                    `
                    : ""
            }
            <div class="fila pago"><span>Forma de pago</span><span>${escapeHtml(labelPago(ticket.forma_pago))}</span></div>
            ${
                ticket.forma_pago === "efectivo"
                    ? `
                        <div class="fila"><span>Recibido</span><span>${fmt(ticket.monto_recibido)}</span></div>
                        <div class="fila"><span>Cambio</span><span>${fmt(ticket.cambio)}</span></div>
                    `
                    : ""
            }
        </section>

        <section class="pie">
            <div class="gracias">Gracias por su compra</div>
            <div class="muted">Conserve este ticket</div>
            <div class="codigo">${escapeHtml(ticket.folio)}</div>
        </section>
    </main>
</body>
</html>`;
}

function fmt(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
        minimumFractionDigits: 2,
    }).format(Number(v ?? 0));
}

function fmtCantidad(v) {
    const n = Number(v ?? 0);
    return Number.isInteger(n) ? String(n) : n.toFixed(3);
}

function fmtFecha(v) {
    return new Date(v).toLocaleString("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function labelPago(v) {
    const labels = {
        efectivo: "Efectivo",
        credito: "Credito",
        transferencia: "Transferencia",
        tarjeta: "Tarjeta",
    };

    return labels[v] ?? v ?? "-";
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}
