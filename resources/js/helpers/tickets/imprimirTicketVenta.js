import { imprimirDocumento } from "../printing/printerService.js";
import { obtenerPrinterConfig } from "../printing/printerConfig.js";
import { crearHtmlTicket } from "../printing/ticketRenderer.js";
import http from "@/lib/http";
import { toastWarning, swal } from "@/lib/alert";

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

export { crearHtmlTicket } from "../printing/ticketRenderer.js";

export async function imprimirTicketVenta(ticket, impresoraQz = null, config = obtenerConfigTicket()) {
    const terminal = obtenerPrinterConfig(impresoraQz);
    const result = await imprimirDocumento({
        renderHtml: (onWarning) => crearHtmlTicket(ticket, config, { onWarning }),
        config: terminal,
        paperWidth: Number(config?.ancho_mm ?? 80),
    });
    if (result.warnings?.length) {
        const message = 'El diseño tiene elementos fuera de sus cajas. Se conservó su posición; revisa los bordes y textos impresos.';
        // Un toast SweetAlert reemplazaría el diálogo activo de venta/cambio.
        if (swal.isVisible()) swal.update({ footer: message });
        else toastWarning(message);
    }
    return result;
}
