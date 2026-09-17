import { enviarQz } from '../qzTray.js';
import { normalizarPrinterConfig } from './printerConfig.js';
import { aHex } from './escpos.js';

async function esperarRecursos(doc) {
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
                throw new Error('Un texto no cabe en su campo. Aumenta su altura en el diseñador antes de imprimir.');
            }
        }
    } finally { clearTimeout(timer); }
}

async function imprimirBrowser(html) {
    const ventana = window.open('', 'ticket_venta', 'width=420,height=720');
    if (!ventana) throw new Error('No se pudo abrir la ventana de impresión. Permite ventanas emergentes.');
    try {
        ventana.document.open();
        ventana.document.write(html);
        ventana.document.close();
        await esperarRecursos(ventana.document);
        ventana.onafterprint = () => ventana.close();
        ventana.focus();
        ventana.print();
        return { status: 'dialog-opened' };
    } catch (error) { ventana.close(); throw error; }
}

// Serializa trabajos QZ en esta pestaña. Nunca reintenta ni cambia de transporte.
let cola = Promise.resolve();
export async function imprimirDocumento({ html, raw, config, paperWidth }) {
    const cfg = normalizarPrinterConfig(config);
    if (!cfg.enabled) throw new Error('La impresión está deshabilitada.');
    if (cfg.mode === 'browser') {
        if (!html) throw new Error('La prueba RAW requiere QZ Tray.');
        return imprimirBrowser(html);
    }
    if (!cfg.printerName) throw new Error('Selecciona una impresora QZ.');
    if (cfg.mode === 'qz-raw' && typeof raw !== 'string') {
        throw new Error('RAW solo está habilitado para diagnósticos; el ticket gráfico aún no está habilitado.');
    }
    const ejecutar = async () => {
        if (cfg.mode === 'qz-raw') {
            return enviarQz(cfg.printerName, { copies: 1, forceRaw: cfg.forceRaw }, [
                { type: 'raw', format: 'command', flavor: 'hex', data: aHex(raw) },
            ]);
        }
        if (!html) throw new Error('Falta el HTML del ticket.');
        const frame = document.createElement('iframe');
        frame.style.cssText = 'position:fixed;left:-10000px;width:1000px;height:1000px;visibility:hidden;';
        document.body.appendChild(frame);
        try {
            const doc = frame.contentDocument;
            doc.open(); doc.write(html); doc.close();
            await esperarRecursos(doc);
            return await enviarQz(cfg.printerName, {
                // Conserva el tamaño clásico QZ en esta primera iteración.
                size: { width: paperWidth ?? cfg.paperWidth, height: 3000 }, units: 'mm',
                margins: 0, colorType: 'blackwhite', duplex: false, copies: 1,
                scaleContent: false, rasterize: false,
            }, [{ type: 'pixel', format: 'html', flavor: 'plain', data: html }]);
        } finally { frame.remove(); }
    };
    const trabajo = cola.then(ejecutar);
    cola = trabajo.catch(() => {});
    try { return await trabajo; }
    catch (error) {
        throw new Error(`No se pudo confirmar el envío a QZ: ${error.message}. Revisa la cola y el papel antes de reintentar; el trabajo podría haber sido recibido.`);
    }
}
