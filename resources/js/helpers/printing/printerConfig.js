const KEY = 'buhopos_printer_config_v1';
const LEGACY_KEY = 'buhopos_qz_impresora_ticket';

// El diseño empresarial sigue en buhopos_ticket_config. Esta clave es local.
export function obtenerPrinterConfig(printerName = localStorage.getItem(LEGACY_KEY)) {
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch { /* defaults */ }
    // Solo qz-html/qz-raster pueden imprimir el ticket real; RAW de diagnóstico nunca se conserva aquí.
    const mode = !printerName ? 'browser' : (saved.mode === 'qz-raster' ? 'qz-raster' : 'qz-html');
    return normalizarPrinterConfig({ ...saved, printerName: printerName || '', mode });
}

export function normalizarPrinterConfig(value = {}) {
    const number = (v, fallback, min, max) => Number.isFinite(Number(v))
        ? Math.min(max, Math.max(min, Number(v))) : fallback;
    const paperWidth = number(value.paperWidth, 80, 40, 112);
    return {
        enabled: value.enabled !== false,
        printerName: String(value.printerName || ''),
        paperWidth,
        printableWidth: number(value.printableWidth, Math.min(72, paperWidth), 20, paperWidth),
        dpi: number(value.dpi, 203, 100, 600),
        mode: ['browser', 'qz-html', 'qz-raw', 'qz-raster'].includes(value.mode) ? value.mode : 'browser',
        autoCut: value.autoCut !== false,
        cutType: value.cutType === 'full' ? 'full' : 'partial',
        feedAfterPrint: Math.round(number(value.feedAfterPrint, 4, 0, 20)), // líneas, no mm
        openCashDrawer: false, // reservado; nunca se activa en esta iteración
        forceRaw: value.forceRaw === true,
        raster: { blockHeight: Math.round(number(value.raster?.blockHeight, 128, 8, 500)) },
    };
}

export function guardarPrinterConfig(value) {
    const config = normalizarPrinterConfig(value);
    localStorage.setItem(KEY, JSON.stringify(config));
    // Conserva compatibilidad con ventas, reimpresión y selección existente.
    if (config.mode !== 'browser' && config.printerName) localStorage.setItem(LEGACY_KEY, config.printerName);
    else localStorage.removeItem(LEGACY_KEY);
    return config;
}
