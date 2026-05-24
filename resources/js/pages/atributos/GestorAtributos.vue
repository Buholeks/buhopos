<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 ring-1 ring-emerald-100"
                    >
                        <svg
                            class="h-5 w-5 text-emerald-600"
                            viewBox="0 0 20 20"
                            fill="none"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M6 6h.01M6 3h4l6 6-6 6H6V3z"
                            />
                        </svg>
                    </div>

                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">
                            Atributos
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            <span class="font-semibold text-emerald-700">{{
                                tipos.length
                            }}</span>
                            tipos ·
                            <span class="font-semibold text-emerald-700">{{
                                totalValores
                            }}</span>
                            valores
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <!-- Search -->
                    <div class="relative w-full sm:w-[320px]">
                        <svg
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            viewBox="0 0 20 20"
                            fill="none"
                            stroke="currentColor"
                        >
                            <circle
                                cx="8.5"
                                cy="8.5"
                                r="5"
                                stroke-width="1.7"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-width="1.7"
                                d="M17 17l-3.5-3.5"
                            />
                        </svg>

                        <BaseInput
                            v-model="busqueda"
                            type="text"
                            placeholder="Buscar tipo o valor…"
                        />

                        <button
                            v-if="busqueda"
                            type="button"
                            @click="busqueda = ''"
                            class="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100"
                            title="Limpiar"
                            aria-label="Limpiar búsqueda"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 16 16"
                                fill="none"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-width="1.8"
                                    d="M4 4l8 8M12 4l-8 8"
                                />
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Expand / Collapse (desktop-friendly) -->
                        <div class="hidden sm:flex items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                                @click="expandirFiltrados"
                                :disabled="tiposFiltrados.length === 0"
                                title="Expandir tipos filtrados"
                            >
                                Expandir todo
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                                @click="colapsarFiltrados"
                                :disabled="tiposFiltrados.length === 0"
                                title="Colapsar tipos filtrados"
                            >
                                Colapsar todo
                            </button>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:translate-y-px"
                            @click="abrirModalTipo()"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 16 16"
                                fill="none"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-width="2"
                                    d="M8 3v10M3 8h10"
                                />
                            </svg>
                            Nuevo tipo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div
                v-if="cargandoLista"
                class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
            >
                <div class="border-b border-slate-200 px-4 py-3">
                    <div
                        class="flex items-center gap-2 text-sm font-semibold text-slate-700"
                    >
                        <svg
                            class="h-4 w-4 animate-spin text-emerald-600"
                            viewBox="0 0 20 20"
                            fill="none"
                        >
                            <circle
                                cx="10"
                                cy="10"
                                r="7"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-dasharray="32"
                                stroke-dashoffset="12"
                            />
                        </svg>
                        Cargando atributos…
                    </div>
                </div>
                <div class="p-4">
                    <div class="space-y-3">
                        <div
                            v-for="i in 6"
                            :key="i"
                            class="h-12 rounded-xl bg-slate-100 animate-pulse"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Empty: no data -->
            <div
                v-else-if="tipos.length === 0"
                class="mt-6 rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200"
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 ring-1 ring-emerald-100"
                >
                    <svg
                        class="h-7 w-7 text-emerald-600"
                        viewBox="0 0 48 48"
                        fill="none"
                        stroke="currentColor"
                    >
                        <rect
                            x="6"
                            y="6"
                            width="36"
                            height="36"
                            rx="6"
                            stroke-width="2"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-width="2"
                            d="M16 24h16M24 16v16"
                        />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-semibold">
                    Sin tipos de atributo
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Crea tu primer tipo: Color, Talla, Material…
                </p>
                <button
                    type="button"
                    class="mt-5 inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    @click="abrirModalTipo()"
                >
                    Crear tipo
                </button>
            </div>

            <!-- Empty: no results -->
            <div
                v-else-if="busqueda && tiposFiltrados.length === 0"
                class="mt-6 rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200"
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 ring-1 ring-slate-200"
                >
                    <svg
                        class="h-7 w-7 text-slate-600"
                        viewBox="0 0 48 48"
                        fill="none"
                        stroke="currentColor"
                    >
                        <circle cx="22" cy="22" r="14" stroke-width="2" />
                        <path
                            stroke-linecap="round"
                            stroke-width="2"
                            d="M32 32l8 8"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-width="2"
                            d="M17 22h10M22 17v10"
                        />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-semibold">Sin resultados</h3>
                <p class="mt-1 text-sm text-slate-500">
                    “{{ busqueda }}” no coincide con ningún tipo ni valor.
                </p>
                <button
                    type="button"
                    class="mt-5 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    @click="busqueda = ''"
                >
                    Limpiar búsqueda
                </button>
            </div>

            <!-- Table component -->
            <AtributosTable
                v-else
                :tipos="tipos"
                :tiposFiltrados="tiposFiltrados"
                :tiposAbiertos="tiposAbiertos"
                :busqueda="busqueda"
                :getValoresFiltrados="getValoresFiltrados"
                :tipoPillClass="tipoPillClass"
                @toggle-tipo="toggleTipo"
                @abrir-modal-tipo="abrirModalTipo"
                @abrir-modal-valor="abrirModalValor"
                @eliminar-tipo="confirmarEliminarTipo"
                @eliminar-valor="confirmarEliminarValor"
                @expandir="expandirFiltrados"
                @colapsar="colapsarFiltrados"
            />

            <!-- Modals -->
            <TipoModal
                :open="modalTipo.mostrar"
                :editando="modalTipo.editando"
                :cargando="cargando"
                :errors="erroresTipo"
                v-model:nombre="formTipo.nombre"
                v-model:activo="formTipo.activo"
                @close="cerrarModalTipo"
                @submit="enviarFormTipo"
            />

            <ValorModal
                :open="modalValor.mostrar"
                :editando="modalValor.editando"
                :cargando="cargando"
                :errors="erroresValor"
                :nombreTipo="modalValor.nombreTipo"
                :placeholder="placeholderValor"
                v-model:valor="formValor.valor"
                v-model:activo="formValor.activo"
                @close="cerrarModalValor"
                @submit="enviarFormValor"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

