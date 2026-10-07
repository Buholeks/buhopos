<template>
    <BaseModal :open="open" :title="titulo" :subtitle="esquema?.nombre ?? ''" size="lg" @close="emit('close')">
        <form id="form-grupo" class="space-y-6" @submit.prevent="guardar">
            <!-- 1. Qué -->
            <section v-if="aplicaA !== 'general'">
                <BaseSearchSelect
                    v-if="aplicaA === 'categoria'"
                    v-model="form.referencia_id"
                    label="Categoría"
                    placeholder="Buscar categoría…"
                    :fetcher="buscarCategorias"
                    :selected-item="referenciaSeleccionada"
                    :sub-label-key="(c) => (c.padre ? `Dentro de ${c.padre.nombre}` : '')"
                    :error="errores.referencia_id"
                    hint="También aplica a sus subcategorías que no tengan comisión propia."
                    required
                    @selected="referenciaSeleccionada = $event"
                />
                <BaseSearchSelect
                    v-else
                    v-model="form.referencia_id"
                    label="Producto"
                    placeholder="Buscar por nombre o código…"
                    :fetcher="buscarProductos"
                    :selected-item="referenciaSeleccionada"
                    :sub-label-key="(p) => [p.codigo, p.categoria?.nombre].filter(Boolean).join(' · ')"
                    :error="errores.referencia_id"
                    required
                    @selected="referenciaSeleccionada = $event"
                />
            </section>
            <p v-else-if="esquema?.modo !== 'general'" class="flex items-start gap-2 rounded-xl bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                <Info class="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                Se usa para los productos que no tienen comisión de {{ esquema?.modo === 'producto' ? 'producto' : 'categoría' }} en este esquema.
            </p>
            <p v-if="errores.referencia_id && aplicaA === 'general'" class="text-xs text-red-600">{{ errores.referencia_id }}</p>

            <!-- 2. Cómo se calcula -->
            <section class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="etiqueta">Tipo de comisión</p>
                    <Segmentado v-model="form.tipo" :opciones="TIPOS" etiqueta="Tipo de comisión" />
                </div>
                <div>
                    <p class="etiqueta">Calcular sobre</p>
                    <Segmentado v-model="form.base" :opciones="BASES" etiqueta="Calcular sobre" :class="form.tipo !== 'porcentaje' ? 'opacity-50' : ''" />
                    <p v-if="form.tipo !== 'porcentaje'" class="mt-1 text-xs text-slate-400">El monto por pieza no depende de la base.</p>
                </div>
            </section>

            <!-- 3. Cuánto -->
            <section>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="etiqueta mb-0">Cuánto paga</p>
                    <div class="w-56">
                        <Segmentado v-model="form.forma" :opciones="FORMAS" etiqueta="Forma de pago" />
                    </div>
                </div>

                <!-- Valor único -->
                <div v-if="form.forma === 'unico'" class="rounded-xl border border-slate-200 p-4">
                    <label for="grupo-valor" class="mb-1 block text-sm font-semibold text-slate-700">
                        {{ form.tipo === 'porcentaje' ? 'Porcentaje' : 'Monto por pieza vendida' }}
                    </label>
                    <CampoValor
                        id="grupo-valor"
                        v-model="form.valor"
                        :prefijo="form.tipo === 'porcentaje' ? '' : '$'"
                        :sufijo="form.tipo === 'porcentaje' ? '%' : 'por pieza'"
                        :error="errores.valor"
                        class="max-w-[220px]"
                    />
                </div>

                <!-- Rangos -->
                <div v-else class="overflow-hidden rounded-xl border border-slate-200">
                    <div class="grid grid-cols-[1fr_auto_1fr_1fr_36px] items-center gap-2 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <span>Precio desde</span>
                        <span></span>
                        <span>Hasta</span>
                        <span>{{ form.tipo === 'porcentaje' ? 'Porcentaje' : 'Por pieza' }}</span>
                        <span></span>
                    </div>
                    <div
                        v-for="(r, i) in form.rangos"
                        :key="i"
                        class="grid grid-cols-[1fr_auto_1fr_1fr_36px] items-start gap-2 border-t border-slate-100 px-3 py-2"
                    >
                        <CampoValor v-model="r.desde" prefijo="$" compacto :error="errores[`rangos.${i}.desde`]" />
                        <ArrowRight class="mt-2 h-4 w-4 text-slate-300" />
                        <div>
                            <p v-if="i < form.rangos.length - 1" class="py-1.5 text-sm tabular-nums text-slate-500">{{ hastaDe(i) }}</p>
                            <CampoValor v-else v-model="form.hasta_final" prefijo="$" placeholder="Sin límite" compacto :error="errores.hasta_final" />
                        </div>
                        <CampoValor
                            v-model="r.valor"
                            :prefijo="form.tipo === 'porcentaje' ? '' : '$'"
                            :sufijo="form.tipo === 'porcentaje' ? '%' : ''"
                            compacto
                            :error="errores[`rangos.${i}.valor`]"
                        />
                        <button
                            type="button"
                            class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-500 disabled:invisible"
                            :disabled="form.rangos.length === 1"
                            :aria-label="`Quitar rango ${i + 1}`"
                            @click="form.rangos.splice(i, 1)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-3 py-2">
                        <button type="button" class="flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-semibold text-emerald-700 hover:bg-emerald-50" @click="agregarRango">
                            <Plus class="h-4 w-4" /> Agregar rango
                        </button>
                        <span class="text-xs text-slate-400">Se compara el precio por pieza. El "hasta" se calcula solo.</span>
                    </div>
                    <p v-if="errores.rangos" class="px-3 pb-2 text-xs text-red-600">{{ errores.rangos }}</p>

                    <div class="border-t border-slate-100 bg-slate-50/60 px-3 py-3">
                        <template v-if="aplicaA !== 'general'">
                            <p class="mb-1.5 text-sm font-semibold text-slate-700">Si el precio queda fuera de los rangos</p>
                            <Segmentado v-model="form.fuera_de_rango" :opciones="FUERA_DE_RANGO" etiqueta="Fuera de los rangos" />
                        </template>
                        <p v-else class="text-xs text-slate-500">Fuera de los rangos no hay comisión.</p>
                    </div>
                </div>
            </section>

            <!-- 4. Precios -->
            <section>
                <div class="mb-2 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <p class="etiqueta mb-0">Precios que pagan comisión</p>
                        <p class="text-xs text-slate-500">
                            {{ form.forma === 'unico' ? 'Apaga los precios que no deben pagar comisión.' : 'Un precio apagado no paga en ningún rango.' }}
                        </p>
                    </div>
                    <label v-if="form.forma === 'unico'" class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                        <input v-model="valorPorPrecio" type="checkbox" class="h-4 w-4 accent-emerald-600" />
                        Valor distinto por precio
                    </label>
                </div>

                <!-- Encendido / apagado -->
                <div v-if="form.forma === 'rangos' || !valorPorPrecio" class="flex flex-wrap gap-2">
                    <button
                        v-for="fila in precios"
                        :key="fila.lista"
                        type="button"
                        role="switch"
                        :aria-checked="fila.activo"
                        class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition"
                        :class="fila.activo ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-400 line-through'"
                        @click="fila.activo = !fila.activo"
                    >
                        <span class="relative h-4 w-7 rounded-full transition" :class="fila.activo ? 'bg-emerald-500' : 'bg-slate-300'">
                            <span class="absolute top-0.5 h-3 w-3 rounded-full bg-white transition-all" :class="fila.activo ? 'left-3.5' : 'left-0.5'"></span>
                        </span>
                        {{ etiquetaLista(fila.lista) }}
                    </button>
                </div>

                <!-- Valor por precio -->
                <div v-else class="overflow-hidden rounded-xl border border-slate-200">
                    <div
                        v-for="(fila, i) in precios"
                        :key="fila.lista"
                        class="grid grid-cols-[110px_1fr_140px] items-start gap-3 border-b border-slate-100 px-3 py-2 last:border-0"
                        :class="fila.activo ? '' : 'bg-slate-50'"
                    >
                        <label class="flex cursor-pointer items-center gap-2 py-1.5 text-sm font-semibold" :class="fila.activo ? 'text-slate-800' : 'text-slate-400'">
                            <input v-model="fila.activo" type="checkbox" class="h-4 w-4 accent-emerald-600" />
                            {{ etiquetaLista(fila.lista) }}
                        </label>
                        <Segmentado v-if="fila.activo" v-model="fila.tipo" :opciones="TIPOS_CORTOS" :etiqueta="`Tipo en ${etiquetaLista(fila.lista)}`" />
                        <p v-else class="py-1.5 text-sm text-slate-400">Sin comisión</p>
                        <CampoValor
                            v-if="fila.activo"
                            v-model="fila.valor"
                            :prefijo="fila.tipo === 'porcentaje' ? '' : '$'"
                            :sufijo="fila.tipo === 'porcentaje' ? '%' : ''"
                            compacto
                            :error="errores[`precios.${i}.valor`]"
                        />
                    </div>
                </div>
            </section>
        </form>

        <template #footer>
            <div class="flex justify-end gap-3">
                <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="emit('close')">
                    Cancelar
                </button>
                <button
                    type="submit"
                    form="form-grupo"
                    class="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                    :disabled="guardando"
                >
                    <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                    {{ guardando ? 'Guardando…' : 'Guardar' }}
                </button>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { ArrowRight, Info, Loader2, Percent, Plus, Tag, X } from "lucide-vue-next";
