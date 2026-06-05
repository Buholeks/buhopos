<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mostrar"
                class="fixed inset-0 z-100 flex items-center justify-center bg-slate-900/40 p-4"
                @mousedown.self="emit('cerrar')"
            >
                <div
                    class="w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                >
                    <!-- Header -->
                    <div class="relative flex items-start gap-3 px-6 pt-6">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600"
                        >
                            <Package class="h-5 w-5" />
                        </div>

                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    editando
                                        ? "Editar producto"
                                        : "Nuevo producto"
                                }}
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Completa los datos del producto
                            </p>
                        </div>

                        <button
                            @click="emit('cerrar')"
                            class="absolute right-4 top-4 rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            title="Cerrar"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="mt-4 border-b border-slate-200 px-6">
                        <div class="flex gap-2">
                            <button
                                v-for="tab in TABS"
                                :key="tab.id"
                                @click="setTab(tab.id)"
                                class="inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium"
                                :class="
                                    tabActivo === tab.id
                                        ? 'border-emerald-600 text-emerald-700'
                                        : 'border-transparent text-slate-500 hover:text-slate-700'
                                "
                            >
                                <component :is="tab.icon" class="h-4 w-4" />
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                        <!-- GENERAL -->
                        <div
                            v-show="tabActivo === 'general'"
                            class="grid grid-cols-1 gap-4 md:grid-cols-2"
                        >
                            <div class="md:col-span-2">
                                <BaseInput
                                    v-model.trim="formProxy.nombre"
                                    label="Nombre"
                                    required
                                    placeholder="Nombre del producto"
                                    :error="err?.nombre"
                                    autofocus
                                />
                                <p
                                    v-if="err?.nombre"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ err.nombre }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="text-sm font-medium text-slate-700"
                                >
                                    Código / SKU
                                </label>
                                <div class="mt-1 flex">
                                    <BaseInput
                                        v-model.trim="formProxy.codigo"
                                        placeholder="0001"
                                          :error="err?.codigo"
                                          autofocus
                                    />
                                </div>
                                <p
                                    v-if="err?.codigo"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ err.codigo }}
                                </p>
                            </div>

                            <div>
                                <BaseSearchSelect
                                    v-model.number="formProxy.unidad_medida_id"
                                    :items="catalogos?.unidades ?? []"
                                    label="Unidad de medida"
                                    placeholder="Buscar unidad…"
                                    :label-key="
                                        (u) => `${u.nombre} (${u.abreviatura})`
                                    "
                                    value-key="id"
                                    :disabled="
                                        cargando || !catalogos?.unidades?.length
                                    "
                                />
                            </div>

                            <div>
                                <BaseSearchSelect
                                    v-model.number="formProxy.categoria_id"
                                    :items="catalogos?.categorias ?? []"
                                    label="Categoría"
                                    placeholder="Buscar categoría…"
                                    :label-key="(c) => c.nombre"
                                    value-key="id"
                                    :disabled="
                                        cargando ||
                                        !catalogos?.categorias?.length
                                    "
                                />
                            </div>

                            <div>
                                <BaseSearchSelect
                                    v-model.number="formProxy.marca_id"
                                    :items="catalogos?.marcas ?? []"
                                    label="Marca"
                                    placeholder="Buscar marca…"
                                    :label-key="(m) => m.nombre"
                                    value-key="id"
                                    :disabled="
                                        cargando || !catalogos?.marcas?.length
                                    "
                                    @change="formProxy.modelo_id = ''"
                                />
                            </div>

                            <div>
                                <BaseSearchSelect
                                    v-model.number="formProxy.modelo_id"
                                    :items="modelosDeMarca ?? []"
                                    label="Modelo"
                                    placeholder="Buscar modelo…"
                                    :label-key="(m) => m.nombre"
                                    value-key="id"
                                    :disabled="
                                        !formProxy.marca_id ||
                                        !modelosDeMarca?.length
                                    "
                                    hint="Primero selecciona una marca."
                                    :error="err?.modelo_id"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label
                                    class="text-sm font-medium text-slate-700"
                                    >Descripción</label
                                >
                                <textarea
                                    v-model="formProxy.descripcion"
                                    rows="2"
                                    placeholder="Descripción del producto…"
                                    class="mt-1 w-full resize-y rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label
                                    class="text-sm font-medium text-slate-700"
                                    >Imagen de referencia</label
                                >
                                <div class="mt-2 flex items-center gap-3">
                                    <div
                                        class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
                                    >
                                        <img
                                            v-if="
                                                formProxy.imagenPreview ||
                                                formProxy.imagenActualUrl
                                            "
                                            :src="
                                                formProxy.imagenPreview ||
                                                formProxy.imagenActualUrl
                                            "
                                            alt="preview"
                                            class="h-full w-full object-contain"
                                        />
                                        <span
                                            v-else
                                            class="text-xs text-slate-400"
                                            >Sin imagen</span
                                        >
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <input
                                            ref="inputImagen"
                                            type="file"
                                            accept="image/*"
                                            class="hidden"
                                            @change="onFileChange"
                                        />
                                        <button
                                            type="button"
                                            @click="inputImagen?.click()"
                                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            {{
                                                formProxy.imagenActualUrl ||
                                                formProxy.imagenPreview
                                                    ? "Cambiar"
                                                    : "Subir imagen"
                                            }}
                                        </button>
                                        <button
                                            v-if="
                                                formProxy.imagenActualUrl ||
                                                formProxy.imagenPreview
                                            "
                                            type="button"
                                            @click="emit('quitar-imagen')"
                                            class="inline-flex items-center justify-center rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
                                        >
                                            Quitar
                                        </button>
                                        <p class="text-xs text-slate-500">
                                            JPG, PNG o WebP · Máx. 2MB
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Toggles: Estado + Control por serie ────────────────────── -->
                            <div
                                class="md:col-span-2 grid grid-cols-1 gap-3 md:grid-cols-2"
                            >
                                <!-- Estado activo/inactivo -->
                                <div
                                    class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3"
                                >
                                    <button
                                        type="button"
                                        @click="
                                            formProxy.activo = !formProxy.activo
                                        "
                                        class="relative h-6 w-11 flex-shrink-0 rounded-full transition"
                                        :class="
                                            formProxy.activo
                                                ? 'bg-emerald-600'
                                                : 'bg-slate-300'
                                        "
                                    >
                                        <span
                                            class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition"
                                            :class="
                                                formProxy.activo
                                                    ? 'left-5'
                                                    : 'left-0.5'
                                            "
                                        />
                                    </button>
                                    <div>
                                        <p
                                            class="text-sm font-medium text-slate-700"
                                        >
                                            Estado
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                formProxy.activo
                                                    ? "Activo"
                                                    : "Inactivo"
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Control por IMEI / serie -->
                                <div
                                    class="flex items-center gap-3 rounded-xl border px-4 py-3 transition"
                                    :class="
                                        formProxy.tiene_series
                                            ? 'border-blue-200 bg-blue-50'
                                            : 'border-slate-200 bg-slate-50'
                                    "
                                >
                                    <button
                                        type="button"
                                        @click="
                                            formProxy.tiene_series =
                                                !formProxy.tiene_series
                                        "
                                        class="relative h-6 w-11 flex-shrink-0 rounded-full transition"
                                        :class="
                                            formProxy.tiene_series
                                                ? 'bg-blue-600'
                                                : 'bg-slate-300'
                                        "
                                    >
                                        <span
                                            class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition"
                                            :class="
                                                formProxy.tiene_series
                                                    ? 'left-5'
                                                    : 'left-0.5'
                                            "
                                        />
                                    </button>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <Smartphone
                                                class="h-3.5 w-3.5 text-blue-500"
                                            />
                                            <p
                                                class="text-sm font-medium text-slate-700"
                                            >
                                                Control por IMEI / serie
                                            </p>
                                        </div>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                formProxy.tiene_series
                                                    ? "Cada unidad se identifica individualmente"
                                                    : "Sin control por número de serie"
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Aviso cuando tiene_series está activo -->
                            <div
                                v-if="formProxy.tiene_series"
                                class="md:col-span-2 flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3"
                            >
                                <AlertTriangle
                                    class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-400"
                                />
                                <p class="text-xs text-blue-700">
                                    Al vender este producto en el POS se
                                    requerirá seleccionar o escanear el IMEI /
                                    número de serie de cada unidad. Registra las
                                    series al recibir mercancía desde
                                    <strong>Inventario → Series</strong>.
                                </p>
                            </div>

                            <div>
                                <label
                                    class="text-sm font-medium text-slate-700"
                                    >Stock mínimo</label
                                >
                                <BaseInput
                                    v-model.number="formProxy.stock_minimo"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="0"
                                      />
                            </div>

                            <div>
                                <label
                                    class="text-sm font-medium text-slate-700"
                                    >Peso (kg)</label
                                >
                                <BaseInput  
                                    v-model.number="formProxy.peso"
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    placeholder="0.000"
                                   
                                />
                            </div>
                        </div>

                        <!-- PRECIOS -->
                        <div
                            v-show="tabActivo === 'precios'"
                            class="grid grid-cols-1 gap-4 md:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="text-sm font-medium text-slate-700"
                                >
                                    Precio costo
                                    <span class="text-red-600">*</span>
                                </label>
                                <div
                                    class="mt-1 flex items-center rounded-lg border focus-within:ring-4"
                                    :class="
                                        err?.precio_costo
                                            ? 'border-red-300 focus-within:border-red-500 focus-within:ring-red-100'
                                            : 'border-slate-200 focus-within:border-emerald-500 focus-within:ring-emerald-100'
                                    "
                                >
                                    <span class="px-3 text-sm text-slate-500"
                                        >$</span
                                    >
                                    <input
                                        v-model.number="formProxy.precio_costo"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full rounded-r-lg px-3 py-2 text-sm outline-none"
                                    />
                                </div>
                                <p
                                    v-if="err?.precio_costo"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ err.precio_costo }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="text-sm font-medium text-slate-700"
                                >
                                    Precio venta
                                    <span class="text-red-600">*</span>
                                </label>
                                <div
                                    class="mt-1 flex items-center rounded-lg border focus-within:ring-4"
                                    :class="
                                        err?.precio_venta
                                            ? 'border-red-300 focus-within:border-red-500 focus-within:ring-red-100'
                                            : 'border-slate-200 focus-within:border-emerald-500 focus-within:ring-emerald-100'
                                    "
                                >
                                    <span class="px-3 text-sm text-slate-500"
                                        >$</span
                                    >
                                    <input
                                        v-model.number="formProxy.precio_venta"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full rounded-r-lg px-3 py-2 text-sm outline-none"
                                    />
                                </div>
                                <p
                                    v-if="err?.precio_venta"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ err.precio_venta }}
                                </p>
                            </div>

                            <div
                                class="md:col-span-2 mt-2 flex items-center justify-between border-t border-slate-200 pt-3"
                            >
                                <span
                                    class="text-xs font-semibold uppercase tracking-wider text-slate-600"
                                >
                                    Niveles de precio
                                </span>
                                <span class="text-xs text-slate-500"
                                    >Dejar en blanco para no usar</span
                                >
                            </div>

                            <div v-for="n in 5" :key="n">
                                <label
                                    class="text-sm font-medium text-slate-700"
                                    >Precio {{ n }}</label
                                >
                                <div
                                    class="mt-1 flex items-center rounded-lg border border-slate-200 focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100"
                                >
                                    <span class="px-3 text-sm text-slate-500"
                                        >$</span
                                    >
                                    <input
                                        v-model.number="formProxy[`precio${n}`]"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="—"
                                        class="w-full rounded-r-lg px-3 py-2 text-sm outline-none"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="
                                    formProxy.precio_costo > 0 &&
                                    formProxy.precio_venta > 0
                                "
                                class="md:col-span-2 flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3"
                            >
                                <span class="text-xs text-slate-500"
                                    >Margen bruto</span
                                >
                                <span
                                    class="font-mono text-sm font-semibold"
                                    :class="
                                        margen >= 0
                                            ? 'text-emerald-700'
                                            : 'text-red-700'
                                    "
                                >
                                    {{
                                        formatPrecio(
                                            formProxy.precio_venta -
                                                formProxy.precio_costo,
                                        )
                                    }}
                                    ({{ Number(margen).toFixed(1) }}%)
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4"
                    >
                        <button
                            @click="emit('cerrar')"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        >
                            <XCircle class="h-4 w-4" />
                            Cancelar
                        </button>

                        <button
                            @click="emit('enviar')"
                            :disabled="cargando"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                        >
                            <Loader2
                                v-if="cargando"
                                class="h-4 w-4 animate-spin"
                            />
                            <Save v-else class="h-4 w-4" />
                            {{
                                editando ? "Guardar cambios" : "Crear producto"
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, ref } from "vue";
import BaseInput from "@/components/ui/BaseInput.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import {
    Package,
    X,
    RefreshCcw,
    Loader2,
    Save,
    XCircle,
    Info,
    DollarSign,
    Smartphone, // ← nuevo
    AlertTriangle, // ← nuevo
} from "lucide-vue-next";

const props = defineProps({
    mostrar: { type: Boolean, default: false },
    editando: { type: Boolean, default: false },
    cargando: { type: Boolean, default: false },

    TABS: { type: Array, default: () => [] },
    tabActivo: { type: String, default: "general" },

    form: { type: Object, required: true },
    err: { type: Object, default: () => ({}) },
    catalogos: { type: Object, default: () => ({}) },
    modelosDeMarca: { type: Array, default: () => [] },

    margen: { type: Number, default: 0 },
    formatPrecio: { type: Function, required: true },
});

const emit = defineEmits([
    "cerrar",
    "enviar",
    "generar-codigo",
    "imagen-change",
    "quitar-imagen",
    "update:tabActivo",
    "update:form",
]);

const inputImagen = ref(null);

const TABS = computed(() => {
    const fallback = { general: Info, precios: DollarSign };
    return (props.TABS ?? []).map((t) => ({
        ...t,
        icon: t.icon ?? fallback[t.id] ?? Info,
    }));
});

const formProxy = computed({
    get: () => props.form,
    set: (v) => emit("update:form", v),
});

function setTab(id) {
    emit("update:tabActivo", id);
}

function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    emit("imagen-change", file);
    if (inputImagen.value) inputImagen.value.value = "";
}
</script>
