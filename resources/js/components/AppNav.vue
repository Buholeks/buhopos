<template>
    <Transition name="nav-fade">
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm md:hidden"
            @click="closeNav"
        />
    </Transition>

    <aside
        :class="[
            'flex w-64 shrink-0 flex-col border-r border-slate-800 bg-slate-950 text-slate-200',
            'fixed inset-y-0 left-0 z-50 transition-transform duration-300 ease-in-out',
            'md:relative md:translate-x-0',
            open ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        ]"
    >
        <div
            class="flex h-16 shrink-0 items-center justify-between border-b border-slate-800 px-4"
        >
            <div class="flex min-w-0 items-center gap-3">
                <div class="relative shrink-0">
                    <div
                        class="absolute inset-0 rounded-2xl bg-emerald-600/20 blur-xl"
                    />

                    <div
                        class="brand-mark relative grid h-10 w-10 place-items-center rounded-2xl bg-emerald-600/10 ring-1 ring-emerald-500/30"
                        title="BuhoSoft"
                    >
                        <svg
                            viewBox="0 0 64 64"
                            class="h-6 w-6 text-emerald-400"
                            fill="none"
                            stroke="currentColor"
                        >
                            <path
                                d="M16 28c0-9 7-16 16-16s16 7 16 16v16c0 6-5 10-16 10S16 50 16 44V28z"
                                stroke-width="2.6"
                            />
                            <path
                                d="M20 20l-6-8M44 20l6-8"
                                stroke-width="2.6"
                                stroke-linecap="round"
                            />
                            <circle
                                cx="24"
                                cy="34"
                                r="6.5"
                                stroke-width="2.6"
                            />
                            <circle
                                cx="40"
                                cy="34"
                                r="6.5"
                                stroke-width="2.6"
                            />
                            <path
                                d="M32 36l-3 4h6l-3-4z"
                                stroke-width="2.6"
                                stroke-linejoin="round"
                            />
                            <path
                                class="brand-lid"
                                d="M18 34c2-2 5-3 6-3s4 1 6 3"
                                stroke-width="3"
                                stroke-linecap="round"
                            />
                            <path
                                class="brand-lid"
                                d="M34 34c2-2 5-3 6-3s4 1 6 3"
                                stroke-width="3"
                                stroke-linecap="round"
                            />
                        </svg>
                    </div>
                </div>

                <div class="min-w-0 leading-tight">
                    <div class="truncate text-sm font-semibold text-white">
                        BuhoPOS
                    </div>
                    <div class="truncate text-xs text-slate-400">
                        Inventario, ventas y caja
                    </div>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <span
                    class="rounded-full bg-emerald-600/10 px-2 py-1 text-[11px] text-emerald-300 ring-1 ring-emerald-500/30"
                >
                    v1
                </span>

                <button
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-800 hover:text-white md:hidden"
                    @click="closeNav"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
            <section v-for="section in navSections" :key="section.title">
                <p
                    class="px-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
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
                            item.emphasis ? 'nav-item--quick' : '',
                        ]"
                        :active-class="item.exact ? '' : 'nav-item--active'"
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

        <div class="shrink-0 border-t border-slate-800 px-4 py-3">
            <p class="text-[11px] text-slate-500">
                © {{ new Date().getFullYear() }} BuhoSoft
            </p>
        </div>
    </aside>
</template>

<script setup>
import {
    BadgeDollarSign,
    Boxes,
    ChartColumn,
    Landmark,
    LayoutDashboard,
    LibraryBig,
    ClipboardList,
    PackageCheck,
    Repeat2,
    ScanSearch,
    ReceiptText,
    ShoppingCart,
    Workflow,
    X,
} from "lucide-vue-next";

defineProps({ open: { type: Boolean, default: false } });
const emit = defineEmits(["update:open"]);

