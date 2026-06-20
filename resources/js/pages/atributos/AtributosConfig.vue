<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 ring-1 ring-emerald-100"
                    >
                        <SlidersHorizontal class="h-5 w-5 text-emerald-600" />
                    </div>

                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">
                            Atributos
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Define los atributos que apareceran como catalogos.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative w-full sm:w-[320px]">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />

                        <BaseInput
                            v-model="busqueda"
                            type="text"
                            placeholder="Buscar atributo..."
                        />

                        <button
                            v-if="busqueda"
                            type="button"
                            class="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100"
                            title="Limpiar"
                            aria-label="Limpiar busqueda"
                            @click="busqueda = ''"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:translate-y-px"
                        @click="abrirModalTipo()"
                    >
                        <Plus class="h-4 w-4" />
                        Nuevo atributo
                    </button>
                </div>
            </div>

            <div
                v-if="cargandoLista"
                class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
            >
                <div class="border-b border-slate-200 px-4 py-3">
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <Loader2 class="h-4 w-4 animate-spin text-emerald-600" />
                        Cargando atributos...
                    </div>
                </div>
                <div class="space-y-3 p-4">
                    <div
                        v-for="i in 6"
                        :key="i"
                        class="h-12 animate-pulse rounded-xl bg-slate-100"
                    />
                </div>
            </div>

            <div
                v-else-if="tipos.length === 0"
                class="mt-6 rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200"
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 ring-1 ring-emerald-100"
                >
                    <Plus class="h-7 w-7 text-emerald-600" />
                </div>
                <h3 class="mt-4 text-base font-semibold">
                    Sin atributos
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Crea el primer atributo: Color, Talla, Material o Temporada.
                </p>
                <button
                    type="button"
                    class="mt-5 inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    @click="abrirModalTipo()"
                >
                    Crear atributo
                </button>
            </div>

            <div
                v-else
                class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
            >
                <div
                    class="flex flex-col gap-2 border-b border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="text-sm text-slate-600">
                        Mostrando
                        <span class="font-semibold text-slate-900">{{ tiposFiltrados.length }}</span>
                        de
                        <span class="font-semibold text-slate-900">{{ tipos.length }}</span>
                        atributos
                    </div>

                </div>

                <div class="max-h-[calc(100vh-260px)] overflow-auto">
                    <table class="min-w-full border-separate border-spacing-0">
                        <thead class="sticky top-0 z-10 bg-white">
                            <tr>
                                <th class="border-b border-slate-200 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    Atributo
                                </th>
                                <th class="border-b border-slate-200 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    Valores
                                </th>
                                <th class="border-b border-slate-200 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    Estado
                                </th>
                                <th class="border-b border-slate-200 px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    Acciones
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="tipo in tiposFiltrados"
                                :key="tipo.id"
                                class="border-b border-slate-200 bg-white hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 align-middle">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500" />
                                        <span class="font-semibold text-slate-900">{{ tipo.nombre }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-center align-middle">
                                    <span
                                        class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200"
                                    >
                                        {{ tipo.atributos?.length ?? 0 }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center align-middle">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                                        :class="
                                            tipo.activo
                                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                : 'bg-slate-100 text-slate-600 ring-slate-200'
                                        "
                                    >
                                        {{ tipo.activo ? "Activo" : "Inactivo" }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-right align-middle">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                                            title="Editar atributo"
                                            @click="abrirModalTipo(tipo)"
                                        >
                                            <Pencil class="h-4 w-4 text-amber-600" />
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-rose-100"
                                            title="Eliminar atributo"
                                            @click="confirmarEliminarTipo(tipo)"
                                        >
                                            <Trash2 class="h-4 w-4 text-rose-600" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

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
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import {
    Loader2,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
    X,
} from "lucide-vue-next";
import { ofrecerRecuperacion } from "@/helpers/recuperar";
import TipoModal from "@/components/atributos/TipoModal.vue";
import BaseInput from "@/components/ui/BaseInput.vue";

const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
});

const API_TIPOS = "/api/tipo-atributos";

const tipos = ref([]);
const cargando = ref(false);
const cargandoLista = ref(false);
const busqueda = ref("");

const modalTipo = reactive({
    mostrar: false,
    editando: false,
    idEditando: null,
});
const formTipo = reactive({ nombre: "", activo: true });
const erroresTipo = reactive({ nombre: "" });

const tiposFiltrados = computed(() => {
    const q = busqueda.value.trim().toLowerCase();
    if (!q) return tipos.value;
    return tipos.value.filter((tipo) => (tipo.nombre ?? "").toLowerCase().includes(q));
});

async function cargarTipos() {
    cargandoLista.value = true;
    try {
        const { data } = await axios.get(API_TIPOS);
        tipos.value = Array.isArray(data) ? data : [];
    } catch {
        Toast.fire({ icon: "error", title: "Error al cargar atributos" });
    } finally {
        cargandoLista.value = false;
    }
}

onMounted(() => cargarTipos());

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
    if (formTipo.nombre.length < 2) return (erroresTipo.nombre = "Minimo 2 caracteres.");

    cargando.value = true;
    try {
        const payload = { nombre: formTipo.nombre, activo: formTipo.activo };
        if (modalTipo.editando) {
            await axios.put(`${API_TIPOS}/${modalTipo.idEditando}`, payload);
            Toast.fire({ icon: "success", title: "Atributo actualizado" });
        } else {
            await axios.post(API_TIPOS, payload);
            Toast.fire({ icon: "success", title: "Atributo creado" });
        }

        cerrarModalTipo();
        await cargarTipos();
    } catch (err) {
        const handled = await ofrecerRecuperacion(err, API_TIPOS, async () => {
            cerrarModalTipo();
            await cargarTipos();
        });
        if (!handled) {
            Toast.fire({ icon: "error", title: err.response?.data?.message ?? "Error" });
            const errors = err.response?.data?.errors;
            if (errors?.nombre) erroresTipo.nombre = errors.nombre[0];
        }
    } finally {
        cargando.value = false;
    }
}

async function confirmarEliminarTipo(tipo) {
    const valores = tipo.atributos?.length ?? 0;
    const result = await Swal.fire({
        title: `Eliminar "${tipo.nombre}"`,
        html:
            valores > 0
                ? `<p style="color:#475569;font-size:.9rem">Tambien se eliminaran sus <strong>${valores} valor(es)</strong> del catalogo.</p>`
                : `<p style="color:#475569;font-size:.9rem">No tiene valores asociados.</p>`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
    });

    if (!result.isConfirmed) return;

    cargando.value = true;
    try {
        await axios.delete(`${API_TIPOS}/${tipo.id}`);
        Toast.fire({ icon: "success", title: "Atributo eliminado" });
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
