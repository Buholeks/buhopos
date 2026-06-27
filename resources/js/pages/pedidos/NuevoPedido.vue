<template>
    <main class="mx-auto max-w-6xl space-y-4">
        <header
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p
                    class="text-xs font-black uppercase tracking-wide text-emerald-700"
                >
                    Pedidos
                </p>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">
                    Nuevo pedido
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Encargos sin depender necesariamente del stock actual.
                </p>
            </div>
        </header>

        <form
            class="rounded-2xl border border-slate-200 bg-white shadow-sm"
            @submit.prevent="guardarEncargo"
        >
            <div
                class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3"
            >
                <div>
                    <h2 class="text-sm font-black text-slate-900">
                        Datos del pedido
                    </h2>
                    <p class="text-xs text-slate-500">
                        Cliente, artículos y anticipo
                    </p>
                </div>
                <span
                    class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700"
                    >Pedido</span
                >
            </div>

            <div class="space-y-4 p-4">
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_160px]">
                    <BaseSearchSelect
                        :model-value="cliente?.id ?? null"
                        label="Cliente"
                        placeholder="Buscar cliente"
                        :fetcher="buscarClientes"
                        :min-chars="1"
                        :label-key="(item) => item.nombre || 'Sin nombre'"
                        :sub-label-key="
                            (item) =>
                                item.telefono || item.email || 'Sin referencia'
                        "
                        value-key="id"
                        required
                        @selected="seleccionarCliente"
                    />
                    <div
                        v-if="cliente"
                        class="rounded-2xl border border-emerald-100 bg-emerald-50 px-3 py-2"
                    >
                        <p
                            class="text-[11px] font-black uppercase tracking-wide text-emerald-700"
                        >
                            Saldo favor
                        </p>
                        <p class="text-lg font-black text-emerald-900">
                            {{ money(resumenCliente.saldo_favor) }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="block">
                        <span
                            class="mb-1 block text-xs font-bold text-slate-600"
                            >Fecha promesa</span
                        >
                        <input
                            v-model="form.fecha_promesa"
                            type="date"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                        />
                    </label>
                    <label class="block">
                        <span
                            class="mb-1 block text-xs font-bold text-slate-600"
                            >Anticipo</span
                        >
                        <input
                            v-model.number="form.anticipo"
                            type="number"
                            min="0"
                            step="0.01"
                            :max="subtotal"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                            :class="
                                Number(form.anticipo) > subtotal
                                    ? 'border-red-400 focus:border-red-500 focus:ring-red-100'
                                    : ''
                            "
                        />
                    </label>
                    <BaseSearchSelect
                        v-model="form.forma_pago"
                        label="Pago anticipo"
                        placeholder="Forma de pago"
                        :items="formasPago"
                        label-key="nombre"
                        value-key="id"
                        :disabled="Number(form.anticipo) <= 0"
                    />
                </div>

                <p
                    v-if="Number(form.anticipo) > subtotal"
                    class="-mt-2 text-xs font-bold text-red-600"
                >
                    El anticipo no puede superar el total.
                </p>

                <div
                    class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_44px] lg:items-end"
                >
                    <BaseSearchSelect
                        :model-value="null"
                        label="Producto a pedir"
                        placeholder="Buscar producto o variante"
                        :fetcher="buscarProductosCatalogo"
                        :min-chars="1"
                        :label-key="labelProducto"
                        :sub-label-key="subLabelProducto"
                        value-key="selector_id"
                        @selected="seleccionarProductoPedido"
                    />
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50"
                        title="Crear producto rapido"
                        @click="abrirProductoRapido"
                    >
                        <Plus class="h-4 w-4" />
                    </button>
                </div>

                <EncargoDetalleTable
                    v-model:detalles="form.detalles"
                    @remove="quitarDetalle"
                />

                <textarea
                    v-model="form.notas"
                    rows="2"
                    class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                    placeholder="Notas internas del pedido..."
                />

                <EncargoResumenCard
                    :subtotal="subtotal"
                    :anticipo="Number(form.anticipo || 0)"
                    :saldo-pendiente="saldoPendiente"
                />

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60"
                    :disabled="
                        guardando ||
                        Number(form.anticipo) > subtotal ||
                        form.detalles.length === 0
                    "
                >
                    <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                    Registrar pedido
                </button>
            </div>
        </form>

        <Teleport to="body">
            <div
                v-if="modalRapido.mostrar"
                class="fixed inset-0 z-100 flex items-center justify-center bg-slate-900/40 p-4"
                @mousedown.self="cerrarProductoRapido"
            >
                <div
                    class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                >
                    <div
                        class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4"
                    >
                        <div>
                            <h2 class="text-base font-black text-slate-900">
                                Crear producto rapido
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Crea producto padre, variante y lo agrega al
                                pedido.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            title="Cerrar"
                            @click="cerrarProductoRapido"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <form
                        class="space-y-4 p-5"
                        @submit.prevent="crearProductoRapido"
                    >
                        <BaseInput
                            v-model.trim="productoRapido.nombre"
                            label="Producto"
                            placeholder="Ej. Tenis Nike Air talla 26"
                            required
                            autofocus
                        />

                        <div class="grid gap-3 sm:grid-cols-2">
                            <BaseInput
                                v-model.trim="productoRapido.codigo"
                                label="Codigo"
                                placeholder="Opcional"
                            />
                            <BaseInput
                                v-model.number="productoRapido.precio_venta"
                                label="Precio venta"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                            />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-1">
                            <BaseInput
                                v-model.number="productoRapido.cantidad"
                                label="Cantidad"
                                type="number"
                                min="1"
                                step="1"
                                required
                            />
                        </div>

                        <div
                            v-if="tiposAtributo.length"
                            class="grid gap-3 sm:grid-cols-2"
                        >
                            <BaseSearchSelect
                                v-for="tipo in tiposAtributo"
                                :key="tipo.id"
                                v-model="productoRapido.atributos[tipo.id]"
                                :label="tipo.nombre"
                                placeholder="Seleccionar"
                                :items="tipo.atributos ?? []"
                                label-key="valor"
                                value-key="id"
                            />
                        </div>

                        <div
                            v-else
                            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                        >
                            No hay atributos activos configurados. Crea
                            atributos como color o talla antes de usar este
                            flujo.
                        </div>

                        <label class="block">
                            <span
                                class="mb-1 block text-xs font-bold text-slate-600"
                                >Descripcion del pedido</span
                            >
                            <textarea
                                v-model="productoRapido.descripcion_pedido"
                                rows="2"
                                class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                placeholder="Opcional"
                            />
                        </label>

                        <div
                            class="flex justify-end gap-2 border-t border-slate-100 pt-4"
                        >
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"
                                @click="cerrarProductoRapido"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white hover:bg-emerald-700 disabled:opacity-60"
                                :disabled="
                                    guardandoRapido ||
                                    !productoRapido.nombre ||
                                    Number(productoRapido.cantidad) < 1 ||
                                    !atributosCompletos
                                "
                            >
                                <Loader2
                                    v-if="guardandoRapido"
                                    class="h-4 w-4 animate-spin"
                                />
                                Crear y agregar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="modalVariante.mostrar"
                class="fixed inset-0 z-100 flex items-center justify-center bg-slate-900/40 p-4"
                @mousedown.self="cerrarModalVariante"
            >
                <div
                    class="w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                >
                    <div
                        class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4"
                    >
                        <div>
                            <h2 class="text-base font-black text-slate-900">
                                Seleccionar variante
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ modalVariante.producto?.nombre }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            title="Cerrar"
                            @click="cerrarModalVariante"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                        <div
                            v-if="cargandoVariantes"
                            class="flex justify-center py-6"
                        >
                            <Loader2
                                class="h-5 w-5 animate-spin text-slate-400"
                            />
                        </div>

                        <div v-else>
                            <div
                                v-if="modalVariante.variantes.length"
                                class="space-y-2"
                            >
                                <button
                                    v-for="variante in modalVariante.variantes"
                                    :key="variante.id"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 text-left transition hover:border-emerald-300 hover:bg-emerald-50"
                                    @click="
                                        seleccionarVarianteExistente(variante)
                                    "
                                >
                                    <span class="font-bold text-slate-800">{{
                                        variante.nombre_variante || variante.sku
                                    }}</span>
                                    <span
                                        class="text-xs font-bold text-emerald-700"
                                        >Seleccionar</span
                                    >
                                </button>
                            </div>

                            <div
                                v-else
                                class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500"
                            >
                                Este producto todavia no tiene variantes.
                            </div>

                            <div class="border-t border-slate-100 pt-4">
                                <h3 class="text-sm font-black text-slate-900">
                                    Nueva variante
                                </h3>
                                <div
                                    v-if="tiposAtributo.length"
                                    class="mt-3 grid gap-3 sm:grid-cols-2"
                                >
                                    <BaseSearchSelect
                                        v-for="tipo in tiposAtributo"
                                        :key="tipo.id"
                                        v-model="
                                            modalVariante.atributos[tipo.id]
                                        "
                                        :label="tipo.nombre"
                                        placeholder="Seleccionar"
                                        :items="tipo.atributos ?? []"
                                        label-key="valor"
                                        value-key="id"
                                    />
                                </div>
                                <div
                                    v-else
                                    class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                                >
                                    No hay atributos activos configurados.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4"
                    >
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50"
                            @click="cerrarModalVariante"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-black text-white hover:bg-emerald-700 disabled:opacity-60"
                            :disabled="
                                guardandoVariante || !atributosVarianteCompletos
                            "
                            @click="crearYSeleccionarVariante"
                        >
                            <Loader2
                                v-if="guardandoVariante"
                                class="h-4 w-4 animate-spin"
                            />
                            Crear y seleccionar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </main>
