<template>
    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white px-4 py-4">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-black text-slate-950">Etiquetas de precio</h1>
                    <p class="text-sm text-slate-500">Selecciona artículos, cantidades y precio antes de imprimir.</p>
                </div>
                <div class="flex gap-2">
                    <RouterLink to="/etiquetas/plantillas" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700">Diseñar plantillas</RouterLink>
                    <button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60" :disabled="imprimiendo" @click="imprimir">{{ imprimiendo ? "Imprimiendo..." : `Imprimir ${totalEtiquetas} etiquetas` }}</button>
                </div>
            </div>
        </header>

        <main class="mx-auto grid max-w-7xl gap-5 p-4 lg:grid-cols-[1fr_360px]">
            <section class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <label class="text-xs font-bold uppercase text-slate-500">Agregar productos o variantes</label>
                    <div class="mt-2 flex gap-2">
                        <input v-model="buscar" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2" placeholder="Nombre, código, SKU o barras" @keyup.enter="buscarProductos">
                        <button class="rounded-xl bg-emerald-600 px-4 py-2 font-bold text-white" @click="buscarProductos">Buscar</button>
                    </div>
                    <div v-if="resultados.length" class="mt-2 divide-y rounded-xl border border-slate-200">
                        <button v-for="r in resultados" :key="r.id" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-50" @click="agregar(r)">
                            <span><strong>{{ r.datos.producto.nombre }}</strong><span v-if="r.datos.variante.nombre"> · {{ r.datos.variante.nombre }}</span></span>
                            <span class="font-mono">{{ r.datos.calculados.codigo_preferido }}</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
                        <div class="flex gap-2">
                            <button class="text-sm font-bold text-emerald-700" @click="seleccionarTodo(true)">Seleccionar todo</button>
                            <button class="text-sm font-bold text-slate-500" @click="seleccionarTodo(false)">Deseleccionar</button>
                        </div>
                        <span class="text-sm text-slate-500">{{ items.length }} artículos · {{ totalEtiquetas }} etiquetas</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="p-3">Imprimir</th><th class="p-3">Producto</th><th class="p-3">Código</th><th class="p-3">Etiquetas</th><th class="p-3">Precio impreso</th><th class="p-3"></th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in items" :key="item.id">
                                    <td class="p-3"><input v-model="item.seleccionado" type="checkbox" class="h-5 w-5 accent-emerald-600"></td>
                                    <td class="p-3"><strong>{{ item.datos.producto.nombre }}</strong><div class="text-xs text-slate-500">{{ item.datos.variante.nombre }}</div></td>
                                    <td class="p-3 font-mono text-xs">{{ item.datos.calculados.codigo_preferido }}</td>
                                    <td class="p-3"><input v-model.number="item.cantidad" type="number" min="0" step="1" class="w-24 rounded-lg border border-slate-300 px-2 py-1"></td>
                                    <td class="p-3"><input v-model.number="item.precio_impresion" type="number" min="0" step=".01" class="w-28 rounded-lg border border-slate-300 px-2 py-1"></td>
                                    <td class="p-3"><button class="text-red-600" @click="quitar(item)">Quitar</button></td>
                                </tr>
                                <tr v-if="!items.length"><td colspan="6" class="p-10 text-center text-slate-400">No hay artículos preparados.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <label class="text-xs font-bold uppercase text-slate-500">Plantilla</label>
                    <select v-model="plantillaId" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2"><option v-for="p in plantillas" :key="p.id" :value="p.id">{{ p.nombre }} · {{ p.ancho_mm }}×{{ p.alto_mm }} mm</option></select>
                    <label class="mt-3 block text-xs font-bold uppercase text-slate-500">Perfil de impresión</label>
                    <select v-model="perfilId" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2"><option v-for="p in perfiles" :key="p.id" :value="p.id">{{ p.nombre }} · {{ p.material }}</option></select>

                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h2 class="mb-3 text-sm font-black text-slate-900">Vista previa</h2>
                    <div class="overflow-auto rounded-xl bg-slate-100 p-3">
                        <EtiquetaVista v-if="plantillaActual && itemPreview" :plantilla="plantillaActual" :datos="itemPreview.datos" :precio-impresion="itemPreview.precio_impresion" :escala="4" :rotacion="perfilActual?.rotacion || 0" />
                    </div>
                </div>
                <QzImpresoraSelector :perfil-id="perfilId" @cambiar="impresoraQz = $event" />
            </aside>
        </main>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, toRaw } from "vue";
import Swal from "sweetalert2";
import http from "@/lib/http";
import EtiquetaVista from "@/components/etiquetas/EtiquetaVista.vue";
import QzImpresoraSelector from "@/components/etiquetas/QzImpresoraSelector.vue";
import { imprimirEtiquetas } from "@/helpers/etiquetas";

const items = ref([]);
const plantillas = ref([]);
const perfiles = ref([]);
const plantillaId = ref(null);
const perfilId = ref(null);
const buscar = ref("");
const resultados = ref([]);
const impresoraQz = ref(null);
const imprimiendo = ref(false);

const plantillaActual = computed(() => plantillas.value.find((p) => p.id === Number(plantillaId.value)));
const perfilActual = computed(() => perfiles.value.find((p) => p.id === Number(perfilId.value)));
const itemPreview = computed(() => items.value.find((i) => i.seleccionado) || items.value[0]);
const totalEtiquetas = computed(() => items.value.filter((i) => i.seleccionado).reduce((t, i) => t + Math.max(0, Number(i.cantidad) || 0), 0));

onMounted(async () => {
    try {
        const { data } = await http.get("/api/etiquetas/configuracion");
        const plantillasP = data.plantillas.filter((p) => p.tipo === "precio");
        plantillas.value = plantillasP;
        perfiles.value = data.perfiles;
        plantillaId.value = (plantillasP.find((p) => p.predeterminada) || plantillasP[0])?.id;
        perfilId.value = (data.perfiles.find((p) => p.predeterminado) || data.perfiles[0])?.id;
    } catch (e) {
        Swal.fire("Error al cargar", e.response?.data?.message || e.message, "error");
    }
});

async function buscarProductos() {
    const { data } = await http.get("/api/etiquetas/catalogo", { params: { q: buscar.value } });
    resultados.value = data;
}
function agregar(item) {
    if (!items.value.some((i) => i.id === item.id)) items.value.push(structuredClone(toRaw(item)));
    resultados.value = [];
    buscar.value = "";
}
function quitar(item) { items.value = items.value.filter((i) => i !== item); }
function seleccionarTodo(valor) { items.value.forEach((i) => { i.seleccionado = valor; }); }

async function imprimir() {
    if (imprimiendo.value) return;
    imprimiendo.value = true;
    try {
        await imprimirEtiquetas({ plantilla: plantillaActual.value, perfil: perfilActual.value, items: items.value, impresoraQz: impresoraQz.value });
    } catch (e) {
        Swal.fire("No se pudo imprimir", e.response?.data?.message || e.message, "error");
    } finally {
        imprimiendo.value = false;
    }
}
</script>
