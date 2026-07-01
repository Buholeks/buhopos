<template>
    <Transition name="nav-fade">
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm md:hidden"
            @click="closeNav"
        />
    </Transition>

    <aside
        :class="[
            'fixed inset-y-0 left-0 z-50 flex w-72 shrink-0 flex-col bg-slate-950 text-slate-200',
            'border-r border-slate-800 transition-transform duration-300 ease-in-out',
            'md:relative md:w-64 md:translate-x-0',
            open ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        ]"
    >
        <!-- Header -->
        <div class="shrink-0 border-b border-slate-800 px-4 py-4">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex items-center gap-3">
                    <img
                        v-if="auth.empresaLogoUrl"
                        :src="auth.empresaLogoUrl"
                        alt="Logo"
                        class="h-9 w-auto max-w-[120px] shrink-0 rounded object-contain"
                    />
                    <div v-else class="min-w-0">
                        <h1
                            class="truncate text-lg font-bold tracking-tight text-white"
                        >
                            BuhoPOS
                        </h1>
                        <p class="mt-0.5 truncate text-xs text-slate-500">
                            Punto de venta
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white md:hidden"
                    @click="closeNav"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Nav -->
        <nav class="sidebar-scroll flex-1 overflow-y-auto px-3 py-4">
            <section
                v-for="section in navSections"
                :key="section.title"
                class="mb-5 last:mb-0"
            >
                <p
                    class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                >
                    {{ section.title }}
                </p>

                <div class="mt-2 space-y-1">
                    <RouterLink
                        v-for="item in section.items"
                        :key="item.to"
                        :to="item.to"
                        :class="[
                            'nav-item',
                            item.emphasis ? 'nav-item--primary' : '',
                        ]"
                        active-class="nav-item--active"
                        exact-active-class="nav-item--active"
                        @click="closeNav"
                    >
                        <component :is="item.icon" class="nav-ic" />

                        <span class="min-w-0 flex-1 truncate">
                            {{ item.label }}
                        </span>

                        <span v-if="item.badge" class="nav-badge">
                            {{ item.badge }}
                        </span>
                    </RouterLink>
                </div>
            </section>
        </nav>

        <!-- Footer -->
<p class="text-center text-xs text-slate-500">
    © {{ currentYear }} BuhoSoft
</p>
    </aside>
</template>

<script setup>
import { computed } from "vue";
import {
    ChartColumn,
    Landmark,
    LayoutDashboard,
    LibraryBig,
    PackageCheck,
    ReceiptText,
    ScanSearch,
    Settings2,
    ShoppingCart,
    Tags,
    UserRoundCog,
    Workflow,
    X,
} from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:open"]);

const auth = useAuthStore();
const currentYear = new Date().getFullYear();
const allSections = [
    {
        items: [
            {
                label: "Inicio",
                to: "/",
                icon: LayoutDashboard,
                permiso: null,
            },
        ],
    },
    {
        title: "Operación",
        items: [
            {
                label: "Ventas",
                to: "/ventas",
                icon: ShoppingCart,
                emphasis: true,
                permiso: "ventas.crear",
            },
            {
                label: "Compras",
                to: "/compras",
                icon: ReceiptText,
                emphasis: true,
                permiso: "compras.crear",
            },
            {
                label: "Corte de caja",
                to: "/caja",
                icon: Landmark,
                emphasis: true,
                permiso: "caja.abrir",
            },

             {
                label: "Lista de precios",
                to: "/catalogo-precios",
                icon: ScanSearch,
                permiso: "productos.precios",
            },
            {
                label: "Etiquetas de precio",
                to: "/etiquetas-precio",
                icon: Tags,
                permiso: "etiquetas.imprimir",
            },
            {
                label: "Exhibición",
                to: "/exhibicion",
                icon: PackageCheck,
                permiso: "inventario.ver",
            },
        ],
    },
    {
        title: "Gestión",
        items: [
            {
                label: "Procesos",
                to: "/procesos",
                icon: Workflow,
                permiso: null,
            },
            {
                label: "Catálogos",
                to: "/catalogos",
                icon: LibraryBig,
                permiso: null,
            },
            {
                label: "Consultas y reportes",
                to: "/consultasreportes",
                icon: ChartColumn,
                permiso: "reportes.ver",
            },
        ],
    },
    {
        title: "Sistema",
        items: [
            {
                label: "Configuración",
                to: "/configuracion",
                icon: Settings2,
                permiso: null,
            },
        ],
    },
];

const navSections = computed(() =>
    allSections
        .map((section) => ({
            ...section,
            items: section.items.filter(
                (item) => item.permiso === null || auth.can(item.permiso),
            ),
        }))
        .filter((section) => section.items.length > 0),
);

function closeNav() {
    emit("update:open", false);
}
</script>

<style scoped>
.sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(51, 65, 85, 0.9) transparent;
}

.sidebar-scroll::-webkit-scrollbar {
    width: 8px;
}

.sidebar-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-scroll::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(51, 65, 85, 0.8);
}

.nav-item {
    position: relative;
    display: flex;
    min-height: 2.55rem;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.75rem;
    padding: 0.6rem 0.75rem;
    color: rgb(148 163 184);
    transition:
        background-color 150ms ease,
        color 150ms ease,
        box-shadow 150ms ease;
}

.nav-item::before {
    content: "";
    position: absolute;
    bottom: 0.65rem;
    left: 0;
    top: 0.65rem;
    width: 3px;
    border-radius: 999px;
    background: transparent;
}

.nav-ic {
    height: 1.05rem;
    width: 1.05rem;
    flex-shrink: 0;
    color: rgb(148 163 184);
    transition: color 150ms ease;
}

.nav-badge {
    flex-shrink: 0;
    border-radius: 999px;
    background: rgb(30 41 59);
    padding: 0.125rem 0.45rem;
    font-size: 0.62rem;
    font-weight: 700;
    color: rgb(203 213 225);
}

.nav-item:hover {
    background: rgb(30 41 59 / 0.75);
    color: white;
}

.nav-item:hover .nav-ic {
    color: rgb(226 232 240);
}

.nav-item--active {
    background: rgb(30 41 59);
    color: white;
    box-shadow: inset 0 0 0 1px rgb(51 65 85);
}

.nav-item--active::before {
    background: rgb(148 163 184);
}

.nav-item--active .nav-ic {
    color: white;
}

.nav-item--primary {
    color: rgb(203 213 225);
}

.nav-item--primary.nav-item--active::before {
    background: rgb(16 185 129);
}

.nav-fade-enter-active,
.nav-fade-leave-active {
    transition: opacity 220ms ease;
}

.nav-fade-enter-from,
.nav-fade-leave-to {
    opacity: 0;
}
</style>