import BaseModal from "@/components/ui/BaseModal.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import CampoValor from "@/components/comisiones/CampoValor.vue";
import Segmentado from "@/components/comisiones/Segmentado.vue";
import http from "@/lib/http";
import { toastError } from "@/lib/alert";
import { LISTAS_PRECIO, dineroCorto, etiquetaLista } from "@/helpers/comisiones";

const props = defineProps({
    open: { type: Boolean, default: false },
    esquema: { type: Object, default: null },
    aplicaA: { type: String, default: "general" }, // categoria | producto | general
    grupo: { type: Object, default: null },       // null = nuevo
});

const emit = defineEmits(["close", "guardado"]);

const TIPOS = [
    { valor: "porcentaje", label: "Porcentaje", icono: Percent },
    { valor: "fijo_por_pieza", label: "Monto por pieza", icono: Tag },
];
const TIPOS_CORTOS = [
    { valor: "porcentaje", label: "%" },
    { valor: "fijo_por_pieza", label: "$ / pza" },
];
const BASES = [
    { valor: "venta", label: "Venta" },
    { valor: "utilidad", label: "Utilidad" },
];
const FORMAS = [
    { valor: "unico", label: "Un valor" },
    { valor: "rangos", label: "Por rangos" },
];
const FUERA_DE_RANGO = [
    { valor: "usar_siguiente", label: "Usar lo demás" },
    { valor: "sin_comision", label: "Sin comisión" },
];