</template>

<script setup>
import { computed, reactive, ref } from "vue";
import { Loader2, Plus, X } from "lucide-vue-next";
import http from "@/lib/http";
import { toastError, toastSuccess } from "@/lib/alert";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import EncargoDetalleTable from "@/components/encargos/EncargoDetalleTable.vue";
import EncargoResumenCard from "@/components/encargos/EncargoResumenCard.vue";
import { useEncargos } from "@/stores/useEncargos";

const {
    cliente,
    resumenCliente,
    form,
    subtotal,
    saldoPendiente,
    guardando,
    money,
    buscarClientes,
    seleccionarCliente,
    buscarProductosCatalogo,
    labelProducto,
    subLabelProducto,
    agregarProducto,
    quitarDetalle,
    guardarEncargo,
} = useEncargos({ tipo: "pedido" });

const formasPago = [
    { id: "efectivo", nombre: "Efectivo" },
    { id: "tarjeta", nombre: "Tarjeta" },
    { id: "transferencia", nombre: "Transferencia" },
];

const modalRapido = reactive({ mostrar: false });
const guardandoRapido = ref(false);
const tiposAtributo = ref([]);
const cargandoVariantes = ref(false);
const guardandoVariante = ref(false);
const modalVariante = reactive({
    mostrar: false,
    producto: null,
    variantes: [],
    atributos: {},
});
const productoRapido = reactive({
    nombre: "",
    codigo: "",
    precio_venta: 0,
    cantidad: 1,
    descripcion_pedido: "",
    atributos: {},
});

