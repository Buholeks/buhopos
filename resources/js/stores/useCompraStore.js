import { defineStore } from "pinia";
import { computed, nextTick, reactive, ref } from "vue";
import http from "@/lib/http";
import Swal from "sweetalert2";
import { toastSuccess, toastError, toastWarning } from "@/lib/alert";

export const useCompraStore = defineStore("compra", () => {
    const proveedores = ref([]);
    const guardando = ref(false);
    const buscadorRef = ref(null);

    const form = reactive(formInicial());
    const detalles = ref([]);
    let keyCounter = 0;

    const modalCantidad = reactive({
        mostrar: false,
        item: null,
        pedidosPendientes: [],
        cargandoPedidos: false,
    });

    const modalImei = reactive({
        mostrar: false,
        item: null,
        cantidad: 1,
        precio_compra: 0,
        precio_venta: 0,
        pedido_detalle_ids: [],
    });

    const modalEditar = reactive({
        mostrar: false,
        detalle: null,
        idx: null,
    });

    const totalCompra = computed(() => {
        return detalles.value.reduce((acc, d) => {
            return acc + (Number(d.subtotal) || 0);
        }, 0);
    });

    const totalArticulos = computed(() => detalles.value.length);
    const proveedorActual = computed(() => {
        return proveedores.value.find(
            (proveedor) => Number(proveedor.id) === Number(form.proveedor_id),
        );
    });
    const saldoFavorDisponible = computed(() =>
        Math.max(0, Number(proveedorActual.value?.saldo_favor ?? 0)),
    );
    const saldoFavorAplicado = computed(() =>
        form.aplicar_saldo_favor
            ? Math.min(saldoFavorDisponible.value, totalCompra.value)
            : 0,
    );
    const restantePorPagar = computed(() =>
        Math.max(0, totalCompra.value - saldoFavorAplicado.value),
    );

    const todosLosImeis = computed(() => {
        return detalles.value.flatMap((d) => d.imeis ?? []);
    });


    function fechaLocal() {
        const d = new Date()
        const y = d.getFullYear()
        const m = String(d.getMonth() + 1).padStart(2, '0')
        const day = String(d.getDate()).padStart(2, '0')
        return `${y}-${m}-${day}`
    }


    function formInicial() {
        return {
            proveedor_id: "",
            folio: "",
            fecha: fechaLocal(),

            forma_pago: "efectivo",
            fecha_vencimiento: "",
            notas: "",
            aplicar_saldo_favor: false,
        };
    }

    function formatPrecio(v) {
        return new Intl.NumberFormat("es-MX", {
            style: "currency",
            currency: "MXN",
        }).format(Number(v ?? 0));
    }

    function setBuscadorRef(refValue) {
        buscadorRef.value = refValue;
    }

    function enfocarBuscador() {
        nextTick(() => buscadorRef.value?.focus?.());
    }

    async function cargarProveedores() {
        try {
            const { data } = await http.get("/api/proveedores");
            proveedores.value = data?.data ?? data ?? [];
        } catch {
            toastError("Error al cargar proveedores");
        } finally {
            enfocarBuscador();
        }
    }

    function actualizarForm(payload) {
        Object.assign(form, payload);
    }

    function normalizeLinea(d) {
        const cantidad = parseFloat(d.cantidad || 1);
        d.cantidad = Number.isFinite(cantidad) ? Math.max(0.001, cantidad) : 1;

        const precioCompra = Number(d.precio_compra || 0);
        d.precio_compra = Number.isFinite(precioCompra)
            ? Math.max(0, precioCompra)
            : 0;

        const precioVenta = Number(d.precio_venta || 0);
        d.precio_venta = Number.isFinite(precioVenta)
            ? Math.max(0, precioVenta)
            : 0;

        d.subtotal = d.cantidad * d.precio_compra;
    }

    function imeisDeOtrosDetalles(idx) {
        return detalles.value
            .filter((_, i) => i !== idx)
            .flatMap((d) => d.imeis ?? []);
    }

    async function seleccionarItem(item) {
        modalCantidad.item = item;
        modalCantidad.pedidosPendientes = [];
        modalCantidad.mostrar = true;

        modalCantidad.cargandoPedidos = true;
        try {
            const { data } = await http.get("/api/compras/pedidos-pendientes", {
                params: {
                    producto_id: item.producto_id,
                    variante_id: item.id ?? null,
                },
            });
            modalCantidad.pedidosPendientes = Array.isArray(data) ? data : [];
        } catch {
            modalCantidad.pedidosPendientes = [];
            toastError("No se pudieron cargar los pedidos pendientes");
        } finally {
            modalCantidad.cargandoPedidos = false;
        }
    }

    function confirmarCantidad({ cantidad, precio_compra, precio_venta, pedido_detalle_ids }) {
        modalCantidad.mostrar = false;

        const item = modalCantidad.item;
        if (!item) return;

        if (item.tiene_series) {
            modalImei.item = item;
            modalImei.cantidad = cantidad;
            modalImei.precio_compra = precio_compra;
            modalImei.precio_venta = precio_venta;
            modalImei.pedido_detalle_ids = pedido_detalle_ids;
            modalImei.mostrar = true;
            return;
        }

        agregarDetalle(item, cantidad, precio_compra, precio_venta, [], pedido_detalle_ids);
        enfocarBuscador();
    }

    function confirmarImeis(imeis) {
        modalImei.mostrar = false;

        agregarDetalle(
            modalImei.item,
            modalImei.cantidad,
            modalImei.precio_compra,
            modalImei.precio_venta,
            imeis,
            modalImei.pedido_detalle_ids
        );

        enfocarBuscador();
    }

    function volverACantidad() {
        modalImei.mostrar = false;
        modalCantidad.mostrar = true;
    }

    function cancelarModal() {
        modalCantidad.mostrar = false;
        modalImei.mostrar = false;
        modalCantidad.item = null;
        modalCantidad.pedidosPendientes = [];
        enfocarBuscador();
    }

    function agregarDetalle(item, cantidad, precio_compra, precio_venta, imeis = [], pedidoDetalleIds = [], notificar = true) {
        if (!item) return;

        const baseIdKey = item.id ? `v:${item.id}` : `p:${item.producto_id}`;
        const pedidosKey = [...pedidoDetalleIds].map(Number).sort((a, b) => a - b).join(",");
        const idKey = pedidosKey ? `${baseIdKey}:pedidos:${pedidosKey}` : baseIdKey;

        const existe = detalles.value.find((d) =>
            d._idkey === idKey
            && Number(d.precio_compra) === Number(precio_compra)
            && Number(d.precio_venta) === Number(precio_venta)
        );

        if (existe && !item.tiene_series) {
            existe.cantidad = Number(existe.cantidad) + Number(cantidad);
            existe.precio_compra = precio_compra;
            existe.precio_venta = precio_venta;
            existe.pedido_detalle_ids = Array.from(new Set([
                ...(existe.pedido_detalle_ids ?? []),
                ...pedidoDetalleIds,
            ]));

            normalizeLinea(existe);
            if (notificar) toastSuccess(`+${cantidad} a ${item.nombre}`);
            return;
        }

        const det = {
            _key: ++keyCounter,
            _idkey: item.tiene_series ? `s:${keyCounter}` : idKey,
            variante_id: item.id ?? null,
            producto_id: item.producto_id,
            nombre: item.nombre,
            nombre_variante: item.nombre_variante ?? null,
            codigo: item.codigo,
            imagen_url: item.imagen_url,
            tiene_series: Boolean(item.tiene_series),
            cantidad,
            precio_compra,
            precio_venta,
            subtotal: 0,
            imeis,
            pedido_detalle_ids: pedidoDetalleIds,
        };

        normalizeLinea(det);

        // Nuevo arriba. Más POS, menos “lo mandé al sótano”.
        detalles.value.unshift(det);

        if (item.tiene_series && notificar) {
            toastSuccess(
                `${cantidad} IMEI${cantidad !== 1 ? "s" : ""} registrados`
            );
        }
    }

    function precargarDesdePedidos(pedidos) {
        detalles.value = [];

        for (const pedido of pedidos) {
            agregarDetalle(
                {
                    id: pedido.variante_id ?? null,
                    producto_id: pedido.producto_id,
                    nombre: pedido.producto || pedido.descripcion,
                    nombre_variante: pedido.sku ?? null,
                    codigo: pedido.codigo,
                    imagen_url: pedido.imagen_url,
                    tiene_series: false,
                },
                Number(pedido.cantidad || 1),
                Number(pedido.precio_compra || 0),
                Number(pedido.precio_venta || pedido.precio_acordado || 0),
                [],
                [pedido.id],
                false,
            );
        }
    }

    function recalcularLinea(idx) {
        const detalle = detalles.value[idx];
        if (detalle) normalizeLinea(detalle);
    }

    function quitarDetalle(idx) {
        detalles.value.splice(idx, 1);
    }

    function abrirEditarImeis(idx) {
        modalEditar.idx = idx;
        modalEditar.detalle = detalles.value[idx];
        modalEditar.mostrar = true;
    }

    function cerrarEditarImeis() {
        modalEditar.mostrar = false;
        modalEditar.detalle = null;
        modalEditar.idx = null;
    }

    function guardarImeisEditados({ imeis, cantidad }) {
        const det = detalles.value[modalEditar.idx];

        if (!det) return;

        det.imeis = imeis;
        det.cantidad = cantidad;

        normalizeLinea(det);
        cerrarEditarImeis();

        toastSuccess("IMEIs actualizados");
    }

    function resetear() {
        detalles.value = [];
        Object.assign(form, formInicial());
        enfocarBuscador();
    }

    async function confirmarGuardar() {
        if (!validarCompra()) return;

        if (detalles.value.length === 0) {
            toastWarning("Agrega al menos un producto");
            return;
        }

        const faltanImeis = detalles.value.find((d) => {
            return d.tiene_series && (d.imeis?.length ?? 0) !== Number(d.cantidad);
        });

        if (faltanImeis) {
            toastWarning(`Faltan IMEIs en "${faltanImeis.nombre}"`);
            return;
        }

        detalles.value.forEach(normalizeLinea);

        const r = await Swal.fire({
            title: "Confirmar compra",
            html: `
                    <p class="text-sm text-slate-600">
                        Se registrará la compra de
                        <strong>${detalles.value.length} artículo${detalles.value.length !== 1 ? "s" : ""}</strong>
                        por un total de <strong>${formatPrecio(totalCompra.value)}</strong>.
                    </p>
                    ${saldoFavorAplicado.value > 0 ? `<p class="mt-2 text-sm text-emerald-700">Se aplicar&aacute;n <strong>${formatPrecio(saldoFavorAplicado.value)}</strong> de saldo a favor. Restante: <strong>${formatPrecio(restantePorPagar.value)}</strong>.</p>` : ""}
                    <p class="mt-2 text-xs text-slate-400">
                        El stock se incrementará automáticamente.
                    </p>
                `,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#059669",
            cancelButtonColor: "#64748b",
            confirmButtonText: "Confirmar compra",
            cancelButtonText: "Revisar",
            reverseButtons: true,
        });

        if (!r.isConfirmed) return;

        guardando.value = true;

        try {
            await http.post("/api/compras", {
                proveedor_id: form.proveedor_id || null,
                folio: form.folio || null,
                fecha: form.fecha,
                forma_pago: form.forma_pago,
                fecha_vencimiento: form.fecha_vencimiento || null,
                notas: form.notas || null,
                aplicar_saldo_favor: Boolean(form.aplicar_saldo_favor),
                detalles: detalles.value.map((d) => ({
                    producto_id: d.producto_id,
                    variante_id: d.variante_id ?? null,
                    cantidad: parseFloat(d.cantidad),
                    precio_compra: Number(d.precio_compra),
                    precio_venta: Number(d.precio_venta) || null,
                    imeis: d.imeis ?? [],
                    pedido_detalle_ids: d.pedido_detalle_ids ?? [],
                })),
            });

            await Swal.fire({
                icon: "success",
                title: "¡Compra registrada!",
                html: `<p class="text-sm text-slate-600">Stock actualizado correctamente.</p>`,
                confirmButtonColor: "#059669",
                confirmButtonText: "Nueva compra",
            });

            await cargarProveedores();
            resetear();
        } catch (e) {
            Swal.fire({
                icon: "error",
                title: "Error al guardar",
                text: e.response?.data?.message ?? "Ocurrió un error inesperado",
            });
        } finally {
            guardando.value = false;
        }
    }

    function validarCompra() {
        if (!form.proveedor_id || Number(form.proveedor_id) <= 0) {
            toastWarning("Selecciona un proveedor");
            return false;
        }

        if (['credito', 'tarjeta_credito'].includes(form.forma_pago) && !form.fecha_vencimiento) {
            toastWarning("Selecciona la fecha de vencimiento");
            return false;
        }

        return true;
    }
    function actualizarCampoForm(key, value) {
        form[key] = value;

        if (key === 'proveedor_id') {
            form.aplicar_saldo_favor = false;
        }

        if (key === 'forma_pago' && !['credito', 'tarjeta_credito'].includes(value)) {
            form.fecha_vencimiento = "";
        }
    }

    return {
        actualizarCampoForm,
        validarCompra,
        proveedores,
        guardando,
        form,
        detalles,

        modalCantidad,
        modalImei,
        modalEditar,

        totalCompra,
        totalArticulos,
        saldoFavorDisponible,
        saldoFavorAplicado,
        restantePorPagar,
        todosLosImeis,

        formatPrecio,
        setBuscadorRef,
        cargarProveedores,
        actualizarForm,

        seleccionarItem,
        confirmarCantidad,
        confirmarImeis,
        volverACantidad,
        cancelarModal,

        recalcularLinea,
        quitarDetalle,

        abrirEditarImeis,
        guardarImeisEditados,
        cerrarEditarImeis,
        imeisDeOtrosDetalles,
        precargarDesdePedidos,

        resetear,
        confirmarGuardar,
    };
});
