import { defineStore } from "pinia";
import { computed, reactive, ref } from "vue";
import http from "@/lib/http";

export const useVentaPosStore = defineStore("ventaPos", () => {
    const guardando = ref(false);

    const cliente = ref(null);
    const clienteId = ref(null);

    const form = reactive({
        folio: "",
        fecha: new Date().toISOString().slice(0, 10),
        descuento: 0,
        notas: "",
    });

    const cobro = reactive({
        visible: false,
        vendedor_id: null,
        vendedor: null,
        forma_pago: "efectivo",
        monto_recibido: "",
        notas: "",
    });

    const detalles = ref([]);
    let keyCounter = 0;

    const subtotal = computed(() =>
        detalles.value.reduce((acc, d) => acc + (Number(d.subtotal) || 0), 0),
    );

    const total = computed(() =>
        Math.max(0, subtotal.value - Number(form.descuento || 0)),
    );

    const cambio = computed(() => {
        if (cobro.forma_pago !== "efectivo") return 0;
        return Math.max(0, Number(cobro.monto_recibido || 0) - total.value);
    });

    const pagoInsuficiente = computed(() => {
        if (cobro.forma_pago !== "efectivo") return false;
        return Number(cobro.monto_recibido || 0) < total.value;
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
        const _idkey = r.id ? `v:${r.id}` : `p:${r.producto_id}`;
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
            forma_pago: "efectivo",
            monto_recibido: "",
            notas: "",
        });
    }

    function abrirCobro() {
        detalles.value.forEach(normalizeLinea);
        cobro.visible = true;
    }

    function cerrarCobro() {
        cobro.visible = false;
    }

    function resetVenta() {
        detalles.value = [];
        clearCliente();

        Object.assign(form, {
            folio: "",
            fecha: new Date().toISOString().slice(0, 10),
            descuento: 0,
            notas: "",
        });

        resetCobro();
    }

    async function guardarVenta() {
        guardando.value = true;

        try {
            const payload = {
                folio: form.folio || null,
                fecha: form.fecha,
                cliente_id: clienteId.value,
                vendedor_id: cobro.vendedor_id,
                forma_pago: cobro.forma_pago,
                descuento: Number(form.descuento || 0),
                notas: cobro.notas || form.notas || null,
                monto_recibido:
                    cobro.forma_pago === "efectivo"
                        ? Number(cobro.monto_recibido || 0)
                        : null,
                cambio:
                    cobro.forma_pago === "efectivo"
                        ? Number(cambio.value || 0)
                        : 0,
                detalles: detalles.value.map((d) => ({
                    producto_id: d.producto_id,
                    variante_id: d.variante_id,
                    serie_id: d.serie_id ?? null,
                    cantidad: parseInt(d.cantidad, 10),
                    precio_venta: Number(d.precio_venta),
                    motivo_precio: d.motivo_precio ?? null,
                    era_exhibido: d.era_exhibido ?? false,
                })),
            };

            await http.post("/api/ventas", payload);

            resetVenta();

            return { ok: true };
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

    return {
        guardando,
        cliente,
        clienteId,
        form,
        cobro,
        detalles,
        subtotal,
        total,
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
        guardarVenta,
    };
});