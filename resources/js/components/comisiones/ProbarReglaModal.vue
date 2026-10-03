<template>
    <BaseModal :open="open" title="Probar regla" :subtitle="esquemaNombre" size="md" @close="emit('close')">
        <form id="form-probar" class="space-y-4" @submit.prevent="probar">
            <BaseSearchSelect
                v-model="form.producto_id"
                label="Producto"
                placeholder="Buscar por nombre o código…"
                :fetcher="buscarProductos"
                :selected-item="producto"
                :sub-label-key="(p) => [p.codigo, p.categoria?.nombre].filter(Boolean).join(' · ')"
                required
                @selected="seleccionarProducto"
            />

            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Precio usado</label>
                    <select v-model="form.lista" class="campo" @change="aplicarPrecioDeLista">
                        <option v-for="lista in LISTAS_PRECIO" :key="lista" :value="lista">{{ etiquetaLista(lista) }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Precio por pieza</label>
                    <input v-model.number="form.precio" type="number" min="0" step="0.01" class="campo" required />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cantidad</label>
                    <input v-model.number="form.cantidad" type="number" min="0.001" step="0.001" class="campo" required />
                </div>
            </div>

            <div v-if="resultado" class="rounded-xl border p-4" :class="resultado.monto > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50'">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Comisión</p>
                <p class="text-2xl font-bold" :class="resultado.monto > 0 ? 'text-emerald-700' : 'text-slate-500'">{{ dinero(resultado.monto) }}</p>
                <p class="mt-1 text-sm text-slate-700">{{ MOTIVOS_PRUEBA[resultado.motivo] }}</p>
                <dl v-if="resultado.regla" class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-600">
                    <dt class="font-semibold">Regla</dt>
                    <dd>{{ resultado.regla.referencia_nombre }}<span v-if="rango"> ({{ rango }})</span></dd>
                    <template v-if="resultado.motivo === 'aplicada'">
                        <dt class="font-semibold">Cálculo</dt>
                        <dd>{{ describirValor(resultado.tipo, resultado.tasa) }}</dd>
                        <dt class="font-semibold">Base ({{ resultado.base }})</dt>
                        <dd>{{ dinero(resultado.base_calculo) }}</dd>
                    </template>
                </dl>
                <p v-if="resultado.base === 'utilidad'" class="mt-2 text-xs text-slate-500">La utilidad usa el costo actual del producto.</p>
            </div>
        </form>

        <template #footer>
            <div class="flex justify-end gap-3">
                <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="emit('close')">
                    Cerrar
                </button>
                <button
                    type="submit"
                    form="form-probar"
                    class="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                    :disabled="probando || !form.producto_id"
                >
                    <Loader2 v-if="probando" class="h-4 w-4 animate-spin" />
                    Calcular
                </button>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Loader2 } from "lucide-vue-next";
import BaseModal from "@/components/ui/BaseModal.vue";
import BaseSearchSelect from "@/components/ui/BaseSearchSelect.vue";
import http from "@/lib/http";
import { toastError } from "@/lib/alert";
import { LISTAS_PRECIO, MOTIVOS_PRUEBA, describirRango, describirValor, dinero, etiquetaLista } from "@/helpers/comisiones";

const props = defineProps({
    open: { type: Boolean, default: false },
    esquemaId: { type: Number, default: null },
    esquemaNombre: { type: String, default: "" },
});

const emit = defineEmits(["close"]);

const CAMPO_LISTA = { "P.Venta": "precio_venta", P1: "precio1", P2: "precio2", P3: "precio3", P4: "precio4", P5: "precio5" };

const form = ref({ producto_id: null, lista: "P.Venta", precio: 0, cantidad: 1 });
const producto = ref(null);
const resultado = ref(null);
const probando = ref(false);

const rango = computed(() => (resultado.value?.regla ? describirRango(resultado.value.regla) : null));

watch(
    () => props.open,
    (abierto) => {
        if (abierto) resultado.value = null;
    },
);

watch(form, () => (resultado.value = null), { deep: true });

async function buscarProductos(q) {
    const { data } = await http.get("/api/comisiones/catalogo/productos", { params: { q } });
    return data;
}

function seleccionarProducto(p) {
    producto.value = p;
    aplicarPrecioDeLista();
}

function aplicarPrecioDeLista() {
    const campo = CAMPO_LISTA[form.value.lista];
    if (campo && producto.value?.[campo] != null) {
        form.value.precio = Number(producto.value[campo]);
    }
}

async function probar() {
    probando.value = true;
    try {
        const { data } = await http.post(`/api/comisiones/esquemas/${props.esquemaId}/probar`, form.value);
        resultado.value = data;
    } catch (e) {
        toastError(e?.response?.data?.message || "No se pudo calcular.");
    } finally {
        probando.value = false;
    }
}
</script>

<style scoped>
@reference "../../../css/app.css";

.campo {
    @apply w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100;
}
</style>