import AtributosTable from "@/components/atributos/AtributosTable.vue";
import TipoModal from "@/components/atributos/TipoModal.vue";
import ValorModal from "@/components/atributos/ValorModal.vue";
import BaseInput from "../../components/ui/BaseInput.vue";

const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
});

const API_TIPOS = "/api/tipo-atributos";
const API_VALORES = "/api/atributos";

const tipos = ref([]);
const cargando = ref(false); // acciones (crear/editar/eliminar)
const cargandoLista = ref(false); // carga tabla
const busqueda = ref("");

// ✅ Set reactivo (no ref(new Set()))
const tiposAbiertos = reactive(new Set());

const modalTipo = reactive({
    mostrar: false,
    editando: false,
    idEditando: null,
});
const formTipo = reactive({ nombre: "", activo: true });
const erroresTipo = reactive({ nombre: "" });

const modalValor = reactive({
    mostrar: false,
    editando: false,
    idEditando: null,
    tipoId: null,
    nombreTipo: "",
});
const formValor = reactive({ valor: "", activo: true });
const erroresValor = reactive({ valor: "" });

const qLower = computed(() => busqueda.value.trim().toLowerCase());

const totalValores = computed(() =>
    tipos.value.reduce((acc, t) => acc + (t.atributos?.length ?? 0), 0),
);

const tiposFiltrados = computed(() => {
    const q = qLower.value;
    if (!q) return tipos.value;

    return tipos.value.filter((t) => {
        const nombre = (t.nombre ?? "").toLowerCase();
        const tc = nombre.includes(q);
        const vc = (t.atributos ?? []).some((a) =>
            (a.valor ?? "").toLowerCase().includes(q),
        );
        return tc || vc;
    });
});

