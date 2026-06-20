<template>
    <div class="h-screen flex bg-slate-100 overflow-hidden">
        <!-- Sidebar -->
        <AppNav v-model:open="navOpen" />

        <!-- Right panel -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- HEADER -->
            <header
                class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-3 sm:px-6 shadow-sm shrink-0"
            >
                <!-- Left -->
                <div class="flex items-center gap-2 sm:gap-4">
                    <!-- Hamburger – mobile only -->
                    <button
                        class="md:hidden p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                        @click="navOpen = true"
                    >
                        <Menu class="h-5 w-5" />
                    </button>

                    <div class="hidden sm:flex flex-col leading-tight">
                        <span
                            class="text-xs uppercase tracking-wide text-slate-400"
                        >
                            Sistema
                        </span>
                        <span class="text-sm font-semibold text-slate-900">
                            BuhoPOS
                        </span>
                    </div>

                    <!-- Empresa badge -->
                    <div
                        class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200"
                    >
                        <Building2 class="h-4 w-4 text-slate-500" />
                        <span class="text-sm font-medium text-slate-700">
                            {{ auth.empresaNombre || "Sin empresa" }}
                        </span>
                    </div>
                </div>

                <!-- Center (Sucursal) -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <span
                        class="hidden sm:block text-xs uppercase tracking-wide text-slate-400"
                    >
                        Sucursal
                    </span>

                    <div class="relative">
                        <select
                            class="appearance-none h-9 pl-3 pr-8 rounded-xl border border-slate-200 bg-white text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-500 max-w-[140px] sm:max-w-none"
                            :value="auth.sucursalActivaId"
                            :disabled="auth.changingSucursal"
                            @change="auth.cambiarSucursal(+$event.target.value)"
                        >
                            <option
                                v-for="s in auth.sucursales"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.nombre }}
                            </option>
                        </select>

                        <ChevronDown
                            class="pointer-events-none absolute right-2 top-2.5 h-4 w-4 text-slate-400"
                        />
                    </div>
                </div>

                <!-- Right -->
                <div class="flex items-center gap-2 sm:gap-4">
                    <button
                        class="relative inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                        title="Traspasos pendientes por recibir"
                        @click="router.push({ name: 'traspasos-entrada' })"
                    >
                        <Bell class="h-4 w-4" />
                        <span
                            v-if="traspasosPendientes > 0"
                            class="absolute -right-1 -top-1 min-w-5 rounded-full bg-amber-500 px-1.5 py-0.5 text-center text-[10px] font-bold leading-none text-white ring-2 ring-white"
                        >
                            {{ traspasosPendientes > 99 ? "99+" : traspasosPendientes }}
                        </span>
                    </button>

                    <!-- User avatar + info -->
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-xl p-1 transition hover:bg-slate-50 sm:gap-3"
                        title="Abrir mi perfil"
                        @click="router.push({ name: 'perfil' })"
                    >
                        <div
                            class="h-9 w-9 rounded-xl bg-emerald-100 text-emerald-700 grid place-items-center font-semibold text-sm shrink-0"
                        >
                            {{
                                (auth.user?.name || "U")
                                    .slice(0, 1)
                                    .toUpperCase()
                            }}
                        </div>

                        <div class="hidden sm:flex flex-col leading-tight">
                            <span class="text-sm font-semibold text-slate-900">
                                {{ auth.user?.name }}
                            </span>
                            <span class="text-xs text-slate-500 truncate max-w-[120px] lg:max-w-none">
                                {{ auth.user?.email }}
                            </span>
                        </div>
                    </button>

                    <!-- Logout -->
                    <button
                        class="inline-flex items-center gap-1.5 sm:gap-2 rounded-xl px-2.5 sm:px-3 py-2 text-sm font-medium bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition focus:outline-none focus:ring-2 focus:ring-slate-900/10 disabled:opacity-60"
                        :disabled="loading"
                        @click="salir"
                    >
                        <Loader2
                            v-if="loading"
                            class="h-4 w-4 animate-spin"
                        />
                        <LogOut v-else class="h-4 w-4" />
                        <span class="hidden sm:inline">
                            {{ loading ? "Saliendo..." : "Salir" }}
                        </span>
                    </button>
                </div>
            </header>

            <!-- MAIN -->
            <main class="flex-1 overflow-y-auto">
                <div class="px-1 sm:px-2 lg:px-3 py-2 sm:py-3">
                    <router-view />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import AppNav from "../components/AppNav.vue";
import { useAuthStore } from "../stores/auth";
import http from "../lib/http";
import { confirm, toastSuccess, toastWarning, error } from "../lib/alert";
import {
    Bell,
    Building2,
    ChevronDown,
    LogOut,
    Loader2,
    Menu,
} from "lucide-vue-next";

const auth = useAuthStore();
const router = useRouter();
const loading = ref(false);
const navOpen = ref(false);
const traspasosPendientes = ref(0);
let traspasosTimer = null;

const storageKey = () =>
    `buhopos:traspasos:last:${auth.empresaId ?? "na"}:${auth.sucursalActivaId ?? "na"}`;

const revisarTraspasosPendientes = async ({ baseline = false } = {}) => {
    if (!auth.isAuth || !auth.sucursalActivaId) return;

    try {
        const { data } = await http.get("/api/traspasos/resumen-pendientes");
        traspasosPendientes.value = Number(data.por_recibir ?? 0);

        const ultimo = data.ultimo;
        if (!ultimo?.id) return;

        const key = storageKey();
        const previo = Number(localStorage.getItem(key) || 0);

        if (!previo || baseline) {
            localStorage.setItem(key, String(ultimo.id));
            return;
        }

        if (Number(ultimo.id) > previo) {
            localStorage.setItem(key, String(ultimo.id));
            toastWarning(
                `Nuevo traspaso ${ultimo.folio} por recibir${ultimo.origen ? ` de ${ultimo.origen}` : ""}`
            );
        }
    } catch {
        // El monitor no debe interrumpir la operacion normal de la pantalla.
    }
};

const detenerMonitorTraspasos = () => {
    if (!traspasosTimer) return;
    window.clearInterval(traspasosTimer);
    traspasosTimer = null;
};

const iniciarMonitorTraspasos = () => {
    detenerMonitorTraspasos();
    revisarTraspasosPendientes({ baseline: true });
    traspasosTimer = window.setInterval(() => revisarTraspasosPendientes(), 45000);
};

onMounted(() => {
    if (auth.isAuth) iniciarMonitorTraspasos();
});

onBeforeUnmount(() => detenerMonitorTraspasos());

watch(
    () => auth.sucursalActivaId,
    () => {
        traspasosPendientes.value = 0;
        if (auth.isAuth) iniciarMonitorTraspasos();
    }
);

const salir = async () => {
    if (loading.value) return;

    const ok = await confirm({
        title: "Cerrar sesión",
        text: "¿Deseas salir de la aplicación?",
        confirmText: "Sí, salir",
        cancelText: "Cancelar",
    });

    if (!ok) return;

    loading.value = true;

    try {
        await auth.logout();
        detenerMonitorTraspasos();
        traspasosPendientes.value = 0;
        toastSuccess("Sesión cerrada");
        router.push({ name: "login" });
    } catch {
        error("Error", "No se pudo cerrar la sesión");
    } finally {
        loading.value = false;
    }
};
</script>
