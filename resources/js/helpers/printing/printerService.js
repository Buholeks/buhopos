import { enviarQz } from '../qzTray.js';
import { normalizarPrinterConfig } from './printerConfig.js';
import { aHex } from './escpos.js';
import { capturarBitmap, ticketAEscpos } from './raster.js';
import { printError, asPrintError } from './printErrors.js';

async function esperarRecursos(doc, onWarning) {
    let timer;
    try {
        void doc.body.offsetHeight; // inicia layout y carga de fuentes antes de fonts.ready
        await Promise.race([
            Promise.all([
                doc.fonts?.ready,
                ...Array.from(doc.images, img => img.decode()),
            ]),
            new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('No terminaron de cargar las imágenes del ticket.')), 15000); }),
        ]);
        for (const el of doc.querySelectorAll('[data-ticket-text]')) {
            if (el.firstElementChild.getBoundingClientRect().height > el.getBoundingClientRect().height + 1) {
                onWarning({ code: 'TEXT_OVERFLOW', message: 'Un texto supera la altura de su campo. Se conserva visible; revisa el resultado impreso.' });
            }
        }
    } finally { clearTimeout(timer); }
}

async function imprimirBrowser(html, onWarning) {
    const ventana = window.open('', 'ticket_venta', 'width=420,height=720');
    if (!ventana) throw printError('POPUP_BLOCKED', 'Permite ventanas emergentes para imprimir el ticket.');
    try {
        ventana.document.open();
        ventana.document.write(html);
        ventana.document.close();
        await esperarRecursos(ventana.document, onWarning);
        ventana.onafterprint = () => ventana.close();
        ventana.focus();
        ventana.print();
        return { status: 'dialog-opened' };
    } catch (error) { ventana.close(); throw asPrintError(error); }
}

async function enviarTrabajo(...args) {
    try { return await enviarQz(...args); }
    catch (error) {
        if (error?.code) throw error;
        throw printError('QZ_SEND_FAILED', `${error.message ?? error}. Revisa la cola y el papel antes de reintentar; el trabajo podría haber sido recibido.`, error);
    }
}

// Serializa trabajos QZ en esta pestaña. Nunca reintenta ni cambia de transporte.
let cola = Promise.resolve();
export async function imprimirDocumento({ html, renderHtml, raw, config, paperWidth }) {
    const cfg = normalizarPrinterConfig(config);
    const warnings = [];
    const onWarning = warning => { warnings.push(warning); console.warn('[ticket]', warning); };
    const generarHtml = () => {
        try { return renderHtml ? renderHtml(onWarning) : html; }
        catch (error) { throw asPrintError(error); }
    };
    if (!cfg.enabled) throw printError('PRINT_ERROR', 'La impresión está deshabilitada.');
    if (cfg.mode === 'browser') {
        html = generarHtml();
        if (!html) throw printError('PRINT_ERROR', 'La prueba RAW requiere QZ Tray.');
        return { ...await imprimirBrowser(html, onWarning), warnings };
    }
    if (!cfg.printerName) throw printError('PRINTER_NOT_FOUND', 'Selecciona una impresora QZ.');
    if (cfg.mode === 'qz-raw' && typeof raw !== 'string') {
        throw printError('PRINT_ERROR', 'RAW solo está habilitado para diagnósticos; el ticket gráfico aún no está habilitado.');
    }
    const ejecutar = async () => {
        if (cfg.mode === 'qz-raw') {
            return enviarTrabajo(cfg.printerName, { copies: 1, forceRaw: cfg.forceRaw }, [
                { type: 'raw', format: 'command', flavor: 'hex', data: aHex(raw) },
            ]);
        }
        html = generarHtml();
        if (!html) throw printError('PRINT_ERROR', 'Falta el HTML del ticket.');
        const frame = document.createElement('iframe');
        frame.style.cssText = 'position:fixed;left:-10000px;width:1000px;height:1000px;visibility:hidden;';
        document.body.appendChild(frame);
        try {
            const doc = frame.contentDocument;
            doc.open(); doc.write(html); doc.close();
            await esperarRecursos(doc, onWarning);
            if (cfg.mode === 'qz-raster') {
                // Rasteriza el ticket ya maquetado por el navegador y lo envía como
                // imagen ESC/POS + corte propios: evita depender del driver de Windows.
                const elemento = doc.querySelector('.ticket') ?? doc.body;
                const bitmap = await capturarBitmap(elemento, { dpi: cfg.dpi, printableWidth: cfg.printableWidth });
                const result = await enviarTrabajo(cfg.printerName, { copies: 1, forceRaw: cfg.forceRaw }, [
                    { type: 'raw', format: 'command', flavor: 'hex', data: aHex(ticketAEscpos(bitmap, cfg)) },
                ]);
                return { ...result, warnings };
            }
            const result = await enviarTrabajo(cfg.printerName, {
                // Conserva el tamaño clásico QZ en esta primera iteración.
                size: { width: paperWidth ?? cfg.paperWidth, height: 3000 }, units: 'mm',
                margins: 0, colorType: 'blackwhite', duplex: false, copies: 1,
                scaleContent: false, rasterize: false,
            }, [{ type: 'pixel', format: 'html', flavor: 'plain', data: html }]);
            return { ...result, warnings };
        } finally { frame.remove(); }
    };
    const trabajo = cola.then(ejecutar);
    cola = trabajo.catch(() => {});
    try { return await trabajo; }
    catch (error) {
        throw asPrintError(error);
    }
}