const valoresFiltradosPorTipo = computed(() => {
    const q = qLower.value;
    const map = new Map();

    for (const tipo of tipos.value) {
        const nombre = (tipo.nombre ?? "").toLowerCase();
        const attrs = tipo.atributos ?? [];

        if (!q || nombre.includes(q)) map.set(tipo.id, attrs);
        else
            map.set(
                tipo.id,
                attrs.filter((a) => (a.valor ?? "").toLowerCase().includes(q)),
            );
    }

    return map;
});

function getValoresFiltrados(tipo) {
    return valoresFiltradosPorTipo.value.get(tipo.id) ?? [];
}

// auto-expand al buscar
watch(
    () => qLower.value,
    (q) => {
        if (!q) return;
        for (const t of tipos.value) {
            const nombre = (t.nombre ?? "").toLowerCase();
            const hitTipo = nombre.includes(q);
            const hitValor = (t.atributos ?? []).some((a) =>
                (a.valor ?? "").toLowerCase().includes(q),
            );
            if (hitTipo || hitValor) tiposAbiertos.add(t.id);
        }
    },
);

const placeholderValor = computed(() => {
    const n = (modalValor.nombreTipo ?? "").toLowerCase();
    if (n.includes("color")) return "Ej. Rojo, Azul marino, Verde oliva";
    if (n.includes("talla")) return "Ej. XS, S, M, L, XL, 38, 40";
    if (n.includes("material")) return "Ej. Algodón, Poliéster, Cuero";
    return "Escribe el valor…";
});

function tipoPillClass(nombreTipo) {
    const n = (nombreTipo ?? "").toLowerCase();
    if (n.includes("color")) return "bg-blue-50 text-blue-700 ring-blue-200";
    if (n.includes("talla"))
        return "bg-violet-50 text-violet-700 ring-violet-200";
    if (n.includes("material"))
        return "bg-amber-50 text-amber-800 ring-amber-200";
    return "bg-slate-100 text-slate-700 ring-slate-200";
}

async function cargarTipos() {
    cargandoLista.value = true;
    try {
        const { data } = await axios.get(API_TIPOS);
        tipos.value = Array.isArray(data) ? data : [];
        for (const t of tipos.value)
            if ((t.atributos?.length ?? 0) > 0) tiposAbiertos.add(t.id);
    } catch {
        Toast.fire({ icon: "error", title: "Error al cargar atributos" });
    } finally {
        cargandoLista.value = false;
    }
}

onMounted(() => cargarTipos());

function toggleTipo(id) {
    tiposAbiertos.has(id) ? tiposAbiertos.delete(id) : tiposAbiertos.add(id);
}

function expandirFiltrados() {
    tiposFiltrados.value.forEach((t) => tiposAbiertos.add(t.id));
}
function colapsarFiltrados() {
    tiposFiltrados.value.forEach((t) => tiposAbiertos.delete(t.id));
}

function abrirModalTipo(tipo = null) {
    formTipo.nombre = tipo?.nombre ?? "";
    formTipo.activo = tipo?.activo ?? true;
    erroresTipo.nombre = "";
    modalTipo.editando = !!tipo;
    modalTipo.idEditando = tipo?.id ?? null;
    modalTipo.mostrar = true;
}
function cerrarModalTipo() {
    modalTipo.mostrar = false;
    formTipo.nombre = "";
    erroresTipo.nombre = "";
}

