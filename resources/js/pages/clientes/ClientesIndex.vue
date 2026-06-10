<template>
    <main class="space-y-4 p-3 sm:p-6">
        <!-- Header -->
        <section
            class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"
                >
                    <Users class="h-5 w-5" />
                </div>

                <div>
                    <h1 class="text-lg font-semibold text-slate-900">
                        Clientes
                    </h1>
                    <p class="text-xs text-slate-500">
                        Alta, edición y administración de clientes.
                    </p>
                </div>
            </div>

            <button
                v-if="auth.can('clientes.editar')"
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                @click="openCreate"
            >
                <UserPlus class="h-4 w-4" />
                Nuevo cliente
            </button>
        </section>

        <!-- Search -->
        <section
            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <BaseInput
                        v-model="q"
                        label="Buscar cliente"
                        type="text"
                        placeholder="Nombre, correo o teléfono..."
                        @keydown.enter.prevent="fetchClientes(1)"
                    >
                        <template #icon>
                            <Search class="h-4 w-4" />
                        </template>

                        <template #suffix>
                            <button
                                v-if="q && !loading"
                                type="button"
                                class="text-slate-400 transition hover:text-slate-700"
                                @click="clearSearch"
                            >
                                <X class="h-4 w-4" />
                            </button>

                            <Loader2
                                v-if="loading"
                                class="h-4 w-4 animate-spin text-slate-400"
                            />
                        </template>
                    </BaseInput>
                </div>

                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading"
                    @click="fetchClientes(1)"
                >
                    <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                    <Search v-else class="h-4 w-4" />
                    {{ loading ? "Buscando..." : "Buscar" }}
                </button>
            </div>
        </section>

        <!-- Table -->
        <section
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div
                class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3"
            >
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Listado de clientes
                    </h2>
                    <p class="text-xs text-slate-500">
                        {{ meta?.total ?? 0 }} cliente(s) registrados
                    </p>
                </div>

                <span
                    v-if="loading"
                    class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500"
                >
                    <Loader2 class="h-3.5 w-3.5 animate-spin" />
                    Cargando
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead
                        class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"
                    >
                        <tr>
                            <th class="px-4 py-3 text-left">Cliente</th>
                            <th class="px-4 py-3 text-left">Contacto</th>
                            <th class="px-4 py-3 text-left">Dirección</th>
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
                                    <div
                                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-500"
                                    >
                                        {{ iniciales(c.nombre) }}
                                    </div>

                                    <div class="min-w-0">
                                        <p
                                            class="truncate font-semibold text-slate-900"
                                        >
                                            {{ c.nombre }}
                                        </p>
                                        <p class="text-xs text-slate-400">
                                            ID #{{ c.id }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <div class="space-y-1">
                                    <p
                                        class="flex items-center gap-1.5 text-slate-700"
                                    >
                                        <Mail
                                            class="h-3.5 w-3.5 text-slate-400"
                                        />
                                        {{ c.correo ?? "Sin correo" }}
                                    </p>
                                    <p
                                        class="flex items-center gap-1.5 text-slate-500"
                                    >
                                        <Phone
                                            class="h-3.5 w-3.5 text-slate-400"
                                        />
                                        {{ c.telefono ?? "Sin teléfono" }}
                                    </p>
                                </div>
                            </td>

                            <td class="max-w-xs px-4 py-3 text-slate-600">
                                <span class="line-clamp-1">
                                    {{ c.direccion ?? "Sin dirección" }}
                                </span>
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
                                    @click="toggleActivo(c)"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            c.activo
                                                ? 'bg-emerald-500'
                                                : 'bg-slate-400'
                                        "
                                    ></span>
                                    {{ c.activo ? "Activo" : "Inactivo" }}
                                </button>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        v-if="auth.can('clientes.editar')"
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                        title="Editar"
                                        @click="openEdit(c)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>

                                    <button
                                        v-if="auth.can('clientes.editar')"
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600"
                                        title="Eliminar"
                                        @click="removeCliente(c)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!loading && items.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div
                                    class="mx-auto flex max-w-sm flex-col items-center"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
                                    >
                                        <Inbox class="h-5 w-5" />
                                    </div>
                                    <h3
                                        class="mt-3 text-sm font-semibold text-slate-800"
                                    >
                                        Sin clientes encontrados
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Intenta cambiar la búsqueda o registra
                                        un nuevo cliente.
                                    </p>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="loading && items.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-12 text-center text-sm text-slate-500"
                            >
                                <div class="inline-flex items-center gap-2">
                                    <Loader2
                                        class="h-4 w-4 animate-spin text-emerald-600"
                                    />
                                    Cargando clientes...
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="meta"
                class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="text-xs text-slate-500">
                    Mostrando
                    <span class="font-semibold text-slate-700">{{
                        meta.from ?? 0
                    }}</span>
                    –
                    <span class="font-semibold text-slate-700">{{
                        meta.to ?? 0
                    }}</span>
                    de
                    <span class="font-semibold text-slate-700">{{
                        meta.total ?? 0
                    }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!meta.prev_page_url || loading"
                        @click="fetchClientes(meta.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                        Anterior
                    </button>

                    <span
                        class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600"
                    >
                        {{ meta.current_page }} / {{ meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!meta.next_page_url || loading"
                        @click="fetchClientes(meta.current_page + 1)"
                    >
                        Siguiente
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </section>

        <!-- Modal -->
        <BaseModal
            :open="modalOpen"
            :title="editando ? 'Editar cliente' : 'Nuevo cliente'"
            :subtitle="
                editando
                    ? `ID #${editando.id}`
                    : 'Completa los datos del cliente'
            "
            size="md"
            @close="modalOpen = false"
        >
            <ClienteForm
                :model="form"
                :loading="saving"
                :submitText="editando ? 'Actualizar' : 'Guardar'"
                :errors="formErrors"
                @submit="save"
                @cancel="modalOpen = false"
            />
        </BaseModal>
    </main>
</template>

<script setup>
import { onMounted, ref } from "vue";
import http from "@/lib/http";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();
import { confirm, toastSuccess, toastWarning, error } from "@/lib/alert";
import BaseModal from "@/components/ui/BaseModal.vue";
import ClienteForm from "@/components/clientes/ClienteForm.vue";
import BaseInput from "@/components/ui/BaseInput.vue";

import {
    ChevronLeft,
    ChevronRight,
    Inbox,
    Loader2,
    Mail,
    Pencil,
    Phone,
    Search,
    Trash2,
    UserPlus,
    Users,
    X,
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
        nombre: "",
        correo: "",
        telefono: "",
        direccion: "",
        activo: true,
    };
}

function iniciales(nombre) {
    if (!nombre) return "CL";

    return nombre
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0])
        .join("")
        .toUpperCase();
}

