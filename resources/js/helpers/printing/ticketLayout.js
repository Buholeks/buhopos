export function altoZona(elementos = [], minimo = 0) {
    return Math.max(Number(minimo) || 0, ...elementos.map(el => Number(el.y) + Number(el.alto)));
}

export function validarZona(elementos = [], ancho) {
    for (const el of elementos) {
        const valores = [el.x, el.y, el.ancho, el.alto].map(Number);
        if (!valores.every(Number.isFinite) || valores[0] < 0 || valores[1] < 0
            || valores[2] <= 0 || valores[3] <= 0 || valores[0] + valores[2] > ancho + 0.01) {
            throw new Error('Un elemento sale del ancho del ticket o tiene dimensiones inválidas. Ajusta el diseñador antes de imprimir.');
        }
    }
}