async function enviarFormTipo() {
    erroresTipo.nombre = "";
    if (!formTipo.nombre) return (erroresTipo.nombre = "Obligatorio.");
    if (formTipo.nombre.length < 2)
        return (erroresTipo.nombre = "Mínimo 2 caracteres.");

    cargando.value = true;
    try {
        const p = { nombre: formTipo.nombre, activo: formTipo.activo };
        if (modalTipo.editando) {
            await axios.put(`${API_TIPOS}/${modalTipo.idEditando}`, p);
            Toast.fire({ icon: "success", title: "Tipo actualizado" });
        } else {
            await axios.post(API_TIPOS, p);
            Toast.fire({ icon: "success", title: "Tipo creado" });
        }
        cerrarModalTipo();
        await cargarTipos();
    } catch (err) {
        Toast.fire({
            icon: "error",
            title: err.response?.data?.message ?? "Error",
        });
        const e = err.response?.data?.errors;
        if (e?.nombre) erroresTipo.nombre = e.nombre[0];
    } finally {
        cargando.value = false;
    }
}

async function confirmarEliminarTipo(tipo) {
    const n = tipo.atributos?.length ?? 0;
    const r = await Swal.fire({
        title: `Eliminar "${tipo.nombre}"`,
        html:
            n > 0
                ? `<p style="color:#475569;font-size:.9rem">Se eliminarán también sus <strong>${n} valor(es)</strong>.</p>`
                : `<p style="color:#475569;font-size:.9rem">No tiene valores asociados.</p>`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
    });
    if (!r.isConfirmed) return;

    cargando.value = true;
    try {
        await axios.delete(`${API_TIPOS}/${tipo.id}`);
        Toast.fire({ icon: "success", title: "Tipo eliminado" });
        await cargarTipos();
    } catch (err) {
        Swal.fire({
            icon: "error",
            title: "Error",
            text: err.response?.data?.message ?? "No se pudo eliminar",
        });
    } finally {
        cargando.value = false;
    }
}

function abrirModalValor(tipo, atributo = null) {
    formValor.valor = atributo?.valor ?? "";
    formValor.activo = atributo?.activo ?? true;
    erroresValor.valor = "";
    modalValor.tipoId = tipo.id;
    modalValor.nombreTipo = tipo.nombre;
    modalValor.editando = !!atributo;
    modalValor.idEditando = atributo?.id ?? null;
    modalValor.mostrar = true;
}
function cerrarModalValor() {
    modalValor.mostrar = false;
    formValor.valor = "";
    erroresValor.valor = "";
}

async function enviarFormValor() {
    erroresValor.valor = "";
    if (!formValor.valor) return (erroresValor.valor = "Obligatorio.");

    cargando.value = true;
    try {
        const p = {
            valor: formValor.valor,
            activo: formValor.activo,
            tipo_atributo_id: modalValor.tipoId,
        };

        if (modalValor.editando) {
            await axios.put(`${API_VALORES}/${modalValor.idEditando}`, p);
            Toast.fire({ icon: "success", title: "Valor actualizado" });
        } else {
            await axios.post(API_VALORES, p);
            Toast.fire({ icon: "success", title: "Valor agregado" });
        }

        cerrarModalValor();
        await cargarTipos();
    } catch (err) {
        Toast.fire({
            icon: "error",
            title: err.response?.data?.message ?? "Error",
        });
        const e = err.response?.data?.errors;
        if (e?.valor) erroresValor.valor = e.valor[0];
    } finally {
        cargando.value = false;
    }
}

async function confirmarEliminarValor(atributo) {
    const r = await Swal.fire({
        title: `Eliminar "${atributo.valor}"`,
        html: `<p style="color:#475569;font-size:.9rem">Esta acción no se puede deshacer.</p>`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
    });
    if (!r.isConfirmed) return;

    cargando.value = true;
    try {
        await axios.delete(`${API_VALORES}/${atributo.id}`);
        Toast.fire({ icon: "success", title: "Valor eliminado" });
        await cargarTipos();
    } catch (err) {
        Swal.fire({
            icon: "error",
            title: "Error",
            text: err.response?.data?.message ?? "No se pudo eliminar",
        });
    } finally {
        cargando.value = false;
    }
}
</script>
