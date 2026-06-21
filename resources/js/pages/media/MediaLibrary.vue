<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

            <!-- Header -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 ring-1 ring-indigo-100">
                        <Images class="h-5 w-5 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">Biblioteca de Imágenes</h1>
                        <p class="mt-0.5 text-sm text-slate-500">{{ resumen.total }} imágenes · {{ resumen.huerfanas }} sin uso</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="resumen.huerfanas > 0"
                        type="button"
                        class="flex items-center gap-2 rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-700 hover:bg-orange-100"
                        :disabled="limpiando"
                        @click="limpiarHuerfanas"
                    >
                        <Trash2 class="h-4 w-4" />
                        Limpiar {{ resumen.huerfanas }} huérfanas
                    </button>

                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="inputRef?.click()"
                    >
                        <UploadCloud class="h-4 w-4" />
                        Subir imagen
                    </button>
                    <input ref="inputRef" type="file" accept="image/jpg,image/jpeg,image/png,image/webp" class="hidden" @change="onUpload" />
                </div>
            </div>

            <div class="flex gap-5">
                <!-- Sidebar carpetas -->
                <aside class="w-52 shrink-0">
                    <div class="rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                        <button
                            v-for="f in carpetas"
                            :key="f.tipo"
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm transition-colors"
                            :class="filtroTipo === f.tipo
                                ? 'bg-indigo-50 font-medium text-indigo-700'
                                : 'text-slate-600 hover:bg-slate-50'"
                            @click="filtroTipo = f.tipo; cargar(1)"
                        >
                            <span>{{ f.label }}</span>
                            <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ f.total }}</span>
                        </button>
                    </div>
                </aside>

                <!-- Contenido principal -->
                <div class="flex-1 overflow-hidden">
                    <!-- Barra de búsqueda -->
                    <div class="mb-4 flex gap-2">
                        <div class="relative flex-1">
                            <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="buscar"
                                type="text"
                                placeholder="Buscar por nombre de archivo..."
                                class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-8 pr-3 text-sm shadow-sm outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200"
                                @input="buscarDebounced"
                            />
                        </div>
                    </div>

                    <!-- Subida en progreso -->
                    <div v-if="subiendo" class="mb-3 flex items-center gap-2 rounded-lg bg-indigo-50 px-4 py-2.5 text-sm text-indigo-700">
                        <Loader2 class="h-4 w-4 animate-spin" />
                        Subiendo imagen...
                    </div>

                    <!-- Error -->
                    <div v-if="error" class="mb-3 rounded-lg bg-red-50 px-4 py-2.5 text-sm text-red-600">{{ error }}</div>

                    <!-- Grid imágenes -->
                    <div v-if="cargando" class="flex items-center justify-center py-20 text-slate-400">
                        <Loader2 class="h-6 w-6 animate-spin" />
                    </div>

                    <div v-else-if="imagenes.length === 0" class="flex flex-col items-center justify-center py-20 text-slate-400">
                        <Images class="mb-2 h-10 w-10" />
                        <p class="text-sm">No hay imágenes en esta carpeta</p>
                    </div>

                    <div v-else class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
                        <div
                            v-for="img in imagenes"
                            :key="img.id"
                            class="group relative cursor-pointer rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm transition-shadow hover:shadow-md"
                            @click="detalle = img"
                        >
                            <div class="aspect-square overflow-hidden rounded-lg bg-slate-100">
                                <img :src="img.url" class="h-full w-full object-cover" :alt="img.nombre_original" />
                            </div>
                            <p class="mt-1 truncate px-0.5 text-[10px] text-slate-500">{{ img.nombre_original }}</p>
                            <span
                                v-if="img.usos === 0"
                                class="absolute right-2 top-2 rounded-full bg-orange-400 px-1.5 py-0.5 text-[9px] font-bold text-white"
                            >sin uso</span>
                            <span
                                v-else
                                class="absolute right-2 top-2 rounded-full bg-slate-800/60 px-1.5 py-0.5 text-[9px] text-white"
                            >{{ img.usos }}x</span>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div v-if="totalPaginas > 1" class="mt-5 flex items-center justify-center gap-3">
                        <button
                            type="button"
                            :disabled="paginaActual <= 1"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm disabled:opacity-40 hover:bg-slate-50"
                            @click="cargar(paginaActual - 1)"
                        >Anterior</button>
                        <span class="text-sm text-slate-500">{{ paginaActual }} / {{ totalPaginas }}</span>
                        <button
                            type="button"
                            :disabled="paginaActual >= totalPaginas"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm disabled:opacity-40 hover:bg-slate-50"
                            @click="cargar(paginaActual + 1)"
                        >Siguiente</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de detalle -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="detalle" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-slate-900/50" @click="detalle = null" />
                    <div class="relative w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                        <button type="button" class="absolute right-3 top-3 text-slate-400 hover:text-slate-600" @click="detalle = null">
                            <X class="h-5 w-5" />
                        </button>

                        <div class="mb-4 overflow-hidden rounded-xl bg-slate-100">
                            <img :src="detalle.url" class="max-h-60 w-full object-contain" />
                        </div>

                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between gap-2">
                                <dt class="text-slate-500">Nombre</dt>
                                <dd class="truncate font-medium text-right">{{ detalle.nombre_original }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Carpeta</dt>
                                <dd class="font-medium">{{ detalle.carpeta_label }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Tamaño</dt>
                                <dd class="font-medium">{{ detalle.tamanio_fmt }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Usos</dt>
                                <dd class="font-medium">{{ detalle.usos }} elemento(s)</dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex justify-end gap-2">
                            <button
                                v-if="detalle.usos === 0"
                                type="button"
                                class="flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                                @click="eliminarImagen(detalle)"
                            >
                                <Trash2 class="h-4 w-4" />
                                Eliminar
                            </button>
                            <a
                                :href="detalle.url"
                                target="_blank"
                                class="flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50"
                            >
                                <ExternalLink class="h-4 w-4" />
                                Abrir
                            </a>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { Images, UploadCloud, Search, Loader2, Trash2, X, ExternalLink } from 'lucide-vue-next';
import http from '@/lib/http';

const cargando = ref(false);
const imagenes = ref([]);
const paginaActual = ref(1);
const totalPaginas = ref(1);
const buscar = ref('');
const filtroTipo = ref('todos');
const subiendo = ref(false);
const error = ref('');
const limpiando = ref(false);
const detalle = ref(null);
const inputRef = ref(null);
let buscarTimer = null;

const resumen = ref({ total: 0, huerfanas: 0, por_tipo: [] });

const carpetas = computed(() => [
    { tipo: 'todos', label: 'Todas', total: resumen.value.total },
    ...resumen.value.por_tipo,
]);

async function cargar(pagina = 1) {
    cargando.value = true;
    error.value = '';
    try {
        const { data } = await http.get('/api/media', {
            params: { tipo: filtroTipo.value, buscar: buscar.value || undefined, page: pagina },
        });
        imagenes.value = data.data;
        paginaActual.value = data.current_page;
        totalPaginas.value = data.last_page;
    } catch {
        error.value = 'Error al cargar imágenes.';
    } finally {
        cargando.value = false;
    }
}

async function cargarResumen() {
    const { data } = await http.get('/api/media/resumen');
    resumen.value = data;
}

function buscarDebounced() {
    clearTimeout(buscarTimer);
    buscarTimer = setTimeout(() => cargar(1), 350);
}

async function onUpload(e) {
    const file = e.target.files[0];
    if (!file) return;
    subiendo.value = true;
    error.value = '';
    try {
        const fd = new FormData();
        fd.append('archivo', file);
        fd.append('tipo', 'producto');
        await http.post('/api/media', fd);
        await Promise.all([cargar(1), cargarResumen()]);
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al subir la imagen.';
    } finally {
        subiendo.value = false;
        e.target.value = '';
    }
}

async function eliminarImagen(img) {
    if (!confirm(`¿Eliminar "${img.nombre_original}"?`)) return;
    try {
        await http.delete(`/api/media/${img.id}`);
        detalle.value = null;
        await Promise.all([cargar(paginaActual.value), cargarResumen()]);
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al eliminar.';
    }
}

async function limpiarHuerfanas() {
    if (!confirm(`¿Eliminar ${resumen.value.huerfanas} imágenes sin uso?`)) return;
    limpiando.value = true;
    try {
        await http.delete('/api/media/limpiar-huerfanas');
        await Promise.all([cargar(1), cargarResumen()]);
    } finally {
        limpiando.value = false;
    }
}

onMounted(() => {
    cargar(1);
    cargarResumen();
});
</script>
