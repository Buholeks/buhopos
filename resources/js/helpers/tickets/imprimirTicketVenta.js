import { imprimirDocumento } from "../printing/printerService.js";
import { obtenerPrinterConfig } from "../printing/printerConfig.js";
import { crearHtmlTicket } from "../printing/ticketRenderer.js";
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

export { crearHtmlTicket } from "../printing/ticketRenderer.js";

export async function imprimirTicketVenta(ticket, impresoraQz = null, config = obtenerConfigTicket()) {
    return imprimirDocumento({
        html: crearHtmlTicket(ticket, config),
        config: obtenerPrinterConfig(impresoraQz),
        paperWidth: Number(config.ancho_mm ?? 80),
    });
}
