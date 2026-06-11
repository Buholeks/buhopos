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

        <section
            v-if="meta.puede_promover_primer_super_admin"
            class="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-start gap-3">
                <Crown class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold">Esta empresa todavía no tiene superadministrador.</p>
                    <p class="text-xs text-amber-700">Promueve a un usuario activo desde la columna de acciones. Después, solo un superadministrador podrá otorgar o retirar ese nivel.</p>
                </div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[420px_1fr]">
            <!-- ── Formulario de creación ──────────────────────────────────── -->
            <section class="self-start rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold">Crear usuario</h2>
                    <p class="mt-0.5 text-xs text-slate-500">La empresa se asigna desde tu sesión activa.</p>
                </div>

                <form class="space-y-4 p-4" @submit.prevent="crearUsuario">
                    <BaseInput
                        :model-value="auth.empresaNombre"
                        label="Empresa"
                        disabled
                        hint="No puede cambiarse durante el alta."
                    >
                        <template #icon><Building2 class="h-4 w-4" /></template>
                    </BaseInput>

                    <BaseSearchSelect
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

                    <!-- Rol para la sucursal inicial -->
                    <div>
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
                        <p class="mt-1 text-[11px] text-slate-400">Puedes cambiar las sucursales y roles después.</p>
                    </div>

                    <BaseInput
                        v-model="form.name"
                        label="Nombre"
                        placeholder="Nombre completo"
                        :error="fieldError('name')"
                        required
                    >
                        <template #icon><UserRound class="h-4 w-4" /></template>
                    </BaseInput>

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

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
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

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="guardando || !puedeGuardar"
                    >
                        <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                        <UserPlus v-else class="h-4 w-4" />
                        {{ guardando ? "Creando..." : "Crear usuario" }}
                    </button>
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
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-left">Creado</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="usuario in usuarios" :key="usuario.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div>
                                            <p class="font-medium text-slate-900">
                                                {{ usuario.name }}
                                                <span v-if="usuario.es_super_admin" class="ml-1.5 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                                                    Super Admin
                                                </span>
                                            </p>
                                            <p class="text-xs text-slate-500">{{ usuario.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ usuario.sucursal?.nombre ?? "Sin sucursal" }}</td>
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
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ fmtFecha(usuario.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            v-if="!usuario.es_super_admin"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            @click="abrirModalSucursales(usuario)"
                                        >
                                            <Building2 class="h-3.5 w-3.5" />
                                            Sucursales
                                        </button>
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
                                            {{ usuario.es_super_admin ? "Retirar nivel" : "Hacer super admin" }}
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

        <!-- ── Modal: Gestionar sucursales del usuario ──────────────────────── -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                <div v-if="modalSucursales" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @mousedown.self="cerrarModalSucursales">
                    <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div>
                                <p class="font-semibold text-slate-900">Sucursales de {{ usuarioEditando?.name }}</p>
                                <p class="text-xs text-slate-400">Asigna sucursales y el rol en cada una.</p>
                            </div>
                            <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="cerrarModalSucursales">
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Body -->
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
                                    <!-- Toggle asignada -->
                                    <input
                                        type="checkbox"
                                        v-model="suc.asignada"
                                        class="h-4 w-4 accent-emerald-600"
                                    />

                                    <!-- Info sucursal -->
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-800">{{ suc.nombre }}</p>
                                        <p v-if="suc.direccion" class="truncate text-xs text-slate-400">{{ suc.direccion }}</p>
                                    </div>

                                    <!-- Selector de rol -->
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

                        <!-- Footer -->
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
    Building2, Crown, Loader2, LockKeyhole, Mail, Search,
    UserPlus, UserRound, UsersRound, X,
} from "lucide-vue-next";

const auth = useAuthStore();

// ── Estado general ────────────────────────────────────────────────────────────
const sucursales        = ref([]);
const roles             = ref([]);
const usuarios          = ref([]);
const cargando          = ref(false);
const guardando         = ref(false);
const busqueda          = ref("");
const errors            = ref({});
const mensajeError      = ref("");
const meta              = ref({
    total: 0,
    super_admins_activos: 0,
    puede_gestionar_super_admins: false,
    puede_promover_primer_super_admin: false,
});
let timer;

// ── Estado modal sucursales ───────────────────────────────────────────────────
const modalSucursales          = ref(false);
const usuarioEditando          = ref(null);
const sucursalesModal          = ref([]);
const cargandoSucursalesModal  = ref(false);
const guardandoSucursales      = ref(false);

// ── Formulario de creación ────────────────────────────────────────────────────
const form = reactive({
    sucursal_id:           null,
    role_id:               null,
    name:                  "",
    email:                 "",
    password:              "",
    password_confirmation: "",
});

const puedeGuardar = computed(() =>
    !!form.sucursal_id &&
    form.name.trim().length >= 2 &&
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email) &&
    form.password.length >= 8 &&
    form.password === form.password_confirmation
);

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
        usuarios.value  = data.data ?? [];
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

function limpiarFormulario() {
    form.sucursal_id           = null;
    form.role_id               = null;
    form.name                  = "";
    form.email                 = "";
    form.password              = "";
    form.password_confirmation = "";
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

function puedeCambiarSuperAdmin(usuario) {
    // No mostrar el botón para retirarse el nivel a uno mismo
    if (usuario.es_super_admin && usuario.id === auth.user?.id) return false;

    if (meta.value.puede_gestionar_super_admins) return true;

    return meta.value.puede_promover_primer_super_admin
        && !usuario.es_super_admin
        && usuario.activo;
}

async function toggleSuperAdmin(usuario) {
    const promover = !usuario.es_super_admin;
    const accion = promover ? "promover como superadministrador" : "retirar el nivel de superadministrador";

    const ok = await confirm({
        title: promover ? "¿Promover a super administrador?" : "¿Retirar nivel de super administrador?",
        text: `${usuario.name} ${promover ? "tendrá acceso total a la empresa." : "perderá el acceso total."}`,
        confirmText: promover ? "Sí, promover" : "Sí, retirar",
        icon: promover ? "question" : "warning",
    });
    if (!ok) return;

    try {
        await http.put(`/api/users/${usuario.id}/super-admin`, {
            es_super_admin: promover,
        });

        if (usuario.id === auth.user?.id) {
            await auth.fetchUser();
        }

        toastSuccess(promover ? "Superadministrador asignado." : "Nivel de superadministrador retirado.");
        await cargarUsuarios();
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo actualizar el nivel del usuario.");
    }
}

// ── Modal sucursales ──────────────────────────────────────────────────────────
async function abrirModalSucursales(usuario) {
    usuarioEditando.value         = usuario;
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
    modalSucursales.value  = false;
    usuarioEditando.value  = null;
    sucursalesModal.value  = [];
}

async function guardarSucursales() {
    guardandoSucursales.value = true;
    try {
        const payload = sucursalesModal.value
            .filter((s) => s.asignada)
            .map((s) => ({ id: s.id, role_id: s.role_id ?? null }));

        await http.put(`/api/users/${usuarioEditando.value.id}/sucursales`, {
            sucursales: payload,
        });

        toastSuccess("Sucursales actualizadas.");
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
