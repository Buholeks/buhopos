<template>
    <section class="min-h-screen bg-slate-50">
        <!-- Header -->
        <div class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-3 sm:px-6 py-3 sm:py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <Truck class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">
                            Proveedores
                        </h1>
                        <p class="text-xs text-slate-500">
                            Alta, edición, estado y administración de proveedores.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-100"
                    @click="openCreate"
                >
                    <Plus class="h-4 w-4" />
                    Nuevo proveedor
                </button>
            </div>
        </div>

        <main class="mx-auto max-w-7xl space-y-4 px-3 sm:px-6 py-4 sm:py-5">
            <!-- Toolbar -->
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div class="w-full md:max-w-xl">
                        <BaseInput
                            v-model="q"
                            label="Buscar proveedor"
                            type="text"
                            placeholder="Nombre, correo, teléfono o contacto…"
                            @keyup.enter="fetchProveedores()"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="loading"
                            @click="fetchProveedores()"
                        >
                            <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                            <Search v-else class="h-4 w-4" />
                            {{ loading ? "Buscando..." : "Buscar" }}
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="loading"
                            @click="limpiarBusqueda"
                        >
                            <XCircle class="h-4 w-4" />
                            Limpiar
                        </button>
                    </div>
                </div>
            </section>

            <!-- Tabla -->
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            Lista de proveedores
                        </h2>
                        <p class="text-xs text-slate-500">
                            {{ meta?.total ?? 0 }} registros encontrados.
                        </p>
                    </div>

                    <div class="hidden items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-500 md:flex">
                        <Database class="h-4 w-4" />
                        Catálogo
                    </div>
                </div>

                <div v-if="loading" class="flex items-center justify-center gap-2 py-14 text-sm text-slate-500">
                    <Loader2 class="h-5 w-5 animate-spin text-emerald-600" />
                    Cargando proveedores...
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Proveedor</th>
                                <th class="px-4 py-3 text-left">Razón social</th>
                                <th class="px-4 py-3 text-left">Correo</th>
                                <th class="px-4 py-3 text-left">Teléfono</th>
                                <th class="px-4 py-3 text-left">Contacto</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="c in items"
                                :key="c.id"
                                class="transition hover:bg-slate-50"
                                @dblclick="openEdit(c)"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                                            <Building2 class="h-4 w-4" />
                                        </div>

                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900">
                                                {{ c.nombre_comercial || "Sin nombre" }}
                                            </p>
                                            <p class="truncate text-xs text-slate-400">
                                                RFC: {{ c.rfc || "N/A" }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-slate-600">
                                    {{ c.razon_social || "-" }}
                                </td>

                                <td class="px-4 py-3">
                                    <div class="inline-flex items-center gap-2 text-slate-600">
                                        <Mail class="h-4 w-4 text-slate-400" />
                                        <span>{{ c.email || "-" }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="inline-flex items-center gap-2 text-slate-600">
                                        <Phone class="h-4 w-4 text-slate-400" />
                                        <span>{{ c.telefono || "-" }}</span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-slate-600">
                                    {{ c.contacto || "-" }}
                                </td>

                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold transition"
                                        :class="
                                            c.activo
                                                ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                                : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
                                        "
                                        title="Cambiar estado"
                                        @click="toggleActivo(c)"
                                    >
                                        <Power class="h-3.5 w-3.5" />
                                        {{ c.activo ? "Activo" : "Inactivo" }}
                                    </button>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                            title="Editar"
                                            @click="openEdit(c)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-red-500 transition hover:bg-red-50 hover:text-red-700"
                                            title="Eliminar"
                                            @click="removeProveedor(c)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="items.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                            <SearchX class="h-6 w-6" />
                                        </div>
                                        <p class="text-sm font-semibold text-slate-700">
                                            Sin resultados
                                        </p>
                                        <p class="mt-1 text-xs text-slate-400">
                                            Intenta cambiar el texto de búsqueda o registra un nuevo proveedor.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div
                    v-if="meta"
                    class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm text-slate-500 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        Mostrando
                        <span class="font-semibold text-slate-700">{{ meta.from ?? 0 }}</span>
                        –
                        <span class="font-semibold text-slate-700">{{ meta.to ?? 0 }}</span>
                        de
                        <span class="font-semibold text-slate-700">{{ meta.total ?? 0 }}</span>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!meta.prev_page_url || loading"
                            @click="fetchProveedores(meta.current_page - 1)"
                        >
                            <ChevronLeft class="h-4 w-4" />
                            Anterior
                        </button>

                        <span class="rounded-xl bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500">
                            Página {{ meta.current_page }} de {{ meta.last_page }}
                        </span>

                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="!meta.next_page_url || loading"
                            @click="fetchProveedores(meta.current_page + 1)"
                        >
                            Siguiente
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </section>
        </main>

        <ProveedorModal
            :open="modalOpen"
            :title="editando ? 'Editar proveedor' : 'Nuevo proveedor'"
            :subtitle="
                editando
                    ? `Editando ID #${editando.id}`
                    : 'Completa los datos del proveedor'
            "
            :model="form"
            :loading="saving"
            :submitText="editando ? 'Actualizar proveedor' : 'Guardar proveedor'"
            :errors="formErrors"
            @close="modalOpen = false"
            @submit="save"
        />
    </section>
</template>

<script setup>
import { ref, onMounted } from "vue";
import http from "@/lib/http";
import { confirm, toastSuccess, toastWarning, error } from "@/lib/alert";
import ProveedorModal from "@/components/proveedores/ProveedorModal.vue";
import BaseInput from "@/components/ui/BaseInput.vue";

import {
    Building2,
    ChevronLeft,
    ChevronRight,
    Database,
    Loader2,
    Mail,
    Pencil,
    Phone,
    Plus,
    Power,
    Search,
    SearchX,
    Trash2,
    Truck,
    XCircle,
} from "lucide-vue-next";

const q = ref("");
const loading = ref(false);
const saving = ref(false);

const items = ref([]);
const meta = ref(null);

const modalOpen = ref(false);
const editando = ref(null);
const form = ref(getEmptyForm());
const formErrors = ref(null);

function getEmptyForm() {
    return {
        nombre_comercial: "",
        razon_social: "",
        rfc: "",
        telefono: "",
        email: "",
        contacto: "",
        calle: "",
        numero: "",
        colonia: "",
        ciudad: "",
        estado: "",
        cp: "",
        sitio_web: "",
        activo: true,
    };
}

async function fetchProveedores(page = 1) {
    loading.value = true;

    try {
        const { data } = await http.get("/api/proveedores", {
            params: { q: q.value, page },
        });

        items.value = data.data;
        meta.value = data;
    } catch (e) {
        error("Error", "No se pudieron cargar los proveedores.");
    } finally {
        loading.value = false;
    }
}

function limpiarBusqueda() {
    q.value = "";
    fetchProveedores();
}

function openCreate() {
    editando.value = null;
    form.value = getEmptyForm();
    formErrors.value = null;
    modalOpen.value = true;
}

function openEdit(c) {
    editando.value = c;

    form.value = {
        nombre_comercial: c.nombre_comercial ?? "",
        razon_social: c.razon_social ?? "",
        rfc: c.rfc ?? "",
        telefono: c.telefono ?? "",
        email: c.email ?? "",
        contacto: c.contacto ?? "",
        calle: c.calle ?? "",
        numero: c.numero ?? "",
        colonia: c.colonia ?? "",
        ciudad: c.ciudad ?? "",
        estado: c.estado ?? "",
        cp: c.cp ?? "",
        sitio_web: c.sitio_web ?? "",
        activo: !!c.activo,
    };

    formErrors.value = null;
    modalOpen.value = true;
}

async function save() {
    saving.value = true;
    formErrors.value = null;

    try {
        if (editando.value?.id) {
            await http.put(`/api/proveedores/${editando.value.id}`, form.value);
            toastSuccess("Actualizado", "Proveedor actualizado correctamente.");
        } else {
            await http.post("/api/proveedores", form.value);
            toastSuccess("Guardado", "Proveedor registrado correctamente.");
        }

        modalOpen.value = false;
        await fetchProveedores(meta.value?.current_page ?? 1);
    } catch (e) {
        if (e?.response?.status === 422) {
            formErrors.value = e.response.data.errors || {};
            toastWarning("Revisa el formulario", "Hay campos con errores.");
        } else {
            error("Error", "No se pudo guardar el proveedor.");
        }
    } finally {
        saving.value = false;
    }
}

async function removeProveedor(c) {
    const ok = await confirm({
        title: "Eliminar proveedor",
        text: `¿Deseas eliminar a "${c.nombre_comercial}"? Esta acción no se puede deshacer.`,
        confirmText: "Sí, eliminar",
    });

    if (!ok) return;

    try {
        await http.delete(`/api/proveedores/${c.id}`);
        toastSuccess("Proveedor eliminado.");
        await fetchProveedores(meta.value?.current_page ?? 1);
    } catch (e) {
        error("Error", "No se pudo eliminar el proveedor.");
    }
}

async function toggleActivo(c) {
    const prev = !!c.activo;
    c.activo = !prev;

    try {
        await http.put(`/api/proveedores/${c.id}`, {
            nombre_comercial: c.nombre_comercial,
            razon_social: c.razon_social,
            rfc: c.rfc,
            telefono: c.telefono,
            email: c.email,
            contacto: c.contacto,
            calle: c.calle,
            numero: c.numero,
            colonia: c.colonia,
            ciudad: c.ciudad,
            estado: c.estado,
            cp: c.cp,
            sitio_web: c.sitio_web,
            activo: c.activo,
        });

        toastSuccess(
            "Listo",
            c.activo ? "Proveedor activado." : "Proveedor desactivado.",
        );
    } catch (e) {
        c.activo = prev;
        error("Error", "No se pudo cambiar el estado.");
    }
}

onMounted(fetchProveedores);
</script>