const navSections = [
    {
        title: "Principal",
        items: [
            {
                label: "Inicio",
                to: "/",
                icon: LayoutDashboard,
                exact: true,
            },
        ],
    },
    {
        title: "Trabajo diario",
        items: [
            {
                label: "Ventas",
                to: "/ventas",
                icon: ShoppingCart,
                emphasis: true,
            },
            {
                label: "Compras",
                to: "/compras",
                icon: ReceiptText,
                emphasis: true,
            },
            {
                label: "Corte de caja",
                to: "/caja",
                icon: Landmark,
                emphasis: true,
            },
            {
                label: "Nuevo pedido",
                to: "/pedidos/nuevo",
                icon: ClipboardList,
                emphasis: true,
            },
            {
                label: "Nuevo apartado",
                to: "/apartados/nuevo",
                icon: ClipboardList,
                emphasis: true,
            },
        ],
    },
    {
        title: "Inventario",
        items: [
            {
                label: "Productos",
                to: "/productos",
                icon: Boxes,
            },
            {
                label: "Lista de precios",
                to: "/catalogo-precios",
                icon: ScanSearch,
            },
            {
                label: "Exhibición",
                to: "/exhibicion",
                icon: PackageCheck,
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
            },
            {
                label: "Catálogos",
                to: "/catalogos",
                icon: LibraryBig,
            },
            {
                label: "Consultas y reportes",
                to: "/consultasreportes",
                icon: ChartColumn,
            },
            {
                label: "Consulta pedidos",
                to: "/pedidos/consulta",
                icon: ClipboardList,
            },
            {
                label: "Consulta apartados",
                to: "/apartados/consulta",
                icon: ClipboardList,
            },
        ],
    },
];

function closeNav() {
    emit("update:open", false);
}
</script>

<style scoped>
.nav-item {
    position: relative;
    display: flex;
    min-height: 2.5rem;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.8rem;
    padding: 0.625rem 0.75rem 0.625rem 0.95rem;
    color: rgba(148, 163, 184, 0.95);
    transition:
        background-color 160ms ease,
        color 160ms ease,
        transform 160ms ease,
        box-shadow 160ms ease;
}

.nav-item::before {
    content: "";
    position: absolute;
    bottom: 0.55rem;
    left: 0.35rem;
    top: 0.55rem;
    width: 3px;
    border-radius: 999px;
    background: rgba(16, 185, 129, 0);
    transform: scaleY(0.6);
    transition:
        background-color 160ms ease,
        transform 160ms ease,
        box-shadow 160ms ease;
}

.nav-ic {
    height: 1.05rem;
    width: 1.05rem;
    flex-shrink: 0;
    color: rgba(148, 163, 184, 0.95);
    transition: color 160ms ease;
}

.nav-badge {
    flex-shrink: 0;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.7);
    padding: 0.125rem 0.4rem;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: rgba(110, 231, 183, 0.95);
    box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.2);
}

.nav-item:hover {
    background: rgba(16, 185, 129, 0.1);
    color: rgba(226, 232, 240, 0.98);
    transform: translateX(1px);
}

.nav-item:hover .nav-ic {
    color: rgba(52, 211, 153, 0.98);
}

.nav-item:hover::before {
    background: rgba(16, 185, 129, 0.35);
    transform: scaleY(0.9);
}

.nav-item--active {
    background: rgba(16, 185, 129, 0.18);
    color: rgba(255, 255, 255, 0.98);
    box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.35);
}

.nav-item--active .nav-ic {
    color: rgba(52, 211, 153, 0.98);
}

.nav-item--active::before {
    background: rgba(16, 185, 129, 1);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
    transform: scaleY(1);
}

.nav-item--quick {
    background: rgba(16, 185, 129, 0.07);
    box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.16);
}

.brand-mark {
    animation: brandFloat 4.2s ease-in-out infinite;
}

@keyframes brandFloat {
    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-5px);
    }
}

.brand-lid {
    opacity: 0;
    animation: brandBlink 6.2s infinite;
}

.brand-lid:nth-of-type(1) {
    animation-delay: 0s;
}

.brand-lid:nth-of-type(2) {
    animation-delay: 0.08s;
}

@keyframes brandBlink {
    0%,
    93%,
    100% {
        opacity: 0;
    }

    95% {
        opacity: 1;
    }

    96.5% {
        opacity: 0;
    }
}

.nav-fade-enter-active,
.nav-fade-leave-active {
    transition: opacity 250ms ease;
}

.nav-fade-enter-from,
.nav-fade-leave-to {
    opacity: 0;
}
</style>
