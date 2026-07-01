<template>
    <main class="space-y-5 p-3 sm:p-6">

        <!-- ── Header del perfil ──────────────────────────────────────────────── -->
        <section class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:gap-6">
            <div class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-2xl font-bold text-emerald-700">
                {{ iniciales }}
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold text-slate-900 truncate">{{ auth.user?.name }}</h1>
                <p class="text-sm text-slate-500 truncate">{{ auth.user?.email }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                        <Building2 class="h-3 w-3" />
                        {{ auth.empresaNombre }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                        <MapPin class="h-3 w-3" />
                        {{ auth.sucursalNombre }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="auth.esSuperAdmin ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'">
                        <ShieldCheck class="h-3 w-3" />
                        {{ auth.rolActual ?? (auth.esSuperAdmin ? 'Super Admin' : 'Sin rol') }}
                    </span>
                </div>
            </div>
            <div class="text-right text-xs text-slate-400 shrink-0">
                <p>Miembro desde</p>
                <p class="font-medium text-slate-600">{{ fmtFecha(auth.user?.created_at) }}</p>
            </div>
        </section>

        <div v-if="cargando" class="rounded-2xl border border-slate-200 bg-white py-16 text-center text-sm text-slate-500">
            <Loader2 class="mx-auto mb-2 h-5 w-5 animate-spin" />
            Cargando perfil...
        </div>

        <template v-else>
            <!-- ── Stats del mes ───────────────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard
                    label="Mis ventas"
                    :value="stats.ventas_mes"
                    :sub="`de ${stats.ventas_sucursal_mes} en sucursal`"
                    color="emerald"
                    :icon="ShoppingCart"
                    :footer="stats.mes"
                />
                <StatCard
                    label="Monto vendido"
                    :value="fmt(stats.monto_ventas_mes)"
                    :sub="`sucursal: ${fmt(stats.monto_sucursal_mes)}`"
                    color="blue"
                    :icon="DollarSign"
                    :footer="stats.mes"
                />
                <StatCard
                    label="Productos vendidos"
                    :value="stats.productos_vendidos"
                    sub="unidades en mis ventas"
                    color="violet"
                    :icon="Package"
                    :footer="stats.mes"
                />
                <StatCard
                    label="Clientes únicos"
                    :value="stats.clientes_unicos"
                    sub="con registro en mis ventas"
                    color="rose"
                    :icon="Users"
                    :footer="stats.mes"
                />
            </div>

            <!-- ── Contenido principal en dos columnas ────────────────────────── -->
            <div class="grid gap-5 xl:grid-cols-[1fr_1fr]">

                <!-- Columna izquierda: datos del usuario -->
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-600">
                            <UserRound class="h-4 w-4" />
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Datos personales</h2>
                            <p class="text-xs text-slate-500">Actualiza tu nombre, correo y contraseña.</p>
                        </div>
                    </div>
                    <form class="space-y-4 p-5" @submit.prevent="guardarUsuario">
                        <BaseInput v-model="usuario.name" label="Nombre completo" :error="errorUsuario('name')" required>
                            <template #icon><UserRound class="h-4 w-4" /></template>
                        </BaseInput>
                        <BaseInput v-model="usuario.email" label="Correo de acceso" type="email" :error="errorUsuario('email')" required>
                            <template #icon><Mail class="h-4 w-4" /></template>
                        </BaseInput>

                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 space-y-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Cambiar contraseña</p>
                            <p class="text-xs text-slate-500 -mt-2">Deja los campos vacíos para conservar la contraseña actual.</p>
                            <BaseInput v-model="usuario.current_password" label="Contraseña actual" type="password" autocomplete="current-password" :error="errorUsuario('current_password')">
                                <template #icon><LockKeyhole class="h-4 w-4" /></template>
                            </BaseInput>
                            <BaseInput v-model="usuario.password" label="Nueva contraseña" type="password" autocomplete="new-password" :error="errorUsuario('password')">
                                <template #icon><LockKeyhole class="h-4 w-4" /></template>
                            </BaseInput>
                            <BaseInput v-model="usuario.password_confirmation" label="Confirmar nueva contraseña" type="password" autocomplete="new-password">
                                <template #icon><LockKeyhole class="h-4 w-4" /></template>
                            </BaseInput>
                        </div>

                        <button
                            type="submit"
                            :disabled="guardandoUsuario"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50"
                        >
                            <Loader2 v-if="guardandoUsuario" class="h-4 w-4 animate-spin" />
                            <Save v-else class="h-4 w-4" />
                            {{ guardandoUsuario ? 'Guardando...' : 'Guardar datos personales' }}
                        </button>
                    </form>
                </section>

                <!-- Columna derecha: empresa + sucursal con tabs -->
                <div class="space-y-4">
                    <!-- Empresa -->
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-slate-50"
                            @click="tabEmpresaAbierto = !tabEmpresaAbierto"
                        >
                            <div class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-600 shrink-0">
                                <Building2 class="h-4 w-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-900">Empresa</p>
                                <p class="text-xs text-slate-500 truncate">{{ empresa.nombre || 'Información fiscal y de contacto' }}</p>
                            </div>
                            <span v-if="!puedeEditarEmpresa" class="mr-2 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-600">Solo lectura</span>
                            <ChevronDown class="h-4 w-4 text-slate-400 transition-transform shrink-0" :class="tabEmpresaAbierto ? 'rotate-180' : ''" />
                        </button>

                        <div v-if="tabEmpresaAbierto" class="border-t border-slate-100">
                            <form class="space-y-4 p-5" @submit.prevent="guardarEmpresa">
                                <BaseInput v-model="empresa.nombre" label="Nombre de la empresa" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('nombre')" required />
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <BaseInput v-model="empresa.propietario" label="Propietario" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('propietario')" />
                                    <BaseInput v-model="empresa.rfc" label="RFC" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('rfc')" />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <BaseInput v-model="empresa.correo" label="Correo" type="email" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('correo')" />
                                    <BaseInput v-model="empresa.telefono" label="Teléfono" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('telefono')" />
                                </div>
                                <BaseInput v-model="empresa.direccion" label="Dirección" :disabled="!puedeEditarEmpresa" :error="errorEmpresa('direccion')" />

                                <!-- Logo -->
                                <div>
                                    <p class="mb-2 text-xs font-semibold text-slate-700">Logo de la empresa</p>
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                            <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="h-full w-full object-contain p-1" />
                                            <Building2 v-else class="h-8 w-8 text-slate-300" />
                                        </div>
                                        <div v-if="puedeEditarEmpresa" class="flex flex-col gap-2">
                                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50">
                                                <Loader2 v-if="subiendoLogo" class="h-3.5 w-3.5 animate-spin" />
                                                <ImagePlus v-else class="h-3.5 w-3.5" />
                                                {{ logoUrl ? 'Cambiar logo' : 'Subir logo' }}
                                                <input
                                                    ref="logoInput"
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                                    class="hidden"
                                                    :disabled="subiendoLogo"
                                                    @change="subirLogo"
                                                />
                                            </label>
                                            <button
                                                v-if="logoUrl"
                                                type="button"
                                                :disabled="eliminandoLogo"
                                                @click="eliminarLogo"
                                                class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 disabled:opacity-50"
                                            >
                                                <Loader2 v-if="eliminandoLogo" class="h-3.5 w-3.5 animate-spin" />
                                                <Trash2 v-else class="h-3.5 w-3.5" />
                                                Eliminar logo
                                            </button>
                                        </div>
                                        <p v-if="puedeEditarEmpresa" class="text-[11px] text-slate-400">PNG, JPG, WebP o SVG · máx. 2 MB.<br>Se usa en PDFs y tickets.</p>
                                    </div>
                                </div>

                                <button v-if="puedeEditarEmpresa"
                                    type="submit"
                                    :disabled="guardandoEmpresa"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50"
                                >
                                    <Loader2 v-if="guardandoEmpresa" class="h-4 w-4 animate-spin" />
                                    <Save v-else class="h-4 w-4" />
                                    {{ guardandoEmpresa ? 'Guardando...' : 'Guardar empresa' }}
                                </button>
                                <div v-else class="flex gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                    <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>Tu rol no tiene permiso para editar la empresa.</span>
                                </div>
                            </form>
                        </div>
                    </section>

                    <!-- Sucursal -->
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-slate-50"
                            @click="tabSucursalAbierto = !tabSucursalAbierto"
                        >
                            <div class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-600 shrink-0">
                                <MapPin class="h-4 w-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-900">Sucursal activa</p>
                                <p class="text-xs text-slate-500 truncate">{{ sucursal.nombre || 'Datos de la sucursal seleccionada' }}</p>
                            </div>
                            <span v-if="!puedeEditarSucursal" class="mr-2 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-600">Solo lectura</span>
                            <ChevronDown class="h-4 w-4 text-slate-400 transition-transform shrink-0" :class="tabSucursalAbierto ? 'rotate-180' : ''" />
                        </button>

                        <div v-if="tabSucursalAbierto" class="border-t border-slate-100">
                            <form class="space-y-4 p-5" @submit.prevent="guardarSucursal">
                                <BaseInput v-model="sucursal.nombre" label="Nombre de la sucursal" :disabled="!puedeEditarSucursal" :error="errorSucursal('nombre')" required />
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <BaseInput v-model="sucursal.correo" label="Correo" type="email" :disabled="!puedeEditarSucursal" :error="errorSucursal('correo')" />
                                    <BaseInput v-model="sucursal.telefono" label="Teléfono" :disabled="!puedeEditarSucursal" :error="errorSucursal('telefono')" />
                                </div>
                                <BaseInput v-model="sucursal.direccion" label="Dirección" :disabled="!puedeEditarSucursal" :error="errorSucursal('direccion')" />

                                <button v-if="puedeEditarSucursal"
                                    type="submit"
                                    :disabled="guardandoSucursal"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50"
                                >
                                    <Loader2 v-if="guardandoSucursal" class="h-4 w-4 animate-spin" />
                                    <Save v-else class="h-4 w-4" />
                                    {{ guardandoSucursal ? 'Guardando...' : 'Guardar sucursal' }}
                                </button>
                                <div v-else class="flex gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                    <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>Tu rol no tiene permiso para editar la sucursal.</span>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </main>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import http from "@/lib/http";
import { toastError, toastSuccess } from "@/lib/alert";
import { useAuthStore } from "@/stores/auth";
import {
    Building2, ChevronDown, DollarSign, ImagePlus, Loader2, LockKeyhole,
    Mail, MapPin, Package, Save, ShieldAlert, ShieldCheck,
    ShoppingCart, Trash2, UserRound, Users,
} from "lucide-vue-next";

const auth = useAuthStore();

// ── Estado ─────────────────────────────────────────────────────────────────────
const cargando         = ref(true);
const guardandoUsuario = ref(false);
const guardandoEmpresa = ref(false);
const guardandoSucursal= ref(false);
const puedeEditarEmpresa  = ref(false);
const puedeEditarSucursal = ref(false);
const erroresUsuario   = ref({});
const erroresEmpresa   = ref({});
const erroresSucursal  = ref({});
const tabEmpresaAbierto  = ref(false);
const tabSucursalAbierto = ref(false);
const stats = ref({
    ventas_mes: 0, monto_ventas_mes: 0,
    productos_vendidos: 0, clientes_unicos: 0,
    ventas_sucursal_mes: 0, monto_sucursal_mes: 0,
    mes: '',
});

const usuario = reactive({ name: "", email: "", current_password: "", password: "", password_confirmation: "" });
const empresa = reactive({ nombre: "", propietario: "", rfc: "", correo: "", telefono: "", direccion: "" });
const sucursal= reactive({ nombre: "", correo: "", telefono: "", direccion: "" });
const logoUrl        = ref(null);
const subiendoLogo   = ref(false);
const eliminandoLogo = ref(false);
const logoInput      = ref(null);

// ── Computed ───────────────────────────────────────────────────────────────────
const iniciales = computed(() => {
    const name = auth.user?.name ?? "";
    return name.split(" ").slice(0, 2).map(p => p[0]?.toUpperCase() ?? "").join("");
});

// ── Helpers ────────────────────────────────────────────────────────────────────
const errorUsuario  = (f) => erroresUsuario.value?.[f]?.[0] ?? "";
const errorEmpresa  = (f) => erroresEmpresa.value?.[f]?.[0] ?? "";
const errorSucursal = (f) => erroresSucursal.value?.[f]?.[0] ?? "";

function fmt(val) {
    return Number(val ?? 0).toLocaleString("es-MX", { style: "currency", currency: "MXN", minimumFractionDigits: 0 });
}

function fmtFecha(val) {
    if (!val) return "—";
    return new Date(val).toLocaleDateString("es-MX", { day: "2-digit", month: "short", year: "numeric" });
}

function normalizar(origen, destino) {
    return Object.fromEntries(Object.keys(destino).map((k) => [k, origen?.[k] ?? ""]));
}

// ── Carga ──────────────────────────────────────────────────────────────────────
onMounted(cargarPerfil);

async function cargarPerfil() {
    cargando.value = true;
    try {
        const { data } = await http.get("/api/perfil");
        aplicarPerfil(data);
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo cargar el perfil.");
    } finally {
        cargando.value = false;
    }
}

function aplicarPerfil(data) {
    Object.assign(usuario, { name: data.name ?? "", email: data.email ?? "", current_password: "", password: "", password_confirmation: "" });
    Object.assign(empresa,  normalizar(data.empresa,  empresa));
    Object.assign(sucursal, normalizar(data.sucursal, sucursal));
    puedeEditarEmpresa.value  = !!data.puede_editar_empresa;
    puedeEditarSucursal.value = !!data.puede_editar_sucursal;
    logoUrl.value = data.empresa?.logo_url ?? null;
    if (data.stats) Object.assign(stats.value, data.stats);
    auth._setUser(data);
}

async function subirLogo(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    subiendoLogo.value = true;
    try {
        const fd = new FormData();
        fd.append("logo", file);
        const { data } = await http.post("/api/perfil/empresa/logo", fd, {
            headers: { "Content-Type": "multipart/form-data" },
        });
        logoUrl.value = data.logo_url;
        if (auth.user?.empresa) auth.user.empresa.logo_url = data.logo_url;
        toastSuccess("Logo actualizado.");
    } catch (err) {
        toastError(err?.response?.data?.message || "No se pudo subir el logo.");
    } finally {
        subiendoLogo.value = false;
        if (logoInput.value) logoInput.value.value = "";
    }
}

async function eliminarLogo() {
    eliminandoLogo.value = true;
    try {
        await http.delete("/api/perfil/empresa/logo");
        logoUrl.value = null;
        if (auth.user?.empresa) auth.user.empresa.logo_url = null;
        toastSuccess("Logo eliminado.");
    } catch {
        toastError("No se pudo eliminar el logo.");
    } finally {
        eliminandoLogo.value = false;
    }
}

// ── Guardar usuario ────────────────────────────────────────────────────────────
async function guardarUsuario() {
    guardandoUsuario.value = true;
    erroresUsuario.value = {};
    try {
        const { data } = await http.put("/api/perfil/usuario", usuario);
        aplicarPerfil(data);
        toastSuccess("Datos personales actualizados.");
    } catch (e) {
        erroresUsuario.value = e?.response?.data?.errors ?? {};
        toastError(e?.response?.data?.message || "No se pudo actualizar.");
    } finally {
        guardandoUsuario.value = false;
    }
}

// ── Guardar empresa ────────────────────────────────────────────────────────────
async function guardarEmpresa() {
    guardandoEmpresa.value = true;
    erroresEmpresa.value = {};
    try {
        const { data } = await http.put("/api/perfil/empresa", empresa);
        aplicarPerfil(data);
        toastSuccess("Empresa actualizada.");
    } catch (e) {
        erroresEmpresa.value = e?.response?.data?.errors ?? {};
        toastError(e?.response?.data?.message || "No se pudo actualizar la empresa.");
    } finally {
        guardandoEmpresa.value = false;
    }
}

// ── Guardar sucursal ───────────────────────────────────────────────────────────
async function guardarSucursal() {
    guardandoSucursal.value = true;
    erroresSucursal.value = {};
    try {
        const { data } = await http.put("/api/perfil/sucursal", sucursal);
        aplicarPerfil(data);
        await auth.fetchSucursales();
        toastSuccess("Sucursal actualizada.");
    } catch (e) {
        erroresSucursal.value = e?.response?.data?.errors ?? {};
        toastError(e?.response?.data?.message || "No se pudo actualizar la sucursal.");
    } finally {
        guardandoSucursal.value = false;
    }
}

// ── StatCard ───────────────────────────────────────────────────────────────────
const colorMap = {
    emerald: { bg: "bg-emerald-50", text: "text-emerald-600", ring: "ring-emerald-100" },
    blue:    { bg: "bg-blue-50",    text: "text-blue-600",    ring: "ring-blue-100"    },
    violet:  { bg: "bg-violet-50",  text: "text-violet-600",  ring: "ring-violet-100"  },
    rose:    { bg: "bg-rose-50",    text: "text-rose-600",    ring: "ring-rose-100"    },
};

const StatCard = defineComponent({
    props: { label: String, value: [String, Number], sub: String, footer: String, color: String, icon: [Object, Function] },
    setup(props) {
        return () => {
            const c = colorMap[props.color] ?? colorMap.emerald;
            return h("div", { class: "rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" }, [
                h("div", { class: "flex items-start justify-between gap-2" }, [
                    h("div", {}, [
                        h("p", { class: "text-xs font-medium text-slate-500" }, props.label),
                        h("p", { class: "mt-1 text-2xl font-bold text-slate-900 leading-none" }, props.value ?? "—"),
                        props.sub ? h("p", { class: "mt-1 text-[11px] text-slate-400 leading-tight" }, props.sub) : null,
                    ]),
                    h("div", { class: `grid h-9 w-9 shrink-0 place-items-center rounded-xl ring-4 ${c.bg} ${c.text} ${c.ring}` }, [
                        h(props.icon, { class: "h-4 w-4" }),
                    ]),
                ]),
                props.footer ? h("p", { class: "mt-3 border-t border-slate-100 pt-2 text-[11px] capitalize text-slate-400" }, props.footer) : null,
            ]);
        };
    },
});
</script>
