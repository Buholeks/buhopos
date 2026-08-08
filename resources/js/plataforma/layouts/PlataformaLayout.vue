<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <!-- Fondo móvil -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="menuAbierto"
                class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"
                @click="menuAbierto = false"
            />
        </Transition>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-800 bg-slate-950 text-white transition-transform duration-200 lg:translate-x-0"
            :class="menuAbierto ? 'translate-x-0' : '-translate-x-full'"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center justify-between border-b border-slate-800 px-4">
                <RouterLink
                    :to="{ name: 'plataforma-dashboard' }"
                    class="flex min-w-0 items-center gap-2.5"
                    @click="menuAbierto = false"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500 text-slate-950">
                        <ShieldCheck class="h-4 w-4" />
                    </div>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-base font-semibold">
                                BuhoPOS
                            </span>

                            <span class="rounded bg-emerald-500/15 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-emerald-300">
                                SaaS
                            </span>
                        </div>

                        <p class="truncate text-[11px] text-slate-500">
                            Administración
                        </p>
                    </div>
                </RouterLink>

                <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden"
                    @click="menuAbierto = false"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Navegación -->
            <div class="flex-1 overflow-y-auto px-3 py-4">
                <p class="mb-2 px-2 text-[10px] font-semibold uppercase tracking-wider text-slate-600">
                    Plataforma
                </p>

                <nav class="space-y-1">
                    <RouterLink
                        v-for="item in items"
                        :key="item.to.name"
                        :to="item.to"
                        class="group flex h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium text-slate-400 transition hover:bg-white/[0.06] hover:text-white"
                        active-class="bg-emerald-500/10 !text-emerald-300"
                        @click="menuAbierto = false"
                    >
                        <component
                            :is="item.icon"
                            class="h-[17px] w-[17px] shrink-0"
                        />

                        <span class="min-w-0 flex-1 truncate">
                            {{ item.label }}
                        </span>

                        <ChevronRight
                            class="h-3.5 w-3.5 text-slate-600 opacity-0 transition group-hover:opacity-100 group-[.router-link-active]:text-emerald-400 group-[.router-link-active]:opacity-100"
                        />
                    </RouterLink>
                </nav>
            </div>

            <!-- Usuario -->
            <div class="border-t border-slate-800 p-3">
                <div class="flex items-center gap-2.5 rounded-lg px-2 py-2">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-800 text-xs font-semibold uppercase text-emerald-300">
                        {{ inicialesAdmin }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-slate-200">
                            {{ auth.admin?.nombre || "Administrador" }}
                        </p>

                        <p class="truncate text-[10px] text-slate-500">
                            {{ auth.admin?.email || "Sin correo" }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-500 transition hover:bg-rose-500/10 hover:text-rose-300 disabled:opacity-50"
                        :disabled="cerrandoSesion"
                        title="Cerrar sesión"
                        @click="salir"
                    >
                        <Loader2
                            v-if="cerrandoSesion"
                            class="h-4 w-4 animate-spin"
                        />
                        <LogOut v-else class="h-4 w-4" />
                    </button>
                </div>

                <p class="mt-1 text-center text-[10px] text-slate-700">
                    © {{ anioActual }} BuhoPOS
                </p>
            </div>
        </aside>

        <!-- Contenido -->
        <div class="min-h-screen lg:pl-64">
            <!-- Header -->
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
                <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-5 lg:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 lg:hidden"
                            @click="menuAbierto = true"
                        >
                            <Menu class="h-4 w-4" />
                        </button>

                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-600">
                                Administración SaaS
                            </p>

                            <h1 class="truncate text-base font-semibold text-slate-900">
                                {{ tituloPagina }}
                            </h1>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <div class="hidden text-right sm:block">
                            <p class="max-w-40 truncate text-xs font-semibold text-slate-700">
                                {{ auth.admin?.nombre || "Administrador" }}
                            </p>

                            <p class="text-[10px] text-slate-400">
                                Administrador
                            </p>
                        </div>

                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-xs font-semibold uppercase text-emerald-300">
                            {{ inicialesAdmin }}
                        </div>
                    </div>
                </div>
            </header>

            <!-- Vista -->
            <main class="p-4 sm:p-5 lg:p-6">
                <div class="mx-auto w-full max-w-[1600px]">
                    <RouterView />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
    Building2,
    Landmark,
    ChevronRight,
    Gift,
    LayoutDashboard,
    Loader2,
    LogOut,
    Menu,
    ShieldCheck,
    WalletCards,
    X,
} from "lucide-vue-next";

import { usePlataformaAuthStore } from "../stores/auth";

const auth = usePlataformaAuthStore();
const route = useRoute();
const router = useRouter();

const menuAbierto = ref(false);
const cerrandoSesion = ref(false);

const items = [
    {
        label: "Resumen",
        to: { name: "plataforma-dashboard" },
        icon: LayoutDashboard,
    },
    {
        label: "Empresas",
        to: { name: "plataforma-empresas" },
        icon: Building2,
    },
    {
        label: "Planes",
        to: { name: "plataforma-planes" },
        icon: WalletCards,
    },
    {
        label: "Cobros",
        to: { name: "plataforma-cobros" },
        icon: Landmark,
    },
    {
        label: "Promociones",
        to: { name: "plataforma-codigos-promocionales" },
        icon: Gift,
    },
];

const tituloPagina = computed(() => {
    return route.meta?.title || "Plataforma";
});

const inicialesAdmin = computed(() => {
    const nombre = auth.admin?.nombre?.trim();

    if (!nombre) {
        return "AD";
    }

    return nombre
        .split(/\s+/)
        .slice(0, 2)
        .map((parte) => parte.charAt(0))
        .join("")
        .toUpperCase();
});

const anioActual = new Date().getFullYear();

async function salir() {
    if (cerrandoSesion.value) {
        return;
    }

    cerrandoSesion.value = true;

    try {
        await auth.logout();
        await router.replace({ name: "plataforma-login" });
    } finally {
        cerrandoSesion.value = false;
    }
}
</script>