const atributosCompletos = computed(() => {
    const tipos = tiposAtributo.value ?? [];
    return (
        tipos.length > 0 &&
        tipos.every((tipo) => productoRapido.atributos[tipo.id])
    );
});

const atributosVarianteCompletos = computed(() => {
    const tipos = tiposAtributo.value ?? [];
    return (
        tipos.length > 0 &&
        tipos.every((tipo) => modalVariante.atributos[tipo.id])
    );
});

async function seleccionarProductoPedido(item) {
    if (!item) return;

    if (item.tipo_resultado === "producto" && item.tiene_variantes) {
        await abrirModalVariante(item);
        return;
    }

    agregarProducto(item);
}

async function abrirModalVariante(producto) {
    modalVariante.mostrar = true;
    modalVariante.producto = producto;
    modalVariante.variantes = [];
    modalVariante.atributos = {};
    cargandoVariantes.value = true;

    try {
        await cargarAtributosRapidos();
        const { data } = await http.get(
            `/api/pedidos/productos/${producto.producto_id}/variantes`,
        );
        modalVariante.variantes = data?.variantes ?? [];
        modalVariante.producto = {
            ...producto,
            ...(data?.producto ?? {}),
            producto_id: producto.producto_id,
        };
    } catch (e) {
        toastError(
            e?.response?.data?.message || "No se pudieron cargar las variantes",
        );
        cerrarModalVariante();
    } finally {
        cargandoVariantes.value = false;
    }
}

