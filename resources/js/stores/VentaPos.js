import { defineStore } from "pinia";
import { computed, reactive, ref, watch } from "vue";
import http from "@/lib/http";

function nuevaLineaPago(formaPago = "efectivo", monto = 0) {
    return {
        forma_pago: formaPago,
        monto,
        cuenta_bancaria_id: "",
        terminal_pago_id: "",
        monto_recibido: "",
    };
}

function fechaLocal() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function generarIdempotencyKey() {
    if (typeof crypto !== "undefined" && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === "x" ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

export const useVentaPosStore = defineStore("VentaPos", () => {
    const guardando = ref(false);
    const ultimaVenta = ref(cargarUltimaVenta());

    const cliente = ref(null);
    const clienteId = ref(null);

    const form = reactive({
        folio: "",
        fecha: fechaLocal(),
        descuento: 0,
        notas: "",
    });

    const cobro = reactive({
        visible: false,
        vendedor_id: null,
        vendedor: null,
        pagos: [],
        saldo_disponible: 0,
        saldo_aplicado: 0,
        notas: "",
    });

    const cuentasBancarias = ref([]);
    const terminalesPago = ref([]);

    async function cargarCuentasBancarias() {
        try {
            const { data } = await http.get("/api/cuentas-bancarias", { params: { activo: 1 } });
            cuentasBancarias.value = data;
        } catch {
            cuentasBancarias.value = [];
        }
    }

    async function cargarTerminalesPago() {
        try {
            const { data } = await http.get("/api/terminales-pago", { params: { activo: 1 } });
            terminalesPago.value = data;
        } catch {
            terminalesPago.value = [];
        }
    }

    // Se cargan desde ahora (no hasta abrir el cobro) para que ya estén
    // disponibles si el cajero elige tarjeta/transferencia justo al abrir el
    // modal, en vez de mostrar el select vacío mientras llega la petición.
    cargarCuentasBancarias();
    cargarTerminalesPago();

    const detalles = ref([]);
    let keyCounter = 0;

    // Se conserva durante los reintentos de un mismo intento de cobro (p. ej. si
    // se cae el internet justo al confirmar) para que el backend detecte el envío
    // duplicado y devuelva la venta ya guardada en vez de duplicarla o fallar por
    // stock insuficiente. Solo se renueva al abrir un cobro nuevo.
    const idempotencyKey = ref(generarIdempotencyKey());

    const subtotal = computed(() =>
        detalles.value.reduce((acc, d) => acc + (Number(d.subtotal) || 0), 0),
    );

    const total = computed(() =>
        Math.max(0, subtotal.value - Number(form.descuento || 0)),
    );

    const saldoAplicable = computed(() =>
        Math.min(
            Number(cobro.saldo_disponible || 0),
            Number(total.value || 0),
        ),
    );

    const totalACobrar = computed(() =>
        Math.max(0, total.value - Number(cobro.saldo_aplicado || 0)),
    );

    const montoAsignado = computed(() =>
        cobro.pagos.reduce((acc, p) => acc + (Number(p.monto) || 0), 0),
    );

    const restante = computed(
        () => Math.round((totalACobrar.value - montoAsignado.value) * 100) / 100,
    );

    const cambio = computed(() =>
        cobro.pagos
            .filter((p) => p.forma_pago === "efectivo")
            .reduce(
                (acc, p) =>
                    acc + Math.max(0, Number(p.monto_recibido || 0) - Number(p.monto || 0)),
                0,
            ),
    );

    const pagoInsuficiente = computed(() =>
        cobro.pagos.some(
            (p) =>
                p.forma_pago === "efectivo" &&
                p.monto_recibido !== "" &&
                Number(p.monto_recibido || 0) < Number(p.monto || 0),
        ),
    );

    // Mientras haya una sola línea de pago, se mantiene sincronizada con el
    // total a cobrar (mismo flujo simple de siempre). Al dividir el pago en
    // varias líneas, cada una se ajusta manualmente.
    watch(totalACobrar, (nuevo) => {
        if (cobro.pagos.length === 1) {
            cobro.pagos[0].monto = nuevo;
        }
    });

    const hayExcedido = computed(() =>
        detalles.value.some(
            (d) => Number(d.cantidad) > Number(d.stock_disponible),
        ),
    );

    function normalizeLinea(d) {
        const c = parseInt(d.cantidad || 1, 10);
        d.cantidad = Number.isFinite(c) ? Math.max(1, c) : 1;

        const p = Number(d.precio_venta || 0);
        d.precio_venta = Number.isFinite(p) ? Math.max(0, p) : 0;

        d.subtotal = d.cantidad * d.precio_venta;
    }

    function setCliente(payload) {
        cliente.value = payload ?? null;
        clienteId.value = payload?.id ?? null;
    }

    function clearCliente() {
        cliente.value = null;
        clienteId.value = null;
        cobro.saldo_disponible = 0;
        cobro.saldo_aplicado = 0;
    }

    function setVendedor(payload) {
        cobro.vendedor = payload ?? null;
        cobro.vendedor_id = payload?.id ?? null;
    }

    function clearVendedor() {
        cobro.vendedor = null;
        cobro.vendedor_id = null;
    }

    function agregarDetalle(r) {
        const _idkey = r.pedido_detalle_id
            ? `pedido:${r.pedido_detalle_id}`
            : r.id
                ? `v:${r.id}`
                : `p:${r.producto_id}`;
        const existe = detalles.value.find((d) => d._idkey === _idkey);

        if (existe) {
            if (Number(existe.cantidad) < Number(existe.stock_disponible)) {
                existe.cantidad = Number(existe.cantidad) + 1;
                normalizeLinea(existe);
            }
            return existe;
        }

        const precioInicial =
            r.precio_venta != null && Number(r.precio_venta) > 0
                ? Number(r.precio_venta)
                : Number(r.precio1 ?? 0);

        const det = {
            _key: ++keyCounter,
            _idkey,
            pedido_id: r.pedido_id ?? null,
            pedido_detalle_id: r.pedido_detalle_id ?? null,
            variante_id: r.id ?? null,
            producto_id: r.producto_id,
            serie_id: null,
            identificador: null,
            nombre: r.nombre,
            nombre_variante: r.nombre_variante ?? null,
            codigo: r.codigo,
            imagen_url: r.imagen_url,
            precio_venta: precioInicial,
            precio_lista_sel:
                r.precio1 != null && Number(r.precio1) > 0 ? "P1" : null,
            precio1: r.precio1 ?? null,
            precio2: r.precio2 ?? null,
            precio3: r.precio3 ?? null,
            precio4: r.precio4 ?? null,
            precio5: r.precio5 ?? null,
            cantidad: 1,
            cantidad_fija: false,
            stock_disponible: r.stock ?? 0,
            inventario_exhibido: r.exhibido ?? false,
            subtotal: 0,
            motivo_precio: null,
            era_exhibido: false,
            tiene_series: false,
        };

        normalizeLinea(det);
        detalles.value.unshift(det);
        return det;
    }

    function agregarDetalleConSerie(serieData) {
        const yaExiste = detalles.value.find(
            (d) => d._idkey === `serie:${serieData.serie_id}`,
        );
        if (yaExiste) return yaExiste;

        const det = {
            _key: ++keyCounter,
            _idkey: `serie:${serieData.serie_id}`,
            variante_id: serieData.variante_id,
            producto_id: serieData.producto_id,
            serie_id: serieData.serie_id,
            identificador: serieData.identificador,
            nombre: serieData.nombre,
            nombre_variante: serieData.sku ?? null,
            codigo: serieData.codigo,
            imagen_url: serieData.imagen_url,
            precio_venta: Number(serieData.precio_venta ?? 0),
            precio_lista_sel: null,
            precio1: serieData.precio1 ?? null,
            precio2: serieData.precio2 ?? null,
            precio3: serieData.precio3 ?? null,
            precio4: serieData.precio4 ?? null,
            precio5: serieData.precio5 ?? null,
            cantidad: 1,
            cantidad_fija: true,
            stock_disponible: 1,
            inventario_exhibido: serieData.exhibido ?? false,
            subtotal: 0,
            motivo_precio: null,
            era_exhibido: false,
            tiene_series: true,
        };

        normalizeLinea(det);
        detalles.value.unshift(det);
        return det;
    }

    function quitarDetalle(idx) {
        detalles.value.splice(idx, 1);
    }

    function recalcularLinea(idx) {
        const d = detalles.value[idx];
        if (!d) return;
        normalizeLinea(d);
    }

    function resetCobro() {
        Object.assign(cobro, {
            visible: false,
            vendedor_id: null,
            vendedor: null,
            pagos: [],
            saldo_disponible: 0,
            saldo_aplicado: 0,
            notas: "",
        });
    }

    function abrirCobro() {
        detalles.value.forEach(normalizeLinea);
        idempotencyKey.value = generarIdempotencyKey();
        cobro.visible = true;
        cobro.pagos = [nuevaLineaPago("efectivo", totalACobrar.value)];

        if (!cuentasBancarias.value.length) cargarCuentasBancarias();
        if (!terminalesPago.value.length) cargarTerminalesPago();
    }

    // En efectivo no se captura "monto" por separado: el monto asignado a la
    // línea se calcula a partir de lo recibido, topado a lo que aún falta
    // cubrir entre las demás líneas (para no exceder el total ni robarle
    // cobertura a otro método).
    function recalcularMontoEfectivo(idx) {
        const linea = cobro.pagos[idx];
        if (!linea || linea.forma_pago !== "efectivo") return;

        const otras = cobro.pagos.reduce(
            (acc, p, i) => (i === idx ? acc : acc + (Number(p.monto) || 0)),
            0,
        );
        const disponible = Math.max(0, totalACobrar.value - otras);
        linea.monto = Math.min(Number(linea.monto_recibido) || 0, disponible);
    }

    function agregarLineaPago() {
        const usados = cobro.pagos.map((p) => p.forma_pago);
        const disponible =
            ["efectivo", "tarjeta", "transferencia"].find((m) => !usados.includes(m)) ??
            "efectivo";

        // Antes de sumar una línea nueva, se ajusta el monto de cualquier
        // línea de efectivo existente (mientras había una sola línea, su
        // monto seguía fijo al total y no reflejaba lo recibido).
        cobro.pagos.forEach((_, i) => recalcularMontoEfectivo(i));

        cobro.pagos.push(nuevaLineaPago(disponible, Math.max(0, restante.value)));
    }

    function quitarLineaPago(idx) {
        if (cobro.pagos.length <= 1) return;
        cobro.pagos.splice(idx, 1);
    }

    function actualizarLineaPago(idx, campo, valor) {
        const linea = cobro.pagos[idx];
        if (!linea) return;

        linea[campo] = valor;

        if (cobro.pagos.length <= 1) return;

        if (campo === "monto_recibido" || campo === "forma_pago") {
            recalcularMontoEfectivo(idx);
            return;
        }

        // El "Monto" de una línea que no es efectivo se edita a mano y afecta
        // cuánto queda disponible para las demás; se resincroniza cualquier
        // línea de efectivo con ese nuevo disponible.
        if (campo === "monto") {
            cobro.pagos.forEach((p, i) => {
                if (p.forma_pago === "efectivo") recalcularMontoEfectivo(i);
            });
        }
    }

    function cerrarCobro() {
        cobro.visible = false;
    }

    function resetVenta() {
        detalles.value = [];
        clearCliente();

        Object.assign(form, {
            folio: "",
            fecha: fechaLocal(),
            descuento: 0,
            notas: "",
        });

        resetCobro();
        idempotencyKey.value = generarIdempotencyKey();
    }

    async function guardarVenta() {
        guardando.value = true;

        try {
            const payload = {
                folio: form.folio || null,
                fecha: form.fecha,
                cliente_id: clienteId.value,
                vendedor_id: cobro.vendedor_id,
                pagos: cobro.pagos.map((p) => ({
                    forma_pago: p.forma_pago,
                    monto: Number(p.monto || 0),
                    cuenta_bancaria_id:
                        p.forma_pago === "transferencia" ? p.cuenta_bancaria_id || null : null,
                    terminal_pago_id:
                        p.forma_pago === "tarjeta" ? p.terminal_pago_id || null : null,
                    monto_recibido:
                        p.forma_pago === "efectivo"
                            ? Number(p.monto_recibido || p.monto || 0)
                            : null,
                })),
                descuento: Number(form.descuento || 0),
                saldo_aplicado: Number(cobro.saldo_aplicado || 0),
                notas: cobro.notas || form.notas || null,
                idempotency_key: idempotencyKey.value,
                detalles: detalles.value.map((d) => ({
                    producto_id: d.producto_id,
                    variante_id: d.variante_id,
                    serie_id: d.serie_id ?? null,
                    pedido_id: d.pedido_id ?? null,
                    pedido_detalle_id: d.pedido_detalle_id ?? null,
                    cantidad: parseInt(d.cantidad, 10),
                    precio_venta: Number(d.precio_venta),
                    lista_precio_usada: d.precio_lista_sel ?? null,
                    motivo_precio: d.motivo_precio ?? null,
                    era_exhibido: d.era_exhibido ?? false,
                })),
            };

            const { data } = await http.post("/api/ventas", payload);

            setUltimaVenta(data);
            resetVenta();

            return { ok: true, venta: data };
        } catch (error) {
            return {
                ok: false,
                error,
                message:
                    error.response?.data?.message ??
                    "Ocurrió un error inesperado",
                campo: error.response?.data?.campo ?? null,
            };
        } finally {
            guardando.value = false;
        }
    }

    function setUltimaVenta(venta) {
        ultimaVenta.value = venta ?? null;

        try {
            if (venta) {
                localStorage.setItem("buho_pos_ultima_venta", JSON.stringify(venta));
            } else {
                localStorage.removeItem("buho_pos_ultima_venta");
            }
        } catch {
            // Si el navegador bloquea localStorage, se conserva en memoria.
        }
    }

    return {
        guardando,
        ultimaVenta,
        cliente,
        clienteId,
        form,
        cobro,
        cuentasBancarias,
        terminalesPago,
        detalles,
        subtotal,
        total,
        saldoAplicable,
        totalACobrar,
        montoAsignado,
        restante,
        cambio,
        pagoInsuficiente,
        hayExcedido,
        normalizeLinea,
        setCliente,
        clearCliente,
        setVendedor,
        clearVendedor,
        agregarDetalle,
        agregarDetalleConSerie,
        quitarDetalle,
        recalcularLinea,
        abrirCobro,
        cerrarCobro,
        resetCobro,
        resetVenta,
        agregarLineaPago,
        quitarLineaPago,
        actualizarLineaPago,
        guardarVenta,
        setUltimaVenta,
    };
});

function cargarUltimaVenta() {
    try {
        const raw = localStorage.getItem("buho_pos_ultima_venta");
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}
