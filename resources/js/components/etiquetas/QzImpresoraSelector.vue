<template>
    <div class="rounded-2xl border bg-white p-4" :class="conectado ? 'border-emerald-200' : 'border-slate-200'">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full" :class="conectado ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                <span class="text-sm font-bold text-slate-800">
                    {{ conectado ? "QZ Tray conectado" : "QZ Tray desconectado" }}
                </span>
            </div>
            <button v-if="!conectado" class="text-xs font-bold text-emerald-700 hover:underline" @click="intentarConectar">Conectar</button>
            <button v-else class="text-xs font-bold text-slate-500 hover:underline" @click="recargarImpresoras">Actualizar</button>
        </div>

        <template v-if="conectado">
            <label class="mt-3 block text-xs font-bold uppercase text-slate-500">Impresora en esta PC</label>
            <select v-model="impresoraLocal" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" @change="guardar">
                <option value="">— Usar diálogo del navegador —</option>
                <option v-for="p in impresoras" :key="p" :value="p">{{ p }}</option>
            </select>
            <p v-if="impresoraLocal" class="mt-1 text-xs text-emerald-700 font-semibold">✓ Impresión directa activa en esta PC</p>
            <p v-else class="mt-1 text-xs text-slate-400">Sin selección: abrirá el diálogo de impresión.</p>
        </template>

        <p v-else class="mt-2 text-xs text-slate-400">
            Instala <a href="https://qz.io" target="_blank" class="underline">QZ Tray</a> y luego registra el certificado en esta PC.
        </p>

        <div class="mt-3 border-t border-slate-100 pt-3">
            <p class="text-xs text-slate-400">¿Primera vez en esta PC? Ejecuta el instalador una sola vez:</p>
            <a href="/api/etiquetas/qztray/instalador" class="mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-center text-xs font-bold text-slate-600 hover:bg-slate-50">
                ⬇ Descargar instalador de certificado (.bat)
            </a>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { conectar, isConectado, listarImpresoras, obtenerImpresoraLocal, guardarImpresoraLocal } from "@/helpers/qzTray";

const props = defineProps({
    perfilId: { type: Number, default: null },
});
const emit = defineEmits(["cambiar"]);

const conectado = ref(false);
const impresoras = ref([]);
const impresoraLocal = ref("");

onMounted(async () => {
    conectado.value = isConectado();
    if (!conectado.value) conectado.value = await conectar();
    if (conectado.value) await recargarImpresoras();
    impresoraLocal.value = obtenerImpresoraLocal(props.perfilId) || "";
    emit("cambiar", impresoraLocal.value || null);
});

watch(() => props.perfilId, (id) => {
    if (id == null) return;
    impresoraLocal.value = obtenerImpresoraLocal(id) || "";
    emit("cambiar", impresoraLocal.value || null);
});

async function intentarConectar() {
    conectado.value = await conectar();
    if (conectado.value) await recargarImpresoras();
}

async function recargarImpresoras() {
    try {
        impresoras.value = await listarImpresoras();
    } catch {
        impresoras.value = [];
    }
}

function guardar() {
    guardarImpresoraLocal(props.perfilId, impresoraLocal.value);
    emit("cambiar", impresoraLocal.value || null);
}
</script>
