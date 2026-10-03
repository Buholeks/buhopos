<template>
    <BaseModal :open="open" :title="esquema ? 'Datos del esquema' : 'Nuevo esquema de comisión'" size="lg" @close="emit('close')">
        <form id="form-esquema" class="space-y-6" @submit.prevent="guardar">
            <!-- Nombre -->
            <div>
                <label for="esquema-nombre" class="etiqueta">Nombre</label>
                <input
                    id="esquema-nombre"
                    v-model="form.nombre"
                    type="text"
                    maxlength="100"
                    placeholder="Ej. Vendedor de piso"
                    class="campo"
                    :class="errores.nombre ? 'border-red-400 ring-4 ring-red-50' : ''"
                    required
                />
                <p v-if="errores.nombre" class="mt-1 text-xs text-red-600">{{ errores.nombre }}</p>
            </div>

            <!-- Para quién -->
            <fieldset>
                <legend class="etiqueta">¿A quién se le paga?</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    <OpcionTarjeta
                        v-for="b in BENEFICIARIOS"
                        :key="b.valor"
                        :activa="form.beneficiario === b.valor"
                        :bloqueada="!!esquema && form.beneficiario !== b.valor"
                        :icono="b.icono"
                        :titulo="b.label"
                        :texto="b.ayuda"
                        @elegir="!esquema && (form.beneficiario = b.valor)"
                    />
                </div>
            </fieldset>

            <!-- Modo -->
            <fieldset>
                <legend class="etiqueta">¿Cómo se calcula?</legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    <OpcionTarjeta
                        v-for="m in MODOS_ESQUEMA"
                        :key="m.valor"
                        :activa="form.modo === m.valor"
                        :bloqueada="!!esquema && form.modo !== m.valor"
                        :icono="m.icono"
                        :titulo="m.label"
                        :texto="m.ayuda"
                        @elegir="!esquema && (form.modo = m.valor)"
                    />
                </div>
                <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                    <Lock class="h-3.5 w-3.5" />
                    A quién se le paga y cómo se calcula no se cambian después. Si lo necesitas, duplica el esquema.
                </p>
            </fieldset>

            <!-- Descripción -->
            <div>
                <label for="esquema-descripcion" class="etiqueta">Descripción <span class="font-normal normal-case tracking-normal text-slate-400">(opcional)</span></label>
                <input id="esquema-descripcion" v-model="form.descripcion" type="text" maxlength="255" placeholder="Para quién es o cuándo se usa" class="campo" />
            </div>

            <label v-if="esquema" class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3">
                <input v-model="form.activo" type="checkbox" class="mt-0.5 h-4 w-4 accent-emerald-600" />
                <span>
                    <span class="block text-sm font-semibold text-slate-800">Esquema activo</span>
                    <span class="block text-xs text-slate-500">Si lo desactivas, los usuarios asignados dejan de generar comisión con él.</span>
                </span>
            </label>
        </form>

        <template #footer>
            <div class="flex justify-end gap-3">
                <button type="button" class="boton-secundario" @click="emit('close')">Cancelar</button>
                <button type="submit" form="form-esquema" class="boton-primario" :disabled="guardando">
                    <Loader2 v-if="guardando" class="h-4 w-4 animate-spin" />
                    {{ esquema ? 'Guardar cambios' : 'Crear esquema' }}
                </button>
            </div>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, watch } from "vue";
import { Loader2, Lock } from "lucide-vue-next";
import BaseModal from "@/components/ui/BaseModal.vue";
import OpcionTarjeta from "@/components/comisiones/OpcionTarjeta.vue";
import http from "@/lib/http";
import { toastError } from "@/lib/alert";
import { BENEFICIARIOS, MODOS_ESQUEMA } from "@/helpers/comisiones";

const props = defineProps({
    open: { type: Boolean, default: false },
    esquema: { type: Object, default: null }, // null = nuevo
});

const emit = defineEmits(["close", "guardado"]);

const form = ref(vacio());
const errores = ref({});
const guardando = ref(false);

function vacio() {
    return { nombre: "", beneficiario: "vendedor", modo: "categoria", descripcion: "", activo: true };
}

watch(
    () => props.open,
    (abierto) => {
        if (!abierto) return;
        errores.value = {};
        const e = props.esquema;
        form.value = e
            ? { nombre: e.nombre, beneficiario: e.beneficiario, modo: e.modo, descripcion: e.descripcion ?? "", activo: e.activo }
            : vacio();
    },
);

async function guardar() {
    guardando.value = true;
    errores.value = {};
    try {
        const { data } = props.esquema
            ? await http.put(`/api/comisiones/esquemas/${props.esquema.id}`, form.value)
            : await http.post("/api/comisiones/esquemas", form.value);
        emit("guardado", data);
    } catch (e) {
        errores.value = Object.fromEntries(Object.entries(e?.response?.data?.errors ?? {}).map(([k, v]) => [k, v[0]]));
        if (!errores.value.nombre) toastError(Object.values(errores.value)[0] || e?.response?.data?.message || "No se pudo guardar el esquema.");
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
.campo {
    @apply w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100;
}
.boton-primario {
    @apply flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50;
}
.boton-secundario {
    @apply rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50;
}
</style>
