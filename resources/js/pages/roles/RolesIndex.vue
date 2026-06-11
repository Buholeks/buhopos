<template>
    <main class="space-y-4 p-3 sm:p-6">
        <!-- Header -->
        <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-50 text-violet-600">
                    <ShieldCheck class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold">Roles y permisos</h1>
                    <p class="text-xs text-slate-500">Define qué puede hacer cada rol en tu empresa.</p>
                </div>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">
                {{ roles.length }} rol(es)
            </span>
        </section>

        <div class="grid gap-4 xl:grid-cols-[300px_1fr]">
            <!-- ── Panel izquierdo: lista de roles ──────────────────────────── -->
            <section class="self-start rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold">Roles</h2>
                    <button
                        type="button"
                        class="flex items-center gap-1 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700"
                        @click="nuevoRol"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Nuevo
                    </button>
                </div>

                <div v-if="cargando" class="px-4 py-8 text-center text-sm text-slate-400">
                    Cargando roles…
                </div>

                <div v-else-if="roles.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">
                    No hay roles creados todavía.
                </div>

                <ul v-else class="divide-y divide-slate-100">
                    <li
                        v-for="rol in roles"
                        :key="rol.id"
                        class="flex cursor-pointer items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50"
                        :class="rolSeleccionado?.id === rol.id ? 'bg-violet-50 hover:bg-violet-50' : ''"
                        @click="seleccionarRol(rol)"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold" :class="rolSeleccionado?.id === rol.id ? 'text-violet-700' : 'text-slate-800'">
                                {{ rol.nombre }}
                            </p>
                            <p class="text-xs text-slate-400">{{ rol.permisos_count }} permiso(s)</p>
                        </div>
                        <ChevronRight class="h-4 w-4 shrink-0 text-slate-300" />
                    </li>
                </ul>
            </section>

            <!-- ── Panel derecho: editor de rol ────────────────────────────── -->
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <!-- Sin selección -->
                <div v-if="!rolSeleccionado && !creandoNuevo" class="flex min-h-[300px] flex-col items-center justify-center gap-3 p-8 text-center text-slate-400">
                    <ShieldCheck class="h-12 w-12 opacity-20" />
                    <p class="text-sm">Selecciona un rol o crea uno nuevo.</p>
                </div>

                <!-- Formulario -->
                <form v-else @submit.prevent="guardar">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold">
                            {{ creandoNuevo ? 'Nuevo rol' : 'Editar rol' }}
                        </h2>
                        <button
                            v-if="!creandoNuevo"
                            type="button"
                            class="flex items-center gap-1.5 rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                            :disabled="guardando"
                            @click="eliminarRol"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Eliminar
                        </button>
                    </div>

                    <div class="space-y-5 p-5">
                        <!-- Nombre -->
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Nombre del rol</label>
                            <input
                                v-model="form.nombre"
                                type="text"
                                placeholder="Ej. Cajero, Gerente, Supervisor…"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                                :class="errores.nombre ? 'border-rose-400' : ''"
                                required
                            />
                            <p v-if="errores.nombre" class="mt-1 text-xs text-rose-600">{{ errores.nombre }}</p>
                        </div>

                        <!-- Descripción -->
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Descripción <span class="font-normal normal-case text-slate-400">(opcional)</span></label>
                            <input
                                v-model="form.descripcion"
                                type="text"
                                placeholder="Para qué sirve este rol…"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                            />
                        </div>

                        <!-- Checklist de permisos -->
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Permisos</label>
                                <div class="flex gap-2">
                                    <button type="button" class="text-xs text-violet-600 hover:underline" @click="seleccionarTodos">Todos</button>
                                    <span class="text-slate-300">·</span>
                                    <button type="button" class="text-xs text-slate-400 hover:underline" @click="deseleccionarTodos">Ninguno</button>
                                </div>
                            </div>

                            <div v-if="cargandoPermisos" class="py-4 text-center text-sm text-slate-400">
                                Cargando permisos…
                            </div>

                            <div v-else class="space-y-4">
                                <div v-for="(grupo, modulo) in permisosAgrupados" :key="modulo">
                                    <!-- Cabecera de módulo -->
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700">{{ modulo }}</span>
                                        <div class="h-px flex-1 bg-slate-100"></div>
                                        <button
                                            type="button"
                                            class="text-[10px] text-violet-500 hover:underline"
                                            @click="toggleModulo(grupo)"
                                        >
                                            {{ moduloCompleto(grupo) ? 'Quitar todos' : 'Seleccionar todos' }}
                                        </button>
                                    </div>

                                    <!-- Permisos del módulo -->
                                    <div class="mt-1.5 grid gap-2 sm:grid-cols-2">
                                        <label
                                            v-for="permiso in grupo"
                                            :key="permiso.id"
                                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 p-3 hover:border-violet-200 hover:bg-violet-50"
                                            :class="form.permisos.includes(permiso.id) ? 'border-violet-200 bg-violet-50' : ''"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="permiso.id"
                                                v-model="form.permisos"
                                                class="mt-0.5 accent-violet-600"
                                            />
                                            <span class="text-xs leading-relaxed text-slate-700">{{ permiso.descripcion }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex justify-end gap-3 border-t border-slate-100 px-5 py-4">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="cancelar"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                            :disabled="guardando || !form.nombre.trim()"
                        >
                            <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                            {{ guardando ? 'Guardando…' : (creandoNuevo ? 'Crear rol' : 'Guardar cambios') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { ChevronRight, Loader2, Plus, ShieldCheck, Trash2 } from "lucide-vue-next";
import http from "@/lib/http";
import { confirm, toastSuccess, toastError } from "@/lib/alert";

// ── Estado ────────────────────────────────────────────────────────────────────

const roles            = ref([]);
const permisosAgrupados = ref({});
const cargando         = ref(false);
const cargandoPermisos = ref(false);
const guardando        = ref(false);

const rolSeleccionado  = ref(null);
const creandoNuevo     = ref(false);
const errores          = ref({});

const form = ref({ nombre: "", descripcion: "", permisos: [] });

// ── Helpers ───────────────────────────────────────────────────────────────────

function resetForm() {
    form.value = { nombre: "", descripcion: "", permisos: [] };
    errores.value = {};
}

function moduloCompleto(grupo) {
    return grupo.every((p) => form.value.permisos.includes(p.id));
}

function toggleModulo(grupo) {
    const ids = grupo.map((p) => p.id);
    if (moduloCompleto(grupo)) {
        form.value.permisos = form.value.permisos.filter((id) => !ids.includes(id));
    } else {
        form.value.permisos = [...new Set([...form.value.permisos, ...ids])];
    }
}

function seleccionarTodos() {
    form.value.permisos = Object.values(permisosAgrupados.value)
        .flat()
        .map((p) => p.id);
}

function deseleccionarTodos() {
    form.value.permisos = [];
}

// ── Carga inicial ─────────────────────────────────────────────────────────────

async function cargarRoles() {
    cargando.value = true;
    try {
        const { data } = await http.get("/api/roles");
        roles.value = data;
    } catch {
        toastError("No se pudieron cargar los roles.");
    } finally {
        cargando.value = false;
    }
}

async function cargarPermisos() {
    cargandoPermisos.value = true;
    try {
        const { data } = await http.get("/api/permisos");
        permisosAgrupados.value = data;
    } catch {
        toastError("No se pudieron cargar los permisos.");
    } finally {
        cargandoPermisos.value = false;
    }
}

onMounted(async () => {
    await Promise.all([cargarRoles(), cargarPermisos()]);
});

// ── Acciones ──────────────────────────────────────────────────────────────────

function nuevoRol() {
    rolSeleccionado.value = null;
    creandoNuevo.value = true;
    resetForm();
}

async function seleccionarRol(rol) {
    creandoNuevo.value = false;
    errores.value = {};
    try {
        const { data } = await http.get(`/api/roles/${rol.id}`);
        rolSeleccionado.value = data;
        form.value = {
            nombre:      data.nombre,
            descripcion: data.descripcion ?? "",
            permisos:    data.permisos.map((p) => p.id),
        };
    } catch {
        toastError("No se pudo cargar el rol.");
    }
}

function cancelar() {
    rolSeleccionado.value = null;
    creandoNuevo.value = false;
    resetForm();
}

async function guardar() {
    guardando.value = true;
    errores.value = {};
    try {
        if (creandoNuevo.value) {
            const { data } = await http.post("/api/roles", form.value);
            roles.value.push({ ...data, permisos_count: data.permisos?.length ?? 0 });
            roles.value.sort((a, b) => a.nombre.localeCompare(b.nombre));
            toastSuccess(`Rol "${data.nombre}" creado.`);
            cancelar();
        } else {
            const { data } = await http.put(`/api/roles/${rolSeleccionado.value.id}`, form.value);
            const idx = roles.value.findIndex((r) => r.id === data.id);
            if (idx !== -1) {
                roles.value[idx] = { ...data, permisos_count: data.permisos?.length ?? 0 };
            }
            rolSeleccionado.value = data;
            toastSuccess(`Rol "${data.nombre}" actualizado.`);
        }
    } catch (e) {
        const apiErrores = e?.response?.data?.errors ?? {};
        errores.value = Object.fromEntries(
            Object.entries(apiErrores).map(([k, v]) => [k, v[0]])
        );
        const msg = e?.response?.data?.message || "No se pudo guardar el rol.";
        if (!Object.keys(errores.value).length) toastError(msg);
    } finally {
        guardando.value = false;
    }
}

async function eliminarRol() {
    if (!rolSeleccionado.value) return;
    const ok = await confirm({
        title: `¿Eliminar el rol "${rolSeleccionado.value.nombre}"?`,
        text: "Esta acción no se puede deshacer.",
        confirmText: "Sí, eliminar",
    });
    if (!ok) return;

    try {
        await http.delete(`/api/roles/${rolSeleccionado.value.id}`);
        roles.value = roles.value.filter((r) => r.id !== rolSeleccionado.value.id);
        toastSuccess("Rol eliminado.");
        cancelar();
    } catch (e) {
        const msg = e?.response?.data?.message || "No se pudo eliminar el rol.";
        toastError(msg);
    }
}
</script>
