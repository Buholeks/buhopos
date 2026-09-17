const byte = (value) => {
    if (!Number.isInteger(value) || value < 0 || value > 255) throw new Error('Byte ESC/POS inválido.');
    return String.fromCharCode(value);
};
export const initialize = () => '\x1b@';
export const lineFeed = () => '\x0a';
export const feed = (lines) => '\x1bd' + byte(lines);
export const partialCut = () => '\x1dV\x01';
export const fullCut = () => '\x1dV\x00';
export const emphasis = (enabled) => '\x1bE' + byte(enabled ? 1 : 0);
export function cashDrawerPulse(pin = 0, on = 25, off = 250) {
    if (![0, 1].includes(pin)) throw new Error('Pin de cajón inválido.');
    return '\x1bp' + byte(pin) + byte(on) + byte(off);
}

// ASCII deliberado para diagnóstico: evita depender de tablas de caracteres y
// elimina controles provenientes del nombre de impresora. El cajón nunca se usa.
const ascii = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^\x20-\x7e]/g, '?');
export function crearDiagnostico(config, date = new Date()) {
    return initialize() + 'BUHOPOS\nPrueba ESC/POS\n\nImpresora: '
        + ascii(config.printerName) + '\n' + ascii(date.toLocaleString('es-MX'))
        + '\n\n--------------------------------\nTexto normal\n'
        + emphasis(true) + 'Texto enfatizado\n' + emphasis(false)
        + '--------------------------------\n\nPRUEBA COMPLETADA\n'
        + finalizar(config);
}
export function finalizar(config) {
    return feed(config.feedAfterPrint) + (config.autoCut ? (config.cutType === 'full' ? fullCut() : partialCut()) : '');
}
export function crearPruebaCorte(config) {
    return initialize() + lineFeed() + finalizar({ ...config, autoCut: true });
}
export function aHex(bytes) {
    return Array.from(bytes, char => byte(char.charCodeAt(0)).charCodeAt(0).toString(16).padStart(2, '0')).join('');
}
