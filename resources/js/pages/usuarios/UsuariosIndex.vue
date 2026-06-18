<template>
    <main class="space-y-4 p-3 sm:p-6">
        <!-- Header -->
        <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                    <UsersRound class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold">Usuarios</h1>
                    <p class="text-xs text-slate-500">Crea cuentas, asigna sucursales y roles.</p>
                </div>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">
                {{ meta.total }} usuario(s)
            </span>
        </section>

        <!-- Aviso primer super admin -->
        <section
            v-if="meta.puede_promover_primer_super_admin"
            class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <Crown class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold">Esta empresa todavía no tiene superadministrador.</p>
                    <p class="text-xs text-amber-700">Promueve a un usuario activo desde la columna de acciones.</p>
                </div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[400px_1fr]">
            <!-- ── Panel izquierdo: formulario crear / editar ──────────────── -->
            <section class="self-start rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold">{{ modoEdicion ? 'Editar usuario' : 'Crear usuario' }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ modoEdicion ? usuarioEditandoDatos?.name : 'La empresa se asigna desde tu sesión activa.' }}</p>
                    </div>
                    <button
                        v-if="modoEdicion"
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        title="Cancelar edición"
                        @click="cancelarEdicion"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <form class="space-y-4 p-4" @submit.prevent="modoEdicion ? guardarEdicion() : crearUsuario()">
                    <!-- Empresa (solo creación) -->
                    <BaseInput
                        v-if="!modoEdicion"
                        :model-value="auth.empresaNombre"
                        label="Empresa"
                        disabled
                        hint="No puede cambiarse durante el alta."
                    >
                        <template #icon><Building2 class="h-4 w-4" /></template>
                    </BaseInput>

                    <!-- Sucursal inicial (solo creación) -->
                    <BaseSearchSelect
                        v-if="!modoEdicion"
                        v-model="form.sucursal_id"
                        label="Sucursal inicial"
                        placeholder="Selecciona una sucursal"
                        :items="sucursales"
                        label-key="nombre"
                        sub-label-key="direccion"
                        value-key="id"
                        :error="fieldError('sucursal_id')"
                        required
                    />

                    <!-- Rol sucursal inicial (solo creación) -->
                    <div v-if="!modoEdicion">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Rol en la sucursal inicial
                        </label>
                        <select
                            v-model="form.role_id"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                        >
                            <option :value="null">Sin rol (acceso total legacy)</option>
                            <option v-for="rol in roles" :key="rol.id" :value="rol.id">{{ rol.nombre }}</option>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400">Puedes cambiar sucursales y roles después.</p>
                    </div>

                    <!-- Nombre -->
                    <BaseInput
                        v-model="form.name"
                        label="Nombre"
                        placeholder="Nombre completo"
                        :error="fieldError('name')"
                        required
                    >
                        <template #icon><UserRound class="h-4 w-4" /></template>
                    </BaseInput>

                    <!-- Email -->
                    <BaseInput
                        v-model="form.email"
                        label="Correo de acceso"
                        type="email"
                        placeholder="usuario@empresa.com"
                        autocomplete="off"
                        :error="fieldError('email')"
                        required
                    >
                        <template #icon><Mail class="h-4 w-4" /></template>
                    </BaseInput>

                    <!-- Contraseña -->
                    <div v-if="modoEdicion" class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Cambiar contraseña <span class="font-normal normal-case text-slate-400">(opcional)</span>
                        </p>
                        <div class="space-y-3">
                            <BaseInput
                                v-model="form.password"
                                label="Nueva contraseña"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Dejar vacío para no cambiar"
                                :error="fieldError('password')"
                            >
                                <template #icon><LockKeyhole class="h-4 w-4" /></template>
                            </BaseInput>
                            <BaseInput
                                v-if="form.password"
                                v-model="form.password_confirmation"
                                label="Confirmar contraseña"
                                type="password"
                                autocomplete="new-password"
                                required
                            >
                                <template #icon><LockKeyhole class="h-4 w-4" /></template>
                            </BaseInput>
                        </div>
                    </div>

                    <div v-if="!modoEdicion" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <BaseInput
                            v-model="form.password"
                            label="Contraseña"
                            type="password"
                            autocomplete="new-password"
                            :error="fieldError('password')"
                            required
                        >
                            <template #icon><LockKeyhole class="h-4 w-4" /></template>
                        </BaseInput>
                        <BaseInput
                            v-model="form.password_confirmation"
                            label="Confirmar contraseña"
                            type="password"
                            autocomplete="new-password"
                            required
                        >
                            <template #icon><LockKeyhole class="h-4 w-4" /></template>
                        </BaseInput>
                    </div>

                    <div v-if="mensajeError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        {{ mensajeError }}
                    </div>

                    <div class="flex gap-2">
                        <button
                            v-if="modoEdicion"
                            type="button"
                            class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="cancelarEdicion"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="guardando || !puedeGuardar"
                        >
                            <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                            <UserPlus v-else-if="!modoEdicion" class="h-4 w-4" />
                            <Save v-else class="h-4 w-4" />
                            {{ guardando ? (modoEdicion ? 'Guardando…' : 'Creando...') : (modoEdicion ? 'Guardar cambios' : 'Crear usuario') }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- ── Tabla de usuarios ──────────────────────────────────────── -->
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold">Usuarios de {{ auth.empresaNombre }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Gestiona accesos, sucursales y roles.</p>
                    </div>
                    <BaseInput v-model="busqueda" placeholder="Buscar nombre o correo" root-class="w-full sm:w-72" @input="debounceBuscar">
                        <template #icon><Search class="h-4 w-4" /></template>
                    </BaseInput>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Usuario</th>
                                <th class="px-4 py-3 text-left">Sucursal activa</th>
                                <th class="px-4 py-3 text-left">Rol</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="usuario in usuarios"
                                :key="usuario.id"
                                class="hover:bg-slate-50"
                                :class="usuarioEditandoDatos?.id === usuario.id ? 'bg-emerald-50/50' : ''"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">
                                        {{ usuario.name }}
                                        <span v-if="usuario.es_super_admin" class="ml-1.5 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                                            Super Admin
                                        </span>
                                    </p>
                                    <p class="text-xs text-slate-500">{{ usuario.email }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ usuario.sucursal?.nombre ?? "Sin sucursal" }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="usuario.rol_activo" class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 ring-1 ring-violet-200">
                                        {{ usuario.rol_activo }}
                                    </span>
                                    <span v-else-if="!usuario.es_super_admin" class="text-xs text-slate-400">Sin rol</span>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        class="rounded-full px-2.5 py-1 text-xs font-medium transition"
                                        :class="usuario.activo
                                            ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                            : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                        :disabled="usuario.es_super_admin && usuario.id === auth.user?.id"
                                        @click="toggleActivo(usuario)"
                                    >
                                        {{ usuario.activo ? "Activo" : "Inactivo" }}
                                    </button>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <!-- Editar datos -->
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            :class="usuarioEditandoDatos?.id === usuario.id ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : ''"
                                            @click="abrirEdicion(usuario)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                        <!-- Sucursales y roles -->
                                        <button
                                            v-if="!usuario.es_super_admin"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="abrirModalSucursales(usuario)"
                                        >
                                            <Building2 class="h-3.5 w-3.5" />
                                            Sucursales / Roles
                                        </button>
                                        <!-- Super admin -->
                                        <button
                                            v-if="puedeCambiarSuperAdmin(usuario)"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold"
                                            :class="usuario.es_super_admin
                                                ? 'border-rose-200 text-rose-600 hover:bg-rose-50'
                                                : 'border-amber-200 text-amber-700 hover:bg-amber-50'"
                                            @click="toggleSuperAdmin(usuario)"
                                        >
                                            <Crown class="h-3.5 w-3.5" />
                                            {{ usuario.es_super_admin ? "Retirar nivel" : "Super admin" }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="cargando" class="border-t border-slate-100 px-4 py-8 text-center text-sm text-slate-400">
                    <Loader2 class="mx-auto mb-2 h-5 w-5 animate-spin" />
                    Cargando usuarios...
                </div>
                <div v-else-if="!usuarios.length" class="border-t border-slate-100 px-4 py-10 text-center text-sm text-slate-400">
                    No hay usuarios registrados.
                </div>
            </section>
        </div>

        <!-- ── Modal: Gestionar sucursales y roles ──────────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                <div v-if="modalSucursales" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @mousedown.self="cerrarModalSucursales">
                    <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div>
                                <p class="font-semibold text-slate-900">Sucursales y roles de {{ usuarioModalSucursales?.name }}</p>
                                <p class="text-xs text-slate-400">Asigna sucursales y el rol en cada una.</p>
                            </div>
                            <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="cerrarModalSucursales">
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="max-h-[60vh] overflow-y-auto p-5">
                            <div v-if="cargandoSucursalesModal" class="py-8 text-center text-sm text-slate-400">
                                Cargando sucursales…
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="suc in sucursalesModal"
                                    :key="suc.id"
                                    class="flex items-center gap-4 rounded-xl border p-3"
                                    :class="suc.asignada ? 'border-emerald-200 bg-emerald-50' : 'border-slate-100'"
                                >
                                    <input type="checkbox" v-model="suc.asignada" class="h-4 w-4 accent-emerald-600" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-800">{{ suc.nombre }}</p>
                                        <p v-if="suc.direccion" class="truncate text-xs text-slate-400">{{ suc.direccion }}</p>
                                    </div>
                                    <select
                                        v-if="suc.asignada"
                                        v-model="suc.role_id"
                                        class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs outline-none focus:border-emerald-500"
                                    >
                                        <option :value="null">Sin rol</option>
                                        <option v-for="rol in roles" :key="rol.id" :value="rol.id">{{ rol.nombre }}</option>
                                    </select>
                                    <span v-else class="text-xs text-slate-300">—</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 border-t border-slate-100 px-5 py-4">
                            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="cerrarModalSucursales">
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                :disabled="guardandoSucursales"
                                @click="guardarSucursales"
                            >
                                <Loader2 v-if="guardandoSucursales" class="h-4 w-4 animate-spin" />
                                {{ guardandoSucursales ? "Guardando…" : "Guardar cambios" }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import http from "@/lib/http";
import { confirm, toastSuccess, toastError } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";
import {
    Building2, Crown, Loader2, LockKeyhole, Mail, Pencil,
    Save, Search, UserPlus, UserRound, UsersRound, X,
} from "lucide-vue-next";

const auth = useAuthStore();

// ── Estado general ────────────────────────────────────────────────────────────
const sucursales   = ref([]);
const roles        = ref([]);
const usuarios     = ref([]);
const cargando     = ref(false);
const guardando    = ref(false);
const busqueda     = ref("");
const errors       = ref({});
const mensajeError = ref("");
const meta         = ref({
    total: 0,
    super_admins_activos: 0,
    puede_gestionar_super_admins: false,
    puede_promover_primer_super_admin: false,
});
let timer;

// ── Modo edición ──────────────────────────────────────────────────────────────
const modoEdicion          = ref(false);
const usuarioEditandoDatos = ref(null);

// ── Estado modal sucursales ───────────────────────────────────────────────────
const modalSucursales         = ref(false);
const usuarioModalSucursales  = ref(null);
const sucursalesModal         = ref([]);
const cargandoSucursalesModal = ref(false);
const guardandoSucursales     = ref(false);

// ── Formulario ────────────────────────────────────────────────────────────────
const form = reactive({
    sucursal_id:           null,
    role_id:               null,
    name:                  "",
    email:                 "",
    password:              "",
    password_confirmation: "",
});

const puedeGuardar = computed(() => {
    if (!form.name.trim() || form.name.trim().length < 2) return false;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) return false;
    if (modoEdicion.value) {
        if (form.password && form.password.length < 8) return false;
        if (form.password && form.password !== form.password_confirmation) return false;
        return true;
    }
    return !!form.sucursal_id && form.password.length >= 8 && form.password === form.password_confirmation;
});

// ── Carga inicial ─────────────────────────────────────────────────────────────
onMounted(async () => {
    await Promise.all([cargarSucursales(), cargarRoles(), cargarUsuarios()]);
});

async function cargarSucursales() {
    const { data } = await http.get("/api/users/sucursales-disponibles");
    sucursales.value = data ?? [];
}

async function cargarRoles() {
    try {
        const { data } = await http.get("/api/roles");
        roles.value = data ?? [];
    } catch {
        roles.value = [];
    }
}

async function cargarUsuarios() {
    cargando.value = true;
    try {
        const { data } = await http.get("/api/users", { params: { q: busqueda.value || undefined } });
        usuarios.value = data.data ?? [];
        meta.value = {
            total: data.total ?? 0,
            super_admins_activos: data.super_admins_activos ?? 0,
            puede_gestionar_super_admins: !!data.puede_gestionar_super_admins,
            puede_promover_primer_super_admin: !!data.puede_promover_primer_super_admin,
        };
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo cargar la lista de usuarios.");
    } finally {
        cargando.value = false;
    }
}

function debounceBuscar() {
    clearTimeout(timer);
    timer = setTimeout(cargarUsuarios, 350);
}

// ── Crear usuario ─────────────────────────────────────────────────────────────
async function crearUsuario() {
    guardando.value = true;
    errors.value    = {};
    mensajeError.value = "";
    try {
        const { data } = await http.post("/api/users", form);
        toastSuccess(`Usuario ${data.name} creado`);
        limpiarFormulario();
        await cargarUsuarios();
    } catch (error) {
        errors.value       = error?.response?.data?.errors ?? {};
        mensajeError.value = error?.response?.data?.message || "No se pudo crear el usuario.";
    } finally {
        guardando.value = false;
    }
}

// ── Editar usuario ────────────────────────────────────────────────────────────
function abrirEdicion(usuario) {
    if (usuarioEditandoDatos.value?.id === usuario.id) {
        cancelarEdicion();
        return;
    }
    modoEdicion.value          = true;
    usuarioEditandoDatos.value = usuario;
    errors.value               = {};
    mensajeError.value         = "";
    form.sucursal_id           = null;
    form.role_id               = null;
    form.name                  = usuario.name;
    form.email                 = usuario.email;
    form.password              = "";
    form.password_confirmation = "";
}

function cancelarEdicion() {
    modoEdicion.value          = false;
    usuarioEditandoDatos.value = null;
    limpiarFormulario();
}

async function guardarEdicion() {
    guardando.value    = true;
    errors.value       = {};
    mensajeError.value = "";
    try {
        const payload = { name: form.name, email: form.email };
        if (form.password) {
            payload.password              = form.password;
            payload.password_confirmation = form.password_confirmation;
        }
        const { data } = await http.put(`/api/users/${usuarioEditandoDatos.value.id}`, payload);
        toastSuccess("Usuario actualizado.");
        const idx = usuarios.value.findIndex((u) => u.id === data.id);
        if (idx !== -1) {
            usuarios.value[idx] = { ...usuarios.value[idx], name: data.name, email: data.email };
        }
        cancelarEdicion();
    } catch (error) {
        errors.value       = error?.response?.data?.errors ?? {};
        mensajeError.value = error?.response?.data?.message || "No se pudo actualizar el usuario.";
    } finally {
        guardando.value = false;
    }
}

function limpiarFormulario() {
    form.sucursal_id           = null;
    form.role_id               = null;
    form.name                  = "";
    form.email                 = "";
    form.password              = "";
    form.password_confirmation = "";
    errors.value               = {};
    mensajeError.value         = "";
}

function fieldError(campo) {
    return errors.value?.[campo]?.[0] ?? "";
}

// ── Activar / desactivar ──────────────────────────────────────────────────────
async function toggleActivo(usuario) {
    try {
        const { data } = await http.put(`/api/users/${usuario.id}`, { activo: !usuario.activo });
        usuario.activo = data.activo;
        toastSuccess(data.activo ? "Usuario activado." : "Usuario desactivado.");
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo actualizar el usuario.");
    }
}

// ── Super admin ───────────────────────────────────────────────────────────────
function puedeCambiarSuperAdmin(usuario) {
    if (usuario.es_super_admin && usuario.id === auth.user?.id) return false;
    if (meta.value.puede_gestionar_super_admins) return true;
    return meta.value.puede_promover_primer_super_admin && !usuario.es_super_admin && usuario.activo;
}

async function toggleSuperAdmin(usuario) {
    const promover = !usuario.es_super_admin;
    const ok = await confirm({
        title: promover ? "¿Promover a super administrador?" : "¿Retirar nivel de super administrador?",
        text: `${usuario.name} ${promover ? "tendrá acceso total a la empresa." : "perderá el acceso total."}`,
        confirmText: promover ? "Sí, promover" : "Sí, retirar",
        icon: promover ? "question" : "warning",
    });
    if (!ok) return;

    try {
        await http.put(`/api/users/${usuario.id}/super-admin`, { es_super_admin: promover });
        if (usuario.id === auth.user?.id) await auth.fetchUser();
        toastSuccess(promover ? "Superadministrador asignado." : "Nivel de superadministrador retirado.");
        await cargarUsuarios();
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo actualizar el nivel del usuario.");
    }
}

// ── Modal sucursales / roles ──────────────────────────────────────────────────
async function abrirModalSucursales(usuario) {
    usuarioModalSucursales.value  = usuario;
    modalSucursales.value         = true;
    cargandoSucursalesModal.value = true;
    sucursalesModal.value         = [];

    try {
        const { data } = await http.get(`/api/users/${usuario.id}/sucursales`);
        sucursalesModal.value = data;
    } catch {
        toastError("No se pudieron cargar las sucursales.");
        cerrarModalSucursales();
    } finally {
        cargandoSucursalesModal.value = false;
    }
}

function cerrarModalSucursales() {
    modalSucursales.value        = false;
    usuarioModalSucursales.value = null;
    sucursalesModal.value        = [];
}

async function guardarSucursales() {
    guardandoSucursales.value = true;
    try {
        const payload = sucursalesModal.value
            .filter((s) => s.asignada)
            .map((s) => ({ id: s.id, role_id: s.role_id ?? null }));

        await http.put(`/api/users/${usuarioModalSucursales.value.id}/sucursales`, { sucursales: payload });
        toastSuccess("Sucursales y roles actualizados.");
        cerrarModalSucursales();
        await cargarUsuarios();
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo guardar.");
    } finally {
        guardandoSucursales.value = false;
    }
}

// ── Utilidades ────────────────────────────────────────────────────────────────
function fmtFecha(valor) {
    if (!valor) return "—";
    return new Date(valor).toLocaleDateString("es-MX", { day: "2-digit", month: "short", year: "numeric" });
}
</script>
