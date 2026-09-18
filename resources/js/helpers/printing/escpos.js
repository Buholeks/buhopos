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

// GS v 0: imagen raster monocromo. widthBytes*height debe caber en bytes.length;
// cada bit es un punto (1 = tinta), empaquetado MSB primero por fila.
export function rasterImage(widthBytes, height, bytes) {
    if (!Number.isInteger(widthBytes) || widthBytes < 1 || widthBytes > 0xffff) throw new Error('Ancho de imagen ESC/POS inválido.');
    if (!Number.isInteger(height) || height < 1 || height > 0xffff) throw new Error('Alto de imagen ESC/POS inválido.');
    if (bytes.length !== widthBytes * height) throw new Error('El tamaño de los datos no corresponde al ancho/alto declarados.');
    const le16 = (n) => byte(n & 0xff) + byte((n >> 8) & 0xff);
    return '\x1dv0' + byte(0) + le16(widthBytes) + le16(height) + bytes;
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
