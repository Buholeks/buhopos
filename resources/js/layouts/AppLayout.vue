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
                    <!-- User avatar + info -->
                    <div class="flex items-center gap-2 sm:gap-3">
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
                    </div>

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
                <div class="px-3 sm:px-4 lg:px-6 py-4 sm:py-6">
                    <router-view />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import AppNav from "../components/AppNav.vue";
import { useAuthStore } from "../stores/auth";
import { confirm, toastSuccess, error } from "../lib/alert";
import {
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
        toastSuccess("Sesión cerrada");
        router.push({ name: "login" });
    } catch {
        error("Error", "No se pudo cerrar la sesión");
    } finally {
        loading.value = false;
    }
};
</script>
