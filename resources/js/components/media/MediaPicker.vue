<template>
    <div>
        <!-- Área de imagen actual + botón -->
        <div class="flex items-start gap-3">
            <div
                v-if="modelValue"
                class="relative h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
            >
                <img :src="modelValue.url" class="h-full w-full object-cover" />
                <button
                    v-if="!disabled"
                    type="button"
                    class="absolute right-0.5 top-0.5 rounded bg-white/80 p-0.5 text-slate-500 hover:text-red-500"
                    @click="$emit('clear')"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <button
                v-if="!disabled"
                type="button"
                class="flex items-center gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-600 hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-700"
                @click="abrir"
            >
                <ImageIcon class="h-4 w-4" />
                {{ modelValue ? 'Cambiar imagen' : (label || 'Seleccionar imagen') }}
            </button>
        </div>

        <!-- Modal picker -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="abierto"
                    class="fixed inset-0 z-[200] flex items-center justify-center p-4"
                >
                    <div class="absolute inset-0 bg-slate-900/50" @click="cerrar" />

                    <Transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="translate-y-3 scale-95 opacity-0"
                        enter-to-class="translate-y-0 scale-100 opacity-100"
                    >
                        <div
                            v-if="abierto"
                            class="relative flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                        >
                            <!-- Header -->
                            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                                <h2 class="text-base font-semibold text-slate-800">Seleccionar imagen</h2>
                                <button type="button" @click="cerrar" class="text-slate-400 hover:text-slate-600">
                                    <X class="h-5 w-5" />
                                </button>
                            </div>

                            <!-- Tabs -->
                            <div class="flex border-b border-slate-200">
                                <button
                                    v-for="t in tabs"
                                    :key="t.key"
                                    type="button"
                                    class="px-5 py-3 text-sm font-medium transition-colors"
                                    :class="tab === t.key
                                        ? 'border-b-2 border-indigo-600 text-indigo-700'
                                        : 'text-slate-500 hover:text-slate-700'"
                                    @click="tab = t.key"
                                >
                                    {{ t.label }}
                                </button>
                            </div>

                            <!-- Tab: Biblioteca -->
                            <div v-if="tab === 'biblioteca'" class="flex flex-1 flex-col overflow-hidden">
                                <!-- Filtros -->
                                <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
                                    <div class="relative flex-1">
                                        <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                        <input
                                            v-model="buscar"
                                            type="text"
                                            placeholder="Buscar por nombre..."
                                            class="w-full rounded-lg border border-slate-200 py-1.5 pl-8 pr-3 text-sm outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200"
                                            @input="buscarDebounced"
                                        />
                                    </div>
                                    <select
                                        v-model="filtroTipo"
                                        class="rounded-lg border border-slate-200 py-1.5 pl-2 pr-7 text-sm outline-none focus:border-indigo-400"
                                        @change="cargar(1)"
                                    >
                                        <option value="todos">Todas las carpetas</option>
                                        <option value="productos">Productos</option>
                                        <option value="variantes">Variantes</option>
                                        <option value="marcas">Logos de marcas</option>
                                        <option value="modelos">Modelos</option>
                                    </select>
                                </div>

                                <!-- Grid -->
                                <div class="flex-1 overflow-y-auto p-4">
                                    <div v-if="cargando" class="flex items-center justify-center py-12 text-slate-400">
                                        <Loader2 class="h-6 w-6 animate-spin" />
                                    </div>

                                    <div v-else-if="imagenes.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400">
                                        <ImageIcon class="mb-2 h-8 w-8" />
                                        <p class="text-sm">No hay imágenes</p>
                                    </div>

                                    <div v-else class="grid grid-cols-4 gap-3 sm:grid-cols-6">
                                        <button
                                            v-for="img in imagenes"
                                            :key="img.id"
                                            type="button"
                                            class="group relative aspect-square overflow-hidden rounded-lg border-2 transition-all"
                                            :class="seleccionada?.id === img.id
                                                ? 'border-indigo-500 ring-2 ring-indigo-200'
                                                : 'border-slate-200 hover:border-indigo-300'"
                                            @click="seleccionada = img"
                                            @dblclick="elegir(img)"
                                        >
                                            <img :src="img.url" class="h-full w-full object-cover" />
                                            <div
                                                v-if="seleccionada?.id === img.id"
                                                class="absolute inset-0 flex items-center justify-center bg-indigo-500/20"
                                            >
                                                <CheckCircle2 class="h-6 w-6 text-indigo-600 drop-shadow" />
                                            </div>
                                            <div
                                                class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 p-1 text-[9px] leading-tight text-white opacity-0 transition-opacity group-hover:opacity-100"
                                            >
                                                {{ img.carpeta_label }}
                                            </div>
                                        </button>
                                    </div>

                                    <!-- Paginación -->
                                    <div v-if="totalPaginas > 1" class="mt-4 flex items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            :disabled="paginaActual <= 1"
                                            class="rounded px-2 py-1 text-sm disabled:opacity-40"
                                            @click="cargar(paginaActual - 1)"
                                        >Ant.</button>
                                        <span class="text-sm text-slate-500">{{ paginaActual }} / {{ totalPaginas }}</span>
                                        <button
                                            type="button"
                                            :disabled="paginaActual >= totalPaginas"
                                            class="rounded px-2 py-1 text-sm disabled:opacity-40"
                                            @click="cargar(paginaActual + 1)"
                                        >Sig.</button>
                                    </div>
                                </div>

                                <!-- Footer con acción -->
                                <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3">
                                    <p v-if="seleccionada" class="truncate text-xs text-slate-500">
                                        {{ seleccionada.nombre_original }}
                                    </p>
                                    <p v-else class="text-xs text-slate-400">Haz doble clic o selecciona y confirma</p>
                                    <div class="flex gap-2">
                                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50" @click="cerrar">
                                            Cancelar
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="!seleccionada"
                                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40"
                                            @click="elegir(seleccionada)"
                                        >
                                            Usar esta imagen
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab: Subir nueva -->
                            <div v-if="tab === 'subir'" class="flex flex-1 flex-col gap-4 overflow-y-auto p-6">
                                <div
                                    class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-10 transition-colors"
                                    :class="arrastrandoArchivo ? 'border-indigo-400 bg-indigo-50' : 'border-slate-300 bg-slate-50'"
                                    @dragover.prevent="arrastrandoArchivo = true"
                                    @dragleave="arrastrandoArchivo = false"
                                    @drop.prevent="onDrop"
                                >
                                    <UploadCloud class="mb-3 h-10 w-10 text-slate-400" />
                                    <p class="text-sm font-medium text-slate-700">Arrastra una imagen aquí</p>
                                    <p class="mt-1 text-xs text-slate-400">o</p>
                                    <button
                                        type="button"
                                        class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                        @click="inputRef?.click()"
                                    >
                                        Seleccionar archivo
                                    </button>
                                    <p class="mt-2 text-xs text-slate-400">JPG, PNG, WebP · máx. 2 MB</p>
                                    <input ref="inputRef" type="file" accept="image/jpg,image/jpeg,image/png,image/webp" class="hidden" @change="onFileChange" />
                                </div>

                                <!-- Preview del archivo seleccionado -->
                                <div v-if="archivoNuevo" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                                    <img :src="previewUrl" class="h-14 w-14 rounded object-cover" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-700">{{ archivoNuevo.name }}</p>
                                        <p class="text-xs text-slate-400">{{ (archivoNuevo.size / 1024).toFixed(0) }} KB</p>
                                    </div>
                                    <button type="button" @click="archivoNuevo = null; previewUrl = null" class="text-slate-400 hover:text-red-500">
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>

                                <div v-if="errorSubir" class="rounded-lg bg-red-50 p-3 text-sm text-red-600">{{ errorSubir }}</div>

                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50" @click="cerrar">
                                        Cancelar
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="!archivoNuevo || subiendo"
                                        class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40"
                                        @click="subirArchivo"
                                    >
                                        <Loader2 v-if="subiendo" class="h-4 w-4 animate-spin" />
                                        Subir y usar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { X, ImageIcon, Search, Loader2, CheckCircle2, UploadCloud } from 'lucide-vue-next';
