<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex items-start gap-3">
                    <RouterLink
                        :to="{ name: 'catalogos' }"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
                        title="Volver"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </RouterLink>

                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">
                            {{ tipo?.nombre ?? "Catalogo" }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Administra los valores disponibles para este atributo.
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
                            placeholder="Buscar valor..."
                        />
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!tipo"
                        @click="abrirModalValor()"
                    >
                        <Plus class="h-4 w-4" />
                        Nuevo valor
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
                        Cargando catalogo...
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
                v-else-if="!tipo"
                class="mt-6 rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-200"
            >
                <Tags class="mx-auto h-10 w-10 text-slate-400" />
                <h2 class="mt-3 text-base font-semibold text-slate-900">
                    Catalogo no encontrado
                </h2>
            </div>

            <div
                v-else
                class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
            >
                <div class="border-b border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                    <span class="font-semibold text-slate-900">{{ valoresFiltrados.length }}</span>
                    de
                    <span class="font-semibold text-slate-900">{{ valores.length }}</span>
                    valores
                </div>

                <div v-if="valores.length === 0" class="p-10 text-center">
                    <Tags class="mx-auto h-10 w-10 text-slate-400" />
                    <h2 class="mt-3 text-base font-semibold text-slate-900">
                        Sin valores
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Agrega el primer valor para este catalogo.
                    </p>
                </div>

                <div v-else class="max-h-[calc(100vh-260px)] overflow-auto">
                    <table class="min-w-full border-separate border-spacing-0">
                        <thead class="sticky top-0 z-10 bg-white">
                            <tr>
                                <th class="border-b border-slate-200 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    Valor
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
                                v-for="atributo in valoresFiltrados"
                                :key="atributo.id"
                                class="border-b border-slate-200 bg-white hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 align-middle">
                                    <span class="font-semibold text-slate-900">
                                        {{ atributo.valor }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center align-middle">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                                        :class="
                                            atributo.activo
                                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                : 'bg-slate-100 text-slate-600 ring-slate-200'
                                        "
                                    >
                                        {{ atributo.activo ? "Activo" : "Inactivo" }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-right align-middle">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                                            title="Editar valor"
                                            @click="abrirModalValor(atributo)"
                                        >
                                            <Pencil class="h-4 w-4 text-amber-600" />
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-rose-100"
                                            title="Eliminar valor"
                                            @click="confirmarEliminarValor(atributo)"
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

            <ValorModal
                :open="modalValor.mostrar"
                :editando="modalValor.editando"
                :cargando="cargando"
                :errors="erroresValor"
                :nombreTipo="tipo?.nombre ?? ''"
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
import { computed, onMounted, reactive, ref } from "vue";
import { RouterLink, useRoute } from "vue-router";
import axios from "axios";
import Swal from "sweetalert2";
import {
    ArrowLeft,
    Loader2,
    Pencil,
    Plus,
    Search,
    Tags,
    Trash2,
} from "lucide-vue-next";
import { ofrecerRecuperacion } from "@/helpers/recuperar";
import BaseInput from "@/components/ui/BaseInput.vue";
import ValorModal from "@/components/atributos/ValorModal.vue";

const route = useRoute();
const tipo = ref(null);
const cargando = ref(false);
const cargandoLista = ref(false);
const busqueda = ref("");

const modalValor = reactive({
    mostrar: false,
    editando: false,
    idEditando: null,
});
const formValor = reactive({ valor: "", activo: true });
const erroresValor = reactive({ valor: "" });

const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
});

const valores = computed(() => tipo.value?.atributos ?? []);
const valoresFiltrados = computed(() => {
    const q = busqueda.value.trim().toLowerCase();
    if (!q) return valores.value;
    return valores.value.filter((atributo) => (atributo.valor ?? "").toLowerCase().includes(q));
});

const placeholderValor = computed(() => {
    const nombre = (tipo.value?.nombre ?? "").toLowerCase();
    if (nombre.includes("color")) return "Ej. Rojo, Azul marino, Verde oliva";
    if (nombre.includes("talla")) return "Ej. XS, S, M, L, XL, 38, 40";
    if (nombre.includes("material")) return "Ej. Algodon, Poliester, Cuero";
    return "Escribe el valor...";
});

async function cargarTipo() {
    cargandoLista.value = true;
    try {
        const { data } = await axios.get(`/api/tipo-atributos/${route.params.id}`);
        tipo.value = data;
    } catch {
        tipo.value = null;
    } finally {
        cargandoLista.value = false;
    }
}

onMounted(() => cargarTipo());

function abrirModalValor(atributo = null) {
    formValor.valor = atributo?.valor ?? "";
    formValor.activo = atributo?.activo ?? true;
    erroresValor.valor = "";
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
    if (!tipo.value?.id) return;

    cargando.value = true;
    try {
        const payload = {
            valor: formValor.valor,
            activo: formValor.activo,
            tipo_atributo_id: tipo.value.id,
        };

        if (modalValor.editando) {
            await axios.put(`/api/atributos/${modalValor.idEditando}`, payload);
            Toast.fire({ icon: "success", title: "Valor actualizado" });
        } else {
            await axios.post("/api/atributos", payload);
            Toast.fire({ icon: "success", title: "Valor agregado" });
        }

        cerrarModalValor();
        await cargarTipo();
    } catch (err) {
        const handled = await ofrecerRecuperacion(err, "/api/atributos", async () => {
            cerrarModalValor();
            await cargarTipo();
        });
        if (!handled) {
            Toast.fire({ icon: "error", title: err.response?.data?.message ?? "Error" });
            const errors = err.response?.data?.errors;
            if (errors?.valor) erroresValor.valor = errors.valor[0];
        }
    } finally {
        cargando.value = false;
    }
}

async function confirmarEliminarValor(atributo) {
    const result = await Swal.fire({
        title: `Eliminar "${atributo.valor}"`,
        html: `<p style="color:#475569;font-size:.9rem">Esta accion no se puede deshacer.</p>`,
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
        await axios.delete(`/api/atributos/${atributo.id}`);
        Toast.fire({ icon: "success", title: "Valor eliminado" });
        await cargarTipo();
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
