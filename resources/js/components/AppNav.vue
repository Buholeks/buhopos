<template>
    <aside
        class="hidden md:flex md:flex-col w-64 shrink-0 bg-slate-900 text-slate-200 border-r border-slate-800"
    >
        <!-- Brand -->
        <div class="h-16 px-4 flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-3">
                <!-- Owl badge -->
                <div class="relative">
                    <div class="absolute inset-0 rounded-2xl bg-emerald-600/20 blur-xl"></div>

                    <div
                        class="relative h-10 w-10 rounded-2xl bg-emerald-600/10 ring-1 ring-emerald-500/30 grid place-items-center owl-float"
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
                            <circle cx="24" cy="34" r="6.5" stroke-width="2.6" />
                            <circle cx="40" cy="34" r="6.5" stroke-width="2.6" />
                            <path
                                d="M32 36l-3 4h6l-3-4z"
                                stroke-width="2.6"
                                stroke-linejoin="round"
                            />
                            <path
                                class="owl-lid"
                                d="M18 34c2-2 5-3 6-3s4 1 6 3"
                                stroke-width="3"
                                stroke-linecap="round"
                            />
                            <path
                                class="owl-lid"
                                d="M34 34c2-2 5-3 6-3s4 1 6 3"
                                stroke-width="3"
                                stroke-linecap="round"
                            />
                        </svg>
                    </div>
                </div>

                <div class="leading-tight">
                    <div class="text-sm font-semibold text-white">BuhoPOS</div>
                    <div class="text-xs text-slate-400">BuhoSoft · Inventario y ventas</div>
                </div>
            </div>

            <span class="text-[11px] px-2 py-1 rounded-full bg-emerald-600/10 ring-1 ring-emerald-500/30 text-emerald-300">
                v1
            </span>
        </div>

        <!-- Nav -->
        <nav class="px-3 py-4 space-y-6 overflow-y-auto">
            <!-- Principal -->
            <div>
                <p class="px-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                    Principal
                </p>

                <div class="mt-2 space-y-1">
                    <RouterLink to="/" class="nav-item" exact-active-class="nav-item--active">
                        <LayoutDashboard class="nav-ic" />
                        <span>Inicio</span>
                    </RouterLink>
                </div>
            </div>

            <!-- Acción rápida -->
            <div>
                <p class="px-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                    Acción rápida
                </p>

                <div class="mt-2 space-y-1">
                    <RouterLink to="/ventas" class="nav-item nav-item--quick" active-class="nav-item--active">
                        <Workflow class="nav-ic" />
                        <span>Ventas</span>
                    </RouterLink>
                </div>
            </div>

            <!-- Operación -->
            <div>
                <p class="px-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                    Operación
                </p>

                <div class="mt-2 space-y-1">
                    <RouterLink to="/procesos" class="nav-item" active-class="nav-item--active">
                        <Workflow class="nav-ic" />
                        <span>Procesos</span>
                    </RouterLink>

                    <RouterLink to="/catalogos" class="nav-item" active-class="nav-item--active">
                        <LibraryBig class="nav-ic" />
                        <span>Catálogos</span>
                    </RouterLink>
                    
                    <RouterLink to="/consultasreportes" class="nav-item" active-class="nav-item--active">
                        <BarChart3 class="nav-ic" />
                        <span>Consultas y Reportes</span>
                    </RouterLink>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-auto pt-4 border-t border-slate-800">
                <p class="px-1 text-[11px] text-slate-500">
                    © {{ new Date().getFullYear() }} BuhoSoft
                </p>
            </div>
        </nav>
    </aside>
</template>

<script setup>
import { LayoutDashboard, Workflow, LibraryBig } from "lucide-vue-next";
</script>

<style scoped>
/* --- NAV ITEM BASE --- */
.nav-item {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem 0.625rem 0.95rem; /* un poquito más a la izq por la barra */
    border-radius: 0.9rem;
    color: rgba(148, 163, 184, 0.95); /* slate-400 */
    transition:
        background-color 160ms ease,
        color 160ms ease,
        transform 160ms ease,
        box-shadow 160ms ease;
}

/* Barra izquierda (apagada por defecto) */
.nav-item::before {
    content: "";
    position: absolute;
    left: 0.35rem;
    top: 0.55rem;
    bottom: 0.55rem;
    width: 3px;
    border-radius: 999px;
    background: rgba(16, 185, 129, 0); /* invisible */
    transform: scaleY(0.6);
    transition:
        background-color 160ms ease,
        transform 160ms ease,
        box-shadow 160ms ease;
}

.nav-ic {
    height: 1.05rem;
    width: 1.05rem;
    color: rgba(148, 163, 184, 0.95);
    transition: color 160ms ease;
}

/* Hover: suave, “premium” */
.nav-item:hover {
    background: rgba(16, 185, 129, 0.10); /* emerald-600/10 */
    color: rgba(226, 232, 240, 0.98);
    transform: translateX(1px);
}
.nav-item:hover .nav-ic {
    color: rgba(52, 211, 153, 0.98); /* emerald-400 */
}
.nav-item:hover::before {
    background: rgba(16, 185, 129, 0.35);
    transform: scaleY(0.9);
}

/* Active: barra encendida + contorno */
.nav-item--active {
    background: rgba(16, 185, 129, 0.18);
    color: rgba(255, 255, 255, 0.98);
    box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.35);
}
.nav-item--active .nav-ic {
    color: rgba(52, 211, 153, 0.98);
}
.nav-item--active::before {
    background: rgba(16, 185, 129, 1); /* emerald-600 */
    transform: scaleY(1);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18); /* glow sutil */
}

/* Acción rápida: un poquito más marcada */
.nav-item--quick {
    background: rgba(16, 185, 129, 0.08);
    box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.18);
}

/* Owl animations */
.owl-float {
    animation: owlFloat 4.2s ease-in-out infinite;
}
@keyframes owlFloat {
    0%,
    100% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
}

/* Blink */
.owl-lid {
    opacity: 0;
    animation: owlBlink 6.2s infinite;
}
.owl-lid:nth-of-type(1) { animation-delay: 0s; }
.owl-lid:nth-of-type(2) { animation-delay: 0.08s; }
@keyframes owlBlink {
    0%,
    93%,
    100% { opacity: 0; }
    95% { opacity: 1; }
    96.5% { opacity: 0; }
}
</style>