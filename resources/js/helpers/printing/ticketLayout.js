import { printError } from './printErrors.js';

export function altoZona(elementos = [], minimo = 0) {
    return Math.max(Number(minimo) || 0, ...elementos.map(el => Number(el.y) + Number(el.alto)));
}

// No confundir geometría CSS con capacidades físicas de una impresora.
// Los límites del lienzo son avisos: nunca cambian coordenadas ni recortan datos.
export function numeroDiseno(value, field) {
    if ((typeof value !== 'number' && typeof value !== 'string')
        || (typeof value === 'string' && !value.trim()) || !Number.isFinite(Number(value))) {
        throw printError('INVALID_DESIGN', `${field} debe ser un número finito.`);
    }
    return Number(value);
}

export function validarZona(elementos = [], ancho, zona = 'canvas') {
    if (!Array.isArray(elementos)) throw printError('INVALID_DESIGN', `${zona}.elementos debe ser una lista.`);
    ancho = numeroDiseno(ancho, 'ancho disponible');
    if (ancho <= 0) throw printError('INVALID_DESIGN', 'El ancho disponible debe ser mayor que cero.');
    const warnings = [];
    for (const el of elementos) {
        if (!el || typeof el !== 'object' || Array.isArray(el)) {
            throw printError('INVALID_DESIGN', `Elemento corrupto en ${zona}.`);
        }
        const label = `${zona}: ${el.id ?? el.tipo ?? 'elemento'}`;
        const [x, y, width, height] = ['x', 'y', 'ancho', 'alto'].map(field => numeroDiseno(el[field], `${label}.${field}`));
        if (width <= 0 || height < 0 || (height === 0 && el.tipo !== 'separador')) {
            throw printError('INVALID_DESIGN', `${label}: ancho/alto no permiten renderizar el elemento.`);
        }
        if (!Number.isFinite(x + width) || !Number.isFinite(y + height)) {
            throw printError('INVALID_DESIGN', `${label}: las dimensiones exceden el rango numérico.`);
        }
        if (x < 0 || y < 0 || x + width > ancho) {
            warnings.push({ code: 'CANVAS_OVERFLOW', zona, id: el.id, tipo: el.tipo,
                x, y, ancho: width, alto: height, disponible: ancho, derecha: x + width,
                message: `${label}: ocupa hasta ${x + width} mm en un lienzo de ${ancho} mm. Se conserva el diseño histórico; comprueba los bordes impresos.` });
        }
    }
    return warnings;
}
