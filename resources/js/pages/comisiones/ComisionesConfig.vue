<template>
    <main class="mx-auto max-w-screen-2xl space-y-5 p-3 sm:p-6">
        <!-- Encabezado -->
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Configuración</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Comisiones</h1>
                <p class="mt-1 text-sm text-slate-500">Define cuánto gana cada vendedor y cajero por lo que vende.</p>
            </div>
            <button type="button" class="boton-primario" @click="abrirEsquemaModal(null)">
                <Plus class="h-4 w-4" /> Nuevo esquema
            </button>
        </header>

        <nav class="flex gap-1 border-b border-slate-200" aria-label="Secciones de comisiones">
            <button
                v-for="t in TABS"
                :key="t.valor"
                type="button"
                class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                :class="tab === t.valor ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                :aria-current="tab === t.valor ? 'page' : undefined"
                @click="tab = t.valor"
            >
                <component :is="t.icono" class="h-4 w-4" />
                {{ t.label }}
                <span v-if="t.valor === 'esquemas' && esquemas.length" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ esquemas.length }}</span>
            </button>
        </nav>

        <!-- ══ Esquemas ══════════════════════════════════════════════════════ -->
        <template v-if="tab === 'esquemas'">
            <div v-if="cargando" class="flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white p-12 text-sm text-slate-500">
                <Loader2 class="h-4 w-4 animate-spin" /> Cargando esquemas…
            </div>

            <!-- Primer uso -->
            <section v-else-if="esquemas.length === 0" class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-10">
                <div class="mx-auto max-w-2xl text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <BadgePercent class="h-7 w-7" />
                    </span>
                    <h2 class="mt-4 text-lg font-bold text-slate-900">Configura tus comisiones en 3 pasos</h2>
                    <p class="mt-1 text-sm text-slate-500">Las comisiones se calculan solas en cada venta y se descuentan si la venta se cancela o se devuelve.</p>
                </div>
                <ol class="mx-auto mt-8 grid max-w-3xl gap-3 sm:grid-cols-3">
                    <li v-for="(paso, i) in PASOS" :key="paso.titulo" class="rounded-xl border border-slate-200 p-4">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">{{ i + 1 }}</span>
                        <p class="mt-3 text-sm font-semibold text-slate-800">{{ paso.titulo }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ paso.texto }}</p>
                    </li>
                </ol>
                <div class="mt-8 flex justify-center">
                    <button type="button" class="boton-primario" @click="abrirEsquemaModal(null)">
                        <Plus class="h-4 w-4" /> Crear el primer esquema
                    </button>
                </div>
            </section>

            <div v-else class="grid items-start gap-5 lg:grid-cols-[300px_1fr]">
                <!-- Lista de esquemas -->
                <aside class="overflow-hidden rounded-2xl border border-slate-200 bg-white lg:sticky lg:top-4">
                    <div class="border-b border-slate-100 p-3">
                        <label class="relative block">
                            <span class="sr-only">Buscar esquema</span>
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input v-model="filtroEsquemas" type="search" placeholder="Buscar esquema…" class="campo pl-9" />
                        </label>
                    </div>
                    <div class="max-h-[65vh] overflow-y-auto">
                        <div v-for="grupo in gruposEsquemas" :key="grupo.beneficiario">
                            <p class="flex items-center gap-1.5 bg-slate-50 px-4 py-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <component :is="beneficiario(grupo.beneficiario).icono" class="h-3.5 w-3.5" />
                                {{ grupo.titulo }}
                            </p>
                            <ul>
                                <li v-for="e in grupo.items" :key="e.id">
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-3 border-l-[3px] px-4 py-3 text-left transition"
                                        :class="esquema?.id === e.id ? 'border-emerald-600 bg-emerald-50/70' : 'border-transparent hover:bg-slate-50'"
                                        :aria-current="esquema?.id === e.id ? 'true' : undefined"
                                        @click="abrirEsquema(e.id)"
                                    >
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="esquema?.id === e.id ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500'">
                                            <component :is="modoEsquema(e.modo).icono" class="h-4 w-4" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-1.5">
                                                <span class="truncate text-sm font-semibold" :class="e.activo ? 'text-slate-800' : 'text-slate-400'">{{ e.nombre }}</span>
                                                <span v-if="!e.activo" class="shrink-0 rounded bg-slate-200 px-1.5 py-px text-[10px] font-semibold text-slate-500">Inactivo</span>
                                            </span>
                                            <span class="block truncate text-xs text-slate-500">{{ resumenEsquema(e) }}</span>
                                        </span>
                                        <span class="flex shrink-0 items-center gap-1 text-xs text-slate-400" :title="`${e.usuarios_count} usuario(s) asignado(s)`">
                                            <Users class="h-3.5 w-3.5" />{{ e.usuarios_count }}
                                        </span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <p v-if="!gruposEsquemas.length" class="px-4 py-8 text-center text-sm text-slate-400">Sin resultados.</p>
                    </div>
                </aside>

                <!-- Detalle -->
                <section v-if="!esquema" class="flex min-h-[320px] flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-400">
                    <BadgePercent class="h-10 w-10 opacity-30" />
                    <p class="text-sm">Selecciona un esquema para ver y editar sus comisiones.</p>
                </section>

                <section v-else class="space-y-5">
                    <!-- Resumen del esquema -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                    <component :is="modoEsquema(esquema.modo).icono" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="truncate text-lg font-bold text-slate-900">{{ esquema.nombre }}</h2>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span class="chip">
                                            <component :is="beneficiario(esquema.beneficiario).icono" class="h-3 w-3" />
                                            Para {{ beneficiario(esquema.beneficiario).label.toLowerCase() }}
                                        </span>
                                        <span class="chip">{{ modoEsquema(esquema.modo).label }}</span>
                                        <span class="chip" :class="esquema.activo ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                                            <span class="h-1.5 w-1.5 rounded-full" :class="esquema.activo ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                            {{ esquema.activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                    <p v-if="esquema.descripcion" class="mt-2 text-sm text-slate-500">{{ esquema.descripcion }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="boton-secundario" @click="probarAbierto = true">
                                    <FlaskConical class="h-4 w-4" /> Probar
                                </button>
                                <button type="button" class="boton-icono" title="Editar datos" aria-label="Editar datos del esquema" @click="abrirEsquemaModal(esquema)">
                                    <Settings2 class="h-4 w-4" />
                                </button>
                                <button type="button" class="boton-icono" title="Duplicar" aria-label="Duplicar esquema" @click="duplicarEsquema">
                                    <Copy class="h-4 w-4" />
                                </button>
                                <button type="button" class="boton-icono hover:border-red-200 hover:bg-red-50 hover:text-red-600" title="Eliminar" aria-label="Eliminar esquema" @click="eliminarEsquema">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                            <span class="flex items-center gap-2 text-slate-600">
                                <Users class="h-4 w-4 text-slate-400" />
                                <template v-if="esquema.usuarios_count">Asignado a <strong class="text-slate-800">{{ esquema.usuarios_count }} usuario(s)</strong></template>
                                <template v-else>Todavía no está asignado a nadie</template>
                            </span>
                            <button type="button" class="text-sm font-semibold text-emerald-700 hover:underline" @click="tab = 'asignacion'">Asignar usuarios →</button>
                        </div>
                    </div>

                    <!-- Igual para todo -->
                    <div v-if="esquema.modo === 'general'" class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="text-sm font-bold text-slate-900">Comisión</h3>
                        <p class="mb-3 text-xs text-slate-500">Se aplica a todos los productos.</p>
                        <GrupoCard
                            v-if="esquema.general"
                            :grupo="esquema.general"
                            titulo="Todos los productos"
                            class="max-w-md"
                            @editar="abrirGrupo('general', esquema.general)"
                            @eliminar="eliminarGrupo(esquema.general)"
                        />
                        <button v-else type="button" class="caja-vacia" @click="abrirGrupo('general', null)">
                            <Plus class="h-5 w-5" />
                            <span>Configurar la comisión</span>
                        </button>
                    </div>

                    <!-- Por categoría / producto -->
                    <template v-else>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">
                                        {{ esPorProducto ? 'Productos' : 'Categorías' }}
                                        <span class="ml-1 font-normal text-slate-400">{{ esquema.grupos.length }}</span>
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        {{ esPorProducto ? 'Cada producto con su propia comisión.' : 'Una categoría también aplica a sus subcategorías que no tengan comisión propia.' }}
                                    </p>
                                </div>
                                <button v-if="esquema.grupos.length" type="button" class="boton-secundario" @click="abrirGrupo(esquema.modo, null)">
                                    <Plus class="h-4 w-4" /> Agregar {{ esPorProducto ? 'producto' : 'categoría' }}
                                </button>
                            </div>

                            <button v-if="!esquema.grupos.length" type="button" class="caja-vacia" @click="abrirGrupo(esquema.modo, null)">
                                <Plus class="h-5 w-5" />
                                <span>Agrega {{ esPorProducto ? 'el primer producto' : 'la primera categoría' }}</span>
                            </button>
                            <div v-else class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                                <GrupoCard
                                    v-for="g in esquema.grupos"
                                    :key="g.referencia_id"
                                    :grupo="g"
                                    @editar="abrirGrupo(esquema.modo, g)"
                                    @eliminar="eliminarGrupo(g)"
                                />
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 class="text-sm font-bold text-slate-900">Para lo demás</h3>
                            <p class="mb-3 text-xs text-slate-500">
                                Productos sin comisión de {{ esPorProducto ? 'producto' : 'categoría' }}, o con precio fuera de sus rangos cuando así se indica.
                            </p>
                            <GrupoCard
                                v-if="esquema.general"
                                :grupo="esquema.general"
                                titulo="Lo demás"
                                destacada
                                class="max-w-md"
                                @editar="abrirGrupo('general', esquema.general)"
                                @eliminar="eliminarGrupo(esquema.general)"
                            />
                            <div v-else class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-slate-300 px-4 py-3">
                                <span class="flex items-center gap-2 text-sm text-slate-500">
                                    <Ban class="h-4 w-4 text-slate-400" /> Lo demás no paga comisión
                                </span>
                                <button type="button" class="boton-secundario" @click="abrirGrupo('general', null)">
                                    <Plus class="h-4 w-4" /> Poner comisión
                                </button>
                            </div>
                        </div>
                    </template>
                </section>
            </div>
        </template>

        <!-- ══ Asignación ════════════════════════════════════════════════════ -->
        <section v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">¿Qué esquema usa cada usuario?</h2>
                    <p class="text-xs text-slate-500">Los cambios se guardan al momento y aplican a las ventas nuevas.</p>
                </div>
                <label class="relative block sm:w-72">
                    <span class="sr-only">Buscar usuario</span>
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input v-model="filtroUsuarios" type="search" placeholder="Buscar usuario…" class="campo pl-9" />
                </label>
            </div>

            <div v-if="cargandoUsuarios" class="flex items-center justify-center gap-2 p-12 text-sm text-slate-500">
                <Loader2 class="h-4 w-4 animate-spin" /> Cargando usuarios…
            </div>

            <template v-else>
                <!-- Encabezados (escritorio) -->
                <div class="hidden grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)] gap-4 bg-slate-50 px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-500 md:grid">
                    <span>Usuario</span>
                    <span class="flex items-center gap-1.5"><UserRound class="h-3.5 w-3.5" /> Como vendedor</span>
                    <span class="flex items-center gap-1.5"><ShoppingBag class="h-3.5 w-3.5" /> Como cajero</span>
                </div>

                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="u in usuariosFiltrados"
                        :key="u.id"
                        class="grid gap-3 px-4 py-3 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)] md:items-center md:gap-4"
                        :class="u.activo ? '' : 'bg-slate-50/60'"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="u.activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-500'">
                                {{ iniciales(u.name) }}
                            </span>
                            <div class="min-w-0">
                                <p class="flex items-center gap-1.5 truncate text-sm font-semibold" :class="u.activo ? 'text-slate-800' : 'text-slate-500'">
                                    {{ u.name }}
                                    <span v-if="!u.activo" class="rounded bg-slate-200 px-1.5 py-px text-[10px] font-semibold text-slate-500">Inactivo</span>
                                    <CircleCheck v-if="guardadoReciente === u.id" class="h-4 w-4 text-emerald-600" aria-label="Guardado" />
                                    <Loader2 v-if="guardandoUsuario === u.id" class="h-4 w-4 animate-spin text-slate-400" />
                                </p>
                                <p class="truncate text-xs text-slate-400">{{ u.email }}</p>
                            </div>
                        </div>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500 md:sr-only">Como vendedor</span>
                            <select
                                v-model="u.esquema_vendedor_id"
                                class="campo py-2"
                                :class="u.esquema_vendedor_id ? 'border-emerald-200 bg-emerald-50/40 font-medium text-emerald-900' : 'text-slate-500'"
                                :disabled="guardandoUsuario === u.id"
                                @change="asignar(u)"
                            >
                                <option :value="null">Sin comisión</option>
                                <option v-for="e in esquemasDe('vendedor')" :key="e.id" :value="e.id">{{ e.nombre }}{{ e.activo ? '' : ' (inactivo)' }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500 md:sr-only">Como cajero</span>
                            <select
                                v-model="u.esquema_cajero_id"
                                class="campo py-2"
                                :class="u.esquema_cajero_id ? 'border-emerald-200 bg-emerald-50/40 font-medium text-emerald-900' : 'text-slate-500'"
                                :disabled="guardandoUsuario === u.id"
                                @change="asignar(u)"
                            >
                                <option :value="null">Sin comisión</option>
                                <option v-for="e in esquemasDe('cajero')" :key="e.id" :value="e.id">{{ e.nombre }}{{ e.activo ? '' : ' (inactivo)' }}</option>
                            </select>
                        </label>
                    </li>
                    <li v-if="!usuariosFiltrados.length" class="px-4 py-10 text-center text-sm text-slate-400">Sin usuarios.</li>
                </ul>
            </template>

            <p class="flex items-start gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                Si en una venta el vendedor y el cajero son la misma persona, solo cobra la comisión de vendedor.
            </p>
        </section>

        <EsquemaModal :open="esquemaModalAbierto" :esquema="esquemaEditando" @close="esquemaModalAbierto = false" @guardado="esquemaGuardado" />
        <GrupoModal
            :open="grupoAbierto"
            :esquema="esquema"
            :aplica-a="grupoAplicaA"
            :grupo="grupoEditando"
            @close="grupoAbierto = false"
            @guardado="grupoGuardado"
        />
        <ProbarReglaModal :open="probarAbierto" :esquema-id="esquema?.id ?? null" :esquema-nombre="esquema?.nombre ?? ''" @close="probarAbierto = false" />
    </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import {
    BadgePercent, Ban, CircleCheck, Copy, FlaskConical, Info, Loader2, Plus, Search, Settings2, ShoppingBag, Trash2, UserRound, Users,
} from "lucide-vue-next";
import http from "@/lib/http";
import { confirm, swal, toastError, toastSuccess } from "@/lib/alert";
import EsquemaModal from "@/components/comisiones/EsquemaModal.vue";
import GrupoCard from "@/components/comisiones/GrupoCard.vue";
import GrupoModal from "@/components/comisiones/GrupoModal.vue";
import ProbarReglaModal from "@/components/comisiones/ProbarReglaModal.vue";
import { beneficiario, iniciales, modoEsquema } from "@/helpers/comisiones";

const TABS = [
    { valor: "esquemas", label: "Esquemas", icono: BadgePercent },
    { valor: "asignacion", label: "Asignación", icono: Users },
];

const PASOS = [
    { titulo: "Crea un esquema", texto: "Elige si es para vendedores o cajeros y si paga igual para todo, por categoría o por producto." },
    { titulo: "Define cuánto paga", texto: "Porcentaje o monto por pieza; con un solo valor o por rangos de precio." },
    { titulo: "Asígnalo", texto: "En la pestaña Asignación elige qué esquema usa cada usuario." },
];

const tab = ref("esquemas");

// ── Esquemas ──────────────────────────────────────────────────────────────────

const esquemas = ref([]);
const cargando = ref(true);
const esquema = ref(null); // esquema abierto (con grupos)
const filtroEsquemas = ref("");

const esquemaModalAbierto = ref(false);
const esquemaEditando = ref(null);
const grupoAbierto = ref(false);
const grupoEditando = ref(null);
const grupoAplicaA = ref("general");
const probarAbierto = ref(false);

const esPorProducto = computed(() => esquema.value?.modo === "producto");

const gruposEsquemas = computed(() => {
    const q = filtroEsquemas.value.trim().toLowerCase();
    const lista = q ? esquemas.value.filter((e) => e.nombre.toLowerCase().includes(q)) : esquemas.value;
    return [
        { beneficiario: "vendedor", titulo: "Para vendedores" },
        { beneficiario: "cajero", titulo: "Para cajeros" },
    ]
        .map((g) => ({ ...g, items: lista.filter((e) => e.beneficiario === g.beneficiario) }))
        .filter((g) => g.items.length);
});

function resumenEsquema(e) {
    if (e.modo === "general") return e.tiene_general ? "Igual para todo" : "Sin configurar";
    const n = e.grupos_count;
    const que = e.modo === "producto" ? (n === 1 ? "producto" : "productos") : (n === 1 ? "categoría" : "categorías");
    return `${n} ${que}${e.tiene_general ? " + lo demás" : ""}`;
}

function erroresDe(e) {
    return Object.fromEntries(Object.entries(e?.response?.data?.errors ?? {}).map(([k, v]) => [k, v[0]]));
}

async function cargarEsquemas() {
    try {
        const { data } = await http.get("/api/comisiones/esquemas");
        esquemas.value = data;
    } catch {
        toastError("No se pudieron cargar los esquemas.");
    } finally {
        cargando.value = false;
    }
}

async function abrirEsquema(id) {
    try {
        const { data } = await http.get(`/api/comisiones/esquemas/${id}`);
        esquema.value = data;
    } catch {
        toastError("No se pudo cargar el esquema.");
    }
}

function abrirEsquemaModal(e) {
    esquemaEditando.value = e;
    esquemaModalAbierto.value = true;
}

async function esquemaGuardado(data) {
    const nuevo = !esquemaEditando.value;
    esquemaModalAbierto.value = false;
    toastSuccess(nuevo ? `Esquema "${data.nombre}" creado.` : "Esquema actualizado.");
    esquema.value = data;
    tab.value = "esquemas";
    await cargarEsquemas();
}

async function duplicarEsquema() {
    const { value: nombre, isConfirmed } = await swal.fire({
        title: "Duplicar esquema",
        text: "Se copian todas sus comisiones. Ponle un nombre a la copia.",
        input: "text",
        inputValue: `${esquema.value.nombre} (copia)`,
        showCancelButton: true,
        confirmButtonText: "Duplicar",
        cancelButtonText: "Cancelar",
        inputValidator: (v) => (!v?.trim() ? "Escribe un nombre." : undefined),
    });
    if (!isConfirmed) return;

    try {
        const { data } = await http.post(`/api/comisiones/esquemas/${esquema.value.id}/duplicar`, { nombre: nombre.trim() });
        toastSuccess(`Esquema "${data.nombre}" creado.`);
        esquema.value = data;
        await cargarEsquemas();
    } catch (e) {
        toastError(erroresDe(e).nombre || e?.response?.data?.message || "No se pudo duplicar.");
    }
}

async function eliminarEsquema() {
    const ok = await confirm({
        title: `¿Eliminar el esquema "${esquema.value.nombre}"?`,
        text: "Los usuarios que lo tengan asignado quedarán sin comisión.",
        confirmText: "Sí, eliminar",
    });
    if (!ok) return;

    try {
        await http.delete(`/api/comisiones/esquemas/${esquema.value.id}`);
        toastSuccess("Esquema eliminado.");
        esquema.value = null;
        await cargarEsquemas();
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo eliminar el esquema.");
    }
}

// ── Grupos (categoría / producto / lo demás) ──────────────────────────────────

function abrirGrupo(aplicaA, grupo) {
    grupoAplicaA.value = aplicaA;
    grupoEditando.value = grupo;
    grupoAbierto.value = true;
}

async function grupoGuardado(data) {
    grupoAbierto.value = false;
    toastSuccess("Comisión guardada.");
    esquema.value = data;
    await cargarEsquemas();
}

async function eliminarGrupo(grupo) {
    const nombre = grupo.aplica_a === "general"
        ? (esquema.value.modo === "general" ? "la comisión" : "la comisión para lo demás")
        : `"${grupo.referencia_nombre}"`;
    const ok = await confirm({
        title: `¿Quitar ${nombre}?`,
        text: "Las comisiones ya generadas no cambian.",
        confirmText: "Sí, quitar",
    });
    if (!ok) return;

    try {
        const { data } = await http.delete(`/api/comisiones/esquemas/${esquema.value.id}/grupos`, {
            data: { aplica_a: grupo.aplica_a, referencia_id: grupo.referencia_id },
        });
        toastSuccess(data.message);
        esquema.value = data.esquema;
        await cargarEsquemas();
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo quitar.");
    }
}

// ── Asignación ────────────────────────────────────────────────────────────────

const usuarios = ref([]);
const cargandoUsuarios = ref(false);
const usuariosCargados = ref(false);
const filtroUsuarios = ref("");
const guardandoUsuario = ref(null);
const guardadoReciente = ref(null);
let temporizadorGuardado = null;

const usuariosFiltrados = computed(() => {
    const q = filtroUsuarios.value.trim().toLowerCase();
    if (!q) return usuarios.value;
    return usuarios.value.filter((u) => `${u.name} ${u.email ?? ""}`.toLowerCase().includes(q));
});

function esquemasDe(tipo) {
    return esquemas.value.filter((e) => e.beneficiario === tipo);
}

async function cargarUsuarios() {
    cargandoUsuarios.value = true;
    try {
        const { data } = await http.get("/api/comisiones/usuarios");
        usuarios.value = data;
        usuariosCargados.value = true;
    } catch {
        toastError("No se pudieron cargar los usuarios.");
    } finally {
        cargandoUsuarios.value = false;
    }
}

async function asignar(u) {
    guardandoUsuario.value = u.id;
    try {
        const { data } = await http.put(`/api/comisiones/usuarios/${u.id}`, {
            esquema_vendedor_id: u.esquema_vendedor_id,
            esquema_cajero_id: u.esquema_cajero_id,
        });
        Object.assign(u, data);
        guardadoReciente.value = u.id;
        clearTimeout(temporizadorGuardado);
        temporizadorGuardado = setTimeout(() => (guardadoReciente.value = null), 2000);
        await cargarEsquemas();
        if (esquema.value) await abrirEsquema(esquema.value.id);
    } catch (e) {
        toastError(Object.values(erroresDe(e))[0] || e?.response?.data?.message || "No se pudo asignar.");
        await cargarUsuarios();
    } finally {
        guardandoUsuario.value = null;
    }
}

watch(tab, (t) => {
    if (t === "asignacion" && !usuariosCargados.value) cargarUsuarios();
});

onMounted(async () => {
    await cargarEsquemas();
    if (esquemas.value.length) await abrirEsquema(esquemas.value[0].id);
});
</script>

<style scoped>
@reference "../../../css/app.css";

.campo {
    @apply block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 disabled:opacity-60;
}
.chip {
    @apply inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600;
}
.boton-primario {
    @apply flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-200 disabled:opacity-50;
}
.boton-secundario {
    @apply flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-100;
}
.boton-icono {
    @apply flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-100;
}
.caja-vacia {
    @apply flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 px-4 py-8 text-sm font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-50/50;
}
</style>