const CAMPOS_CON_ERROR = [/^referencia_id$/, /^valor$/, /^hasta_final$/, /^rangos$/, /^rangos\.\d+\.(desde|valor)$/, /^precios\.\d+\.valor$/];

const form = ref(formVacio());
const precios = ref(preciosVacios());
const valorPorPrecio = ref(false);
const referenciaSeleccionada = ref(null);
const errores = ref({});
const guardando = ref(false);

const titulo = computed(() => {
    if (props.aplicaA === "general") return props.esquema?.modo === "general" ? "Comisión" : "Comisión para lo demás";
    const que = props.aplicaA === "producto" ? "producto" : "categoría";
    return props.grupo ? `Editar ${que}` : `Agregar ${que}`;
});

function formVacio() {
    return {
        referencia_id: null,
        base: "venta",
        tipo: "porcentaje",
        forma: "unico",
        valor: 0,
        rangos: [{ desde: 0, valor: 0 }],
        hasta_final: "",
        fuera_de_rango: "usar_siguiente",
    };
}

function preciosVacios() {
    return LISTAS_PRECIO.map((lista) => ({ lista, activo: true, tipo: "porcentaje", valor: null }));
}

watch(
    () => props.open,
    (abierto) => {
        if (!abierto) return;
        errores.value = {};
        const g = props.grupo;
        if (!g) {
            form.value = formVacio();
            precios.value = preciosVacios();
            valorPorPrecio.value = false;
            referenciaSeleccionada.value = null;
            return;
        }
        form.value = {
            referencia_id: g.referencia_id,
            base: g.base,
            tipo: g.tipo,
            forma: g.forma,
            valor: g.valor ?? 0,
            rangos: g.rangos.length ? g.rangos.map((r) => ({ ...r })) : [{ desde: 0, valor: 0 }],
            hasta_final: g.hasta_final ?? "",
            fuera_de_rango: g.fuera_de_rango,
        };
        referenciaSeleccionada.value = g.referencia_id ? { id: g.referencia_id, nombre: g.referencia_nombre } : null;
        // Con valor por precio, las listas sin fila guardada heredan el valor general.
        valorPorPrecio.value = g.forma === "unico" && g.precios.some((p) => p.activo);
        precios.value = LISTAS_PRECIO.map((lista) => {
            const fila = g.precios.find((p) => p.lista === lista);
            return {
                lista,
                activo: fila ? fila.activo : true,
                tipo: fila?.tipo ?? g.tipo,
                valor: fila?.valor ?? g.valor ?? 0,
            };
        });
    },
);

