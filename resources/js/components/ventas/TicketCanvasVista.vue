<template>
    <div
        class="relative overflow-hidden bg-white text-black"
        :style="{ width: `${anchoPx}px`, height: `${altoPx}px` }"
        @click.self="$emit('seleccionar', null)"
    >
        <div
            v-for="el in elementos"
            :key="el.id"
            class="absolute flex overflow-hidden"
            :class="{
                'ring-2 ring-blue-500': editable && seleccionado === el.id,
                'cursor-move hover:ring-1 hover:ring-blue-400': editable,
            }"
            :style="estiloEl(el)"
            @pointerdown.prevent="editable && iniciarMovimiento($event, el)"
            @click.stop="editable && $emit('seleccionar', el.id)"
        >
            <template v-if="el.tipo === 'separador'">
                <div class="w-full self-center" style="border-top: 1px dashed #0f172a;"></div>
            </template>
            <template v-else-if="el.tipo === 'codigo_barras'">
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div class="w-full h-full" v-html="barcodeHtml(el)"></div>
            </template>
            <span v-else class="w-full self-center overflow-hidden">{{ valor(el) }}</span>
            <button
                v-if="editable && seleccionado === el.id"
                type="button"
                class="absolute bottom-0 right-0 h-3 w-3 cursor-se-resize bg-blue-600"
                @pointerdown.stop.prevent="iniciarRedimension($event, el)"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { crearSvgBarcode } from "@/helpers/etiquetas";

const props = defineProps({
    elementos: { type: Array, default: () => [] },
    datos: { type: Object, default: () => ({}) },
    anchoMm: { type: Number, required: true },
    altoMm: { type: Number, required: true },
    escala: { type: Number, default: 3 },
    editable: Boolean,
    seleccionado: { type: String, default: null },
});
const emit = defineEmits(["seleccionar", "cambiar"]);

const anchoPx = computed(() => props.anchoMm * props.escala);
const altoPx = computed(() => props.altoMm * props.escala);
const mm = (n) => `${Number(n) * props.escala}px`;

const estiloEl = (el) => ({
    left: mm(el.x),
    top: mm(el.y),
    width: mm(el.ancho),
    height: mm(el.alto),
    fontSize: `${Number(el.fuente || 10) * props.escala * 0.3528}px`,
    fontWeight: el.negrita ? 700 : 400,
    fontFamily: el.familia_fuente || "Arial, Helvetica, sans-serif",
    textAlign: el.alineacion === "centro" ? "center" : el.alineacion === "derecha" ? "right" : "left",
    alignItems: "center",
    lineHeight: "1.15",
});

function resolverCampo(campo) {
    if (!campo) return "";
    if (campo === "fecha") {
        return new Date(props.datos.fecha ?? Date.now()).toLocaleString("es-MX", {
            day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit",
        });
    }
    if (campo === "folio_corto") return String(props.datos.folio ?? "").replace(/\D/g, "");
    return String(campo.split(".").reduce((o, k) => o?.[k], props.datos) ?? "");
}

function valor(el) {
    return el.tipo === "texto" ? (el.texto || "") : resolverCampo(el.campo);
}

function barcodeHtml(el) {
    const val = resolverCampo(el.campo || "folio");
    const anchoPx = Number(el.ancho) * props.escala;
    const altoPx = Number(el.alto) * props.escala;
    // Convertimos px de pantalla a mm equivalentes para crearSvgBarcode
    const anchoMm = Number(el.ancho);
    const altoMm = Number(el.alto);
    return crearSvgBarcode(val, anchoMm, altoMm, el.mostrar_texto !== false, el.fuente_barcode || null);
}

function iniciarMovimiento(event, el) { arrastrar(event, el, false); }
function iniciarRedimension(event, el) { arrastrar(event, el, true); }

function arrastrar(event, el, resize) {
    emit("seleccionar", el.id);
    const inicio = { x: event.clientX, y: event.clientY, el: { ...el } };
    const mover = (e) => {
        const dx = Math.round(((e.clientX - inicio.x) / props.escala) * 2) / 2;
        const dy = Math.round(((e.clientY - inicio.y) / props.escala) * 2) / 2;
        emit("cambiar", {
            id: el.id,
            cambio: resize
                ? { ancho: Math.max(5, inicio.el.ancho + dx), alto: Math.max(2, inicio.el.alto + dy) }
                : { x: Math.max(0, inicio.el.x + dx), y: Math.max(0, inicio.el.y + dy) },
        });
    };
    const terminar = () => {
        window.removeEventListener("pointermove", mover);
        window.removeEventListener("pointerup", terminar);
    };
    window.addEventListener("pointermove", mover);
    window.addEventListener("pointerup", terminar);
}
</script>
