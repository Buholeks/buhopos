import { initialize, finalizar, rasterImage } from './escpos.js';

const CHUNK = 8192;

// String.fromCharCode.apply revienta con arrays enormes; se trocea.
function bytesABinario(uint8) {
    let out = '';
    for (let i = 0; i < uint8.length; i += CHUNK) {
        out += String.fromCharCode.apply(null, uint8.subarray(i, i + CHUNK));
    }
    return out;
}

// Convierte RGBA (como ImageData) a 1 bit por punto. Alfa bajo = blanco (sin tinta).
export function empaquetarMonocromo({ width, height, data }, umbral = 200) {
    if (!Number.isInteger(width) || width < 1 || !Number.isInteger(height) || height < 1) {
        throw new Error('Dimensiones de imagen inválidas.');
    }
    const widthBytes = Math.ceil(width / 8);
    const bytes = new Uint8Array(widthBytes * height);
    for (let y = 0; y < height; y++) {
        const filaBase = y * width * 4;
        const filaBytes = y * widthBytes;
        for (let x = 0; x < width; x++) {
            const i = filaBase + x * 4;
            const luminancia = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
            if (data[i + 3] > 64 && luminancia < umbral) {
                bytes[filaBytes + (x >> 3)] |= 0x80 >> (x & 7);
            }
        }
    }
    return { widthBytes, height, bytes };
}

export function trocear({ widthBytes, height, bytes }, blockHeight = 128) {
    const alto = Math.max(1, Math.round(blockHeight) || 128);
    const bloques = [];
    for (let y = 0; y < height; y += alto) {
        const filas = Math.min(alto, height - y);
        bloques.push({ widthBytes, height: filas, bytes: bytes.subarray(y * widthBytes, (y + filas) * widthBytes) });
    }
    return bloques;
}

export function imagenAEscpos(bitmap, blockHeight) {
    return trocear(bitmap, blockHeight)
        .map((bloque) => rasterImage(bloque.widthBytes, bloque.height, bytesABinario(bloque.bytes)))
        .join('');
}

export function ticketAEscpos(bitmap, config) {
    return initialize() + imagenAEscpos(bitmap, config?.raster?.blockHeight) + finalizar(config);
}

// Captura el elemento ya maquetado por el navegador (no reimplementa el layout:
// lee las cajas ya calculadas) y lo escala al DPI de la impresora. 1mm CSS = 96/25.4 px.
// El ticket puede diseñarse un poco más ancho que lo que el cabezal imprime de verdad
// (p. ej. 73mm de zona interior en un papel de 80mm); si no se limita a printableWidth
// (72mm por defecto: el máximo típico y seguro en térmicas ESC/POS de 80mm), cada línea
// queda unos puntos más ancha que el buffer del cabezal, el desfase se acumula y el
// final del ticket —incluido el corte— llega corrupto.
export async function capturarBitmap(elemento, { dpi = 203, printableWidth } = {}) {
    const { default: html2canvas } = await import('html2canvas');
    const anchoRenderMm = elemento.getBoundingClientRect().width * 25.4 / 96;
    const anchoObjetivoMm = printableWidth ? Math.min(anchoRenderMm, Number(printableWidth)) : anchoRenderMm;
    const escala = (anchoObjetivoMm / anchoRenderMm) * (Math.max(1, Number(dpi) || 203) / 96);
    const canvas = await html2canvas(elemento, { scale: escala, backgroundColor: '#ffffff', useCORS: true, logging: false });
    const { width, height } = canvas;
    if (!width || !height) throw new Error('No se pudo capturar el ticket para rasterizar.');
    const { data } = canvas.getContext('2d').getImageData(0, 0, width, height);
    return empaquetarMonocromo({ width, height, data });
}