import http from '@/lib/http';

const props = defineProps({
    modelValue: { type: Object, default: null },
    carpetaTipo: { type: String, default: 'producto' },
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'clear']);

const abierto = ref(false);
const tab = ref('biblioteca');
const tabs = [
    { key: 'biblioteca', label: 'Biblioteca' },
    { key: 'subir', label: 'Subir nueva' },
];

// Biblioteca
const cargando = ref(false);
const imagenes = ref([]);
const seleccionada = ref(null);
const buscar = ref('');
const filtroTipo = ref('todos');
const paginaActual = ref(1);
const totalPaginas = ref(1);
let buscarTimer = null;

// Subir
const inputRef = ref(null);
const archivoNuevo = ref(null);
const previewUrl = ref(null);
const subiendo = ref(false);
const errorSubir = ref('');
const arrastrandoArchivo = ref(false);

function abrir() {
    abierto.value = true;
    tab.value = 'biblioteca';
    seleccionada.value = null;
    cargar(1);
}

function cerrar() {
    abierto.value = false;
    archivoNuevo.value = null;
    previewUrl.value = null;
    errorSubir.value = '';
}

async function cargar(pagina = 1) {
    cargando.value = true;
    try {
        const { data } = await http.get('/api/media', {
            params: { tipo: filtroTipo.value, buscar: buscar.value || undefined, page: pagina },
        });
        imagenes.value = data.data;
        paginaActual.value = data.current_page;
        totalPaginas.value = data.last_page;
    } finally {
        cargando.value = false;
    }
}

function buscarDebounced() {
    clearTimeout(buscarTimer);
    buscarTimer = setTimeout(() => cargar(1), 350);
}

function elegir(img) {
    if (!img) return;
    emit('update:modelValue', img);
    cerrar();
}

// Subir
function onFileChange(e) {
    const file = e.target.files[0];
    if (file) setArchivo(file);
}

function onDrop(e) {
    arrastrandoArchivo.value = false;
    const file = e.dataTransfer.files[0];
    if (file) setArchivo(file);
}

function setArchivo(file) {
    archivoNuevo.value = file;
    previewUrl.value = URL.createObjectURL(file);
    errorSubir.value = '';
}

async function subirArchivo() {
    if (!archivoNuevo.value) return;
    subiendo.value = true;
    errorSubir.value = '';
    try {
        const fd = new FormData();
        fd.append('archivo', archivoNuevo.value);
        fd.append('tipo', props.carpetaTipo);
        const { data } = await http.post('/api/media', fd);
        emit('update:modelValue', data.data);
        cerrar();
    } catch (e) {
        errorSubir.value = e.response?.data?.message || 'Error al subir la imagen.';
    } finally {
        subiendo.value = false;
    }
}
</script>
