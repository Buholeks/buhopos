// Origen del precio cobrado en una línea de venta (venta_detalles), para consultas y reportes.
// "manual" = precio capturado a mano en el POS: el POS exige motivo_precio en ese caso.

export function esPrecioManual(detalle) {
    return Boolean(String(detalle?.motivo_precio ?? "").trim());
}

export function infoPrecioLinea(detalle) {
    const aplicado = Number(detalle?.precio_aplicado ?? detalle?.precio_venta ?? 0);
    const referencia = Number(detalle?.precio_lista_original ?? aplicado);
    const diferencia = round2(aplicado - referencia);

    return {
        manual: esPrecioManual(detalle),
        lista: detalle?.lista_precio_usada ?? null,
        motivo: detalle?.motivo_precio ?? null,
        aplicado,
        referencia,
        diferencia,
        porcentaje: referencia > 0 ? round2((diferencia / referencia) * 100) : null,
    };
}

function round2(v) {
    return Math.round(v * 100) / 100;
}