function cerrarModalVariante() {
    modalVariante.mostrar = false;
    modalVariante.producto = null;
    modalVariante.variantes = [];
    modalVariante.atributos = {};
}

function seleccionarVarianteExistente(variante) {
    agregarProducto({
        ...variante,
        selector_id: `variante:${variante.producto_id}:${variante.id}`,
    });
    cerrarModalVariante();
}

async function crearYSeleccionarVariante() {
    if (
        !modalVariante.producto?.producto_id ||
        !atributosVarianteCompletos.value
    ) {
        toastError("Selecciona los atributos de la variante");
        return;
    }

    guardandoVariante.value = true;
    try {
        const { data } = await http.post(
            `/api/pedidos/productos/${modalVariante.producto.producto_id}/variantes`,
            {
                atributos: modalVariante.atributos,
            },
        );
        seleccionarVarianteExistente(data?.data);
        toastSuccess("Variante agregada al pedido");
    } catch (e) {
        toastError(
            e?.response?.data?.message || "No se pudo crear la variante",
        );
    } finally {
        guardandoVariante.value = false;
    }
}

async function abrirProductoRapido() {
    resetProductoRapido();
    modalRapido.mostrar = true;
    await cargarAtributosRapidos();
}

function cerrarProductoRapido() {
    modalRapido.mostrar = false;
}

function resetProductoRapido() {
    Object.assign(productoRapido, {
        nombre: "",
        codigo: "",
        precio_venta: 0,
        cantidad: 1,
        descripcion_pedido: "",
        atributos: {},
    });
}

async function cargarAtributosRapidos() {
    if (tiposAtributo.value.length) return;

    try {
        const { data } = await http.get("/api/productos/atributos-empresa");
        tiposAtributo.value = Array.isArray(data?.data)
            ? data.data
            : Array.isArray(data)
              ? data
              : [];
    } catch {
        toastError("No se pudieron cargar los atributos");
    }
}

async function crearProductoRapido() {
    if (
        !productoRapido.nombre ||
        Number(productoRapido.cantidad || 0) < 1 ||
        !atributosCompletos.value
    ) {
        toastError("Completa el producto rapido");
        return;
    }

    guardandoRapido.value = true;
    try {
        const { data } = await http.post("/api/pedidos/producto-rapido", {
            nombre: productoRapido.nombre,
            codigo: productoRapido.codigo || null,
            precio_costo: 0,
            precio_venta: Number(productoRapido.precio_venta || 0),
            atributos: productoRapido.atributos,
        });

        const item = data?.data;
        agregarProducto({
            ...item,
            selector_id: `variante:${item.producto_id}:${item.id}`,
        });

        const detalle = form.detalles[form.detalles.length - 1];
        if (detalle) {
            detalle.cantidad = Number(productoRapido.cantidad || 1);
            detalle.descripcion = productoRapido.descripcion_pedido || "";
        }

        toastSuccess("Producto agregado al pedido");
        cerrarProductoRapido();
    } catch (e) {
        const errores = e?.response?.data?.errors;
        toastError(
            errores?.codigo?.[0] ||
                e?.response?.data?.message ||
                "No se pudo crear el producto",
        );
    } finally {
        guardandoRapido.value = false;
    }
}
</script>