function clearSearch() {
    q.value = "";
    fetchClientes(1);
}

async function fetchClientes(page = 1) {
    loading.value = true;

    try {
        const { data } = await http.get("/api/clientes", {
            params: {
                q: q.value || undefined,
                page,
            },
        });

        items.value = data.data ?? [];
        meta.value = data;
    } catch (e) {
        error("Error", "No se pudieron cargar los clientes.");
    } finally {
        loading.value = false;
    }
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
        nombre: c.nombre ?? "",
        correo: c.correo ?? "",
        telefono: c.telefono ?? "",
        direccion: c.direccion ?? "",
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
            await http.put(`/api/clientes/${editando.value.id}`, form.value);
            toastSuccess("Cliente actualizado correctamente.");
        } else {
            await http.post("/api/clientes", form.value);
            toastSuccess("Cliente registrado correctamente.");
        }

        modalOpen.value = false;
        await fetchClientes(meta.value?.current_page ?? 1);
    } catch (e) {
        if (e?.response?.status === 422) {
            formErrors.value = e.response.data.errors || {};
            toastWarning("Hay campos con errores, revisa el formulario.");
        } else {
            error("Error", "No se pudo guardar el cliente.");
        }
    } finally {
        saving.value = false;
    }
}

async function removeCliente(c) {
    const ok = await confirm({
        title: "Eliminar cliente",
        text: `¿Deseas eliminar a "${c.nombre}"? Esta acción no se puede deshacer.`,
        confirmText: "Sí, eliminar",
    });

    if (!ok) return;

    try {
        await http.delete(`/api/clientes/${c.id}`);
        toastSuccess("Cliente eliminado.");

        const currentPage = meta.value?.current_page ?? 1;
        const isLastItemInPage = items.value.length === 1 && currentPage > 1;

        await fetchClientes(isLastItemInPage ? currentPage - 1 : currentPage);
    } catch (e) {
        error("Error", "No se pudo eliminar el cliente.");
    }
}

async function toggleActivo(c) {
    const prev = !!c.activo;
    c.activo = !prev;

    try {
        await http.put(`/api/clientes/${c.id}`, {
            nombre: c.nombre,
            correo: c.correo,
            telefono: c.telefono,
            direccion: c.direccion,
            activo: c.activo,
        });

        toastSuccess(c.activo ? "Cliente activado." : "Cliente desactivado.");
    } catch (e) {
        c.activo = prev;
        error("Error", "No se pudo cambiar el estado.");
    }
}

onMounted(() => fetchClientes(1));
</script>
