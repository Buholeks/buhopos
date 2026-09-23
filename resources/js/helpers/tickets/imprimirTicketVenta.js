import { imprimirDocumento } from "../printing/printerService.js";
import { obtenerPrinterConfig } from "../printing/printerConfig.js";
import { crearHtmlTicket } from "../printing/ticketRenderer.js";
import { obtenerConfigTicket, cargarConfigTicketDesdeServidor } from './ticketConfigStore.js';
import { toastWarning, swal } from "@/lib/alert";
import { printError } from '../printing/printErrors.js';

export { obtenerConfigTicket, cargarConfigTicketDesdeServidor, guardarConfigTicket } from './ticketConfigStore.js';

export { crearHtmlTicket } from "../printing/ticketRenderer.js";

export async function imprimirTicketVenta(ticket, impresoraQz = null, config = undefined) {
    const terminal = obtenerPrinterConfig(impresoraQz);
    let browserWindow;
    let result;
    try {
    if (config === undefined) {
        // Reservar el popup antes de la red mantiene el gesto del usuario.
        if (terminal.mode === 'browser') {
            browserWindow = window.open('', 'ticket_venta', 'width=420,height=720');
            if (!browserWindow) throw printError('POPUP_BLOCKED', 'Permite ventanas emergentes para imprimir el ticket.');
        }
        config = await cargarConfigTicketDesdeServidor({ forzar: false });
    }
    result = await imprimirDocumento({
        renderHtml: (onWarning) => crearHtmlTicket(ticket, config, { onWarning }),
        config: terminal,
        paperWidth: Number(config?.ancho_mm ?? 80),
        browserWindow,
    });
    } catch (error) { browserWindow?.close(); throw error; }
    if (result.warnings?.length) {
        const message = 'El ticket se imprimió, pero algunos elementos pueden quedar cerca del borde o tener texto ajustado.';
        console.warn('[ticket] Avisos de impresión:', result.warnings);
        // Un toast SweetAlert reemplazaría el diálogo activo de venta/cambio.
        if (swal.isVisible()) swal.update({ footer: message });
        else toastWarning(message);
    }
    return result;
}
