<template>
    <main class="min-h-screen p-2 sm:p-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                    <Upload class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold tracking-tight text-slate-900">
                        Importar productos
                    </h1>
                    <p class="text-xs text-slate-500">
                        Productos base, categorias, marca, modelo, unidad y stock inicial
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                    :disabled="descargando"
                    @click="descargarPlantilla"
                >
                    <Download v-if="!descargando" class="h-4 w-4" />
                    <Loader2 v-else class="h-4 w-4 animate-spin" />
                    Plantilla
                </button>
            </div>
        </div>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <label class="block text-sm font-medium text-slate-700">
                        Archivo Excel
                    </label>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                            @change="seleccionarArchivo"
                        />
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                            :disabled="!archivo || previsualizando"
                            @click="previsualizar"
                        >
                            <Loader2 v-if="previsualizando" class="h-4 w-4 animate-spin" />
                            <Eye v-else class="h-4 w-4" />
                            Previsualizar
                        </button>
                    </div>
                </div>

                <div v-if="resultado" class="rounded-xl border border-slate-200 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Resultado de previsualizacion
                            </h2>
                            <p class="text-xs text-slate-500">
                                Revisa antes de guardar la importacion
                            </p>
                        </div>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="resultado.valido ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                        >
                            {{ resultado.valido ? "Listo para importar" : "Con errores" }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-4">
                        <div
                            v-for="item in resumenItems"
                            :key="item.label"
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3"
                        >
                            <p class="text-xs font-medium text-slate-500">
                                {{ item.label }}
                            </p>
                            <p class="mt-1 text-xl font-semibold text-slate-900">
                                {{ item.valor ?? 0 }}
                            </p>
                        </div>
                    </div>

                    <div v-if="catalogosPendientes.length" class="border-t border-slate-200 px-5 py-4">
                        <h3 class="text-sm font-semibold text-slate-900">
                            Catalogos que se crearan
                        </h3>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div
                                v-for="grupo in catalogosPendientes"
                                :key="grupo.label"
                                class="rounded-lg border border-slate-200 bg-slate-50 p-3"
                            >
                                <p class="text-xs font-semibold uppercase text-slate-500">
                                    {{ grupo.label }}
                                </p>
                                <ul class="mt-2 max-h-32 space-y-1 overflow-y-auto text-sm text-slate-700">
                                    <li v-for="item in grupo.items" :key="item">
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div v-if="resultado.errores?.length" class="border-t border-slate-200 px-5 py-4">
                        <h3 class="text-sm font-semibold text-red-700">
                            Errores
                        </h3>
                        <div class="mt-3 overflow-hidden rounded-lg border border-red-100">
                            <table class="min-w-full divide-y divide-red-100 text-sm">
                                <thead class="bg-red-50 text-left text-xs font-semibold uppercase text-red-700">
                                    <tr>
                                        <th class="px-3 py-2">Fila</th>
                                        <th class="px-3 py-2">Campo</th>
                                        <th class="px-3 py-2">Mensaje</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-red-50 bg-white">
                                    <tr v-for="error in resultado.errores" :key="`${error.fila}-${error.campo}-${error.mensaje}`">
                                        <td class="px-3 py-2 font-mono text-slate-700">{{ error.fila }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ error.campo }}</td>
                                        <td class="px-3 py-2 text-slate-600">{{ error.mensaje }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-4">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                            :disabled="!resultado.valido || importando"
                            @click="importar"
                        >
                            <Loader2 v-if="importando" class="h-4 w-4 animate-spin" />
                            <Upload v-else class="h-4 w-4" />
                            Importar productos
                        </button>
                    </div>
                </div>
            </div>

            <aside class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-slate-900">
                    Columnas soportadas
                </h2>
                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <p>
                        La categoria acepta ruta de arbol con <span class="font-mono">&gt;</span>.
                    </p>
                    <p class="rounded-lg bg-slate-50 p-3 font-mono text-xs text-slate-700">
                        Accesorios &gt; Cargadores &gt; USB-C
                    </p>
                    <p>
                        Si la categoria, marca, modelo o unidad no existe, se creara al confirmar.
                    </p>
                    <p>
                        Si <span class="font-mono">codigo</span> ya existe, el producto se actualiza. Si esta vacio, se crea con codigo automatico.
                    </p>
                    <p>
                        <span class="font-mono">stock_inicial</span> deja esa existencia como stock actual en la sucursal activa.
                    </p>
                </div>
            </aside>
        </section>
    </main>
</template>

<script setup>
import { computed, ref } from "vue";
import { Download, Eye, Loader2, Upload } from "lucide-vue-next";
import http from "@/lib/http";
import { toastError, toastSuccess } from "@/lib/alert";

const archivo = ref(null);
const resultado = ref(null);
const descargando = ref(false);
const previsualizando = ref(false);
const importando = ref(false);

const catalogosPendientes = computed(() => {
    const catalogos = resultado.value?.catalogos ?? {};
    return [
        { label: "Categorias", items: catalogos.categorias ?? [] },
        { label: "Marcas", items: catalogos.marcas ?? [] },
        { label: "Modelos", items: catalogos.modelos ?? [] },
        { label: "Unidades", items: catalogos.unidades ?? [] },
    ].filter((grupo) => grupo.items.length > 0);
});

const resumenItems = computed(() => [
    { label: "Nuevos", valor: resultado.value?.creados ?? 0 },
    { label: "Actualizados", valor: resultado.value?.actualizados ?? 0 },
    { label: "Stock", valor: resultado.value?.stocks_actualizados ?? 0 },
    { label: "Errores", valor: resultado.value?.errores?.length ?? 0 },
]);

function seleccionarArchivo(event) {
    archivo.value = event.target.files?.[0] ?? null;
    resultado.value = null;
}

async function descargarPlantilla() {
    descargando.value = true;
    try {
        const { data } = await http.get("/api/productos/importacion/plantilla", {
            responseType: "blob",
        });
        const url = URL.createObjectURL(data);
        const link = document.createElement("a");
        link.href = url;
        link.download = "plantilla_importacion_productos.xlsx";
        link.click();
        URL.revokeObjectURL(url);
    } catch {
        toastError("No se pudo descargar la plantilla");
    } finally {
        descargando.value = false;
    }
}

async function previsualizar() {
    if (!archivo.value) return;
    previsualizando.value = true;
    try {
        const { data } = await http.post(
            "/api/productos/importacion/previsualizar",
            formData(),
            { headers: { "Content-Type": "multipart/form-data" } },
        );
        resultado.value = data;
    } catch (e) {
        resultado.value = e.response?.data ?? null;
        toastError(e.response?.data?.message ?? "No se pudo previsualizar el archivo");
    } finally {
        previsualizando.value = false;
    }
}

async function importar() {
    if (!archivo.value || !resultado.value?.valido) return;
    importando.value = true;
    try {
        const { data } = await http.post("/api/productos/importacion", formData(), {
            headers: { "Content-Type": "multipart/form-data" },
        });
        resultado.value = data;
        toastSuccess("Productos importados correctamente");
    } catch (e) {
        resultado.value = e.response?.data ?? resultado.value;
        toastError(e.response?.data?.message ?? "No se pudo importar");
    } finally {
        importando.value = false;
    }
}

function formData() {
    const fd = new FormData();
    fd.append("archivo", archivo.value);
    return fd;
}
</script>