// Al activar "valor distinto por precio", las filas parten del valor general.
watch(valorPorPrecio, (activo) => {
    if (!activo) return;
    for (const fila of precios.value) {
        if (fila.valor === null || fila.valor === undefined || fila.valor === "") {
            fila.tipo = form.value.tipo;
            fila.valor = form.value.valor;
        }
    }
});

function hastaDe(i) {
    const siguiente = Number(form.value.rangos[i + 1]?.desde);
    return Number.isFinite(siguiente) && siguiente > 0 ? dineroCorto(siguiente - 0.01) : "—";
}

function agregarRango() {
    const ultimo = form.value.rangos[form.value.rangos.length - 1];
    form.value.rangos.push({ desde: Number(ultimo?.desde || 0) + 1000, valor: ultimo?.valor ?? 0 });
}

async function buscarCategorias(q) {
    const { data } = await http.get("/api/comisiones/catalogo/categorias", { params: { q } });
    return data;
}

async function buscarProductos(q) {
    const { data } = await http.get("/api/comisiones/catalogo/productos", { params: { q } });
    return data;
}

function payload() {
    const f = form.value;
    const base = {
        aplica_a: props.aplicaA,
        referencia_id: props.aplicaA === "general" ? null : f.referencia_id,
        base: f.base,
        tipo: f.tipo,
        forma: f.forma,
    };
    if (props.grupo) base.anterior_referencia_id = props.grupo.referencia_id ?? null;

    if (f.forma === "unico") {
        return {
            ...base,
            valor: f.valor === "" ? 0 : f.valor,
            // Sin valor por precio, solo se mandan los precios apagados.
            precios: valorPorPrecio.value
                ? precios.value
                : precios.value.filter((p) => !p.activo).map((p) => ({ lista: p.lista, activo: false, tipo: f.tipo, valor: 0 })),
        };
    }
    return {
        ...base,
        rangos: f.rangos.map((r) => ({ desde: r.desde === "" ? 0 : r.desde, valor: r.valor === "" ? 0 : r.valor })),
        hasta_final: f.hasta_final === "" || f.hasta_final === null ? null : f.hasta_final,
        fuera_de_rango: f.fuera_de_rango,
        precios: precios.value.filter((p) => !p.activo).map((p) => ({ lista: p.lista, activo: false })),
    };
}

async function guardar() {
    guardando.value = true;
    errores.value = {};
    try {
        const { data } = await http.put(`/api/comisiones/esquemas/${props.esquema.id}/grupos`, payload());
        emit("guardado", data);
    } catch (e) {
        const apiErrores = e?.response?.data?.errors ?? {};
        errores.value = Object.fromEntries(Object.entries(apiErrores).map(([k, v]) => [k, v[0]]));
        // Los errores que no tienen un campo donde mostrarse van a un aviso.
        const sinCampo = Object.entries(errores.value).filter(([k]) => !CAMPOS_CON_ERROR.some((re) => re.test(k)));
        if (!Object.keys(errores.value).length || sinCampo.length) {
            toastError(sinCampo[0]?.[1] || e?.response?.data?.message || "No se pudo guardar.");
        }
    } finally {
        guardando.value = false;
    }
}
</script>

<style scoped>
@reference "../../../css/app.css";

.etiqueta {
    @apply mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500;
}
</style>
