export function crearTicketVenta(venta) {
    const detalles = Array.isArray(venta?.detalles) ? venta.detalles : [];
    const productos = detalles.map((detalle) => {
        const cantidad = Number(detalle.cantidad ?? 0);
        const precioAplicado = Number(detalle.precio_aplicado ?? detalle.precio_venta ?? 0);
        const descuento = Number(detalle.descuento ?? 0);
        const descuentoUnitario = cantidad > 0 ? descuento / cantidad : 0;
        const precioOriginal = Number(
            detalle.precio_lista_original ?? precioAplicado + descuentoUnitario,
        );
        const importe = Number(detalle.subtotal ?? cantidad * precioAplicado);

        return {
            cantidad,
            nombre: detalle.producto_nombre ?? detalle.producto?.nombre ?? "Producto",
            variante: detalle.variante_nombre ?? nombreVariante(detalle),
            identificador: detalle.serie?.imei ?? detalle.serie?.serie ?? null,
            precio_unitario: precioOriginal,
            precio_aplicado: precioAplicado,
            lista_precio_usada: detalle.lista_precio_usada ?? null,
            descuento,
            importe,
        };
    });
    const descuentoPrecios = productos.reduce(
        (acc, producto) => acc + Number(producto.descuento || 0),
        0,
    );
    const subtotalLista = productos.reduce(
        (acc, producto) =>
            acc + Number(producto.cantidad || 0) * Number(producto.precio_unitario || 0),
        0,
    );

    return {
        folio: venta?.folio ?? "-",
        reimpresion: Boolean(venta?.reimpresion),
        fecha: venta?.created_at ?? venta?.fecha ?? new Date().toISOString(),
        empresa: venta?.empresa ?? null,
        sucursal: venta?.sucursal ?? null,
        usuario: venta?.user ?? null,
        vendedor: venta?.vendedor ?? null,
        cliente: venta?.cliente ?? null,
        forma_pago: venta?.forma_pago ?? "-",
        subtotal: Number(venta?.subtotal ?? 0),
        subtotal_lista: subtotalLista,
        descuento_precios: descuentoPrecios,
        descuento: Number(venta?.descuento ?? 0),
        total: Number(venta?.total ?? 0),
        monto_recibido: Number(venta?.monto_recibido ?? 0),
        cambio: Number(venta?.cambio ?? 0),
        notas: venta?.notas ?? null,
        productos,
    };
}

function nombreVariante(detalle) {
    const atributos = Array.isArray(detalle.variante?.atributos)
        ? detalle.variante.atributos
        : [];
    const nombreAtributos = atributos
        .map((item) => {
            const tipo = item.tipo_atributo?.nombre ?? item.tipoAtributo?.nombre;
            const valor = item.atributo?.valor;

            if (!valor) return null;
            return tipo ? `${tipo}: ${valor}` : valor;
        })
        .filter(Boolean)
        .join(" / ");

    if (nombreAtributos) return nombreAtributos;
    if (detalle.nombre_variante) return detalle.nombre_variante;
    if (detalle.variante?.nombre_variante) return detalle.variante.nombre_variante;
    if (detalle.variante?.sku) return detalle.variante.sku;
    if (detalle.variante?.codigo_barras) return detalle.variante.codigo_barras;
    return null;
}
