<template>
    <!-- Contenedor externo con las dimensiones post-rotación -->
    <div :style="{ width: `${contenedorAnchoPx}px`, height: `${contenedorAltoPx}px`, position: 'relative' }">
        <!-- Lienzo del diseño, rotado desde su centro -->
        <div
            class="relative overflow-hidden bg-white text-black shadow-sm"
            :style="estiloLienzo"
        >
            <div
                v-for="el in elementos"
                :key="el.id"
                class="absolute flex overflow-hidden leading-none"
                :class="{ 'ring-2 ring-emerald-500': editable && seleccionado === el.id, 'cursor-move hover:ring-1 hover:ring-emerald-400': editable }"
                :style="estilo(el)"
                @pointerdown="editable && iniciarMovimiento($event, el)"
                @click.stop="$emit('seleccionar', el.id)"
            >
                <template v-if="esFigura(el.tipo)">
                    <div class="absolute inset-0" :style="estiloFigura(el)"></div>
                </template>
                <span v-else-if="el.tipo !== 'codigo_barras'" class="w-full self-center">{{ valor(el) }}</span>
                <span v-else class="absolute inset-0" v-html="barcode(el)"></span>
                <button
                    v-if="editable && seleccionado === el.id"
                    type="button"
                    class="absolute bottom-0 right-0 h-3 w-3 cursor-se-resize bg-emerald-600"
                    @pointerdown.stop="iniciarRedimension($event, el)"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { crearSvgBarcode, formatearValor, resolverCampo } from "@/helpers/etiquetas";

const props = defineProps({
    plantilla: { type: Object, required: true },
    datos: { type: Object, default: () => ({}) },
    precioImpresion: { type: Number, default: null },
    escala: { type: Number, default: 4 },
    rotacion: { type: Number, default: 0 },
    editable: Boolean,
    seleccionado: { type: String, default: null },
});
const emit = defineEmits(["seleccionar", "cambiar"]);
const elementos = computed(() => props.plantilla?.diseno?.elementos || []);
const anchoPx = computed(() => Number(props.plantilla.ancho_mm) * props.escala);
const altoPx = computed(() => Number(props.plantilla.alto_mm) * props.escala);
const mm = (n) => `${Number(n) * props.escala}px`;

// Cuando está girado 90°/270° el contenedor externo invierte ancho y alto
// para que ocupe el espacio correcto en el layout.
const girada = computed(() => props.rotacion === 90 || props.rotacion === 270);
const contenedorAnchoPx = computed(() => girada.value ? altoPx.value : anchoPx.value);
const contenedorAltoPx  = computed(() => girada.value ? anchoPx.value : altoPx.value);

// El lienzo se centra dentro del contenedor y se rota visualmente.
const estiloLienzo = computed(() => {
    const w = anchoPx.value;
    const h = altoPx.value;
    const cw = contenedorAnchoPx.value;
    const ch = contenedorAltoPx.value;
    return {
        position: 'absolute',
        width: `${w}px`,
        height: `${h}px`,
        left: `${(cw - w) / 2}px`,
        top:  `${(ch - h) / 2}px`,
        transform: props.rotacion ? `rotate(${props.rotacion}deg)` : undefined,
        transformOrigin: 'center center',
    };
});
const TIPOS_FIGURA = ["linea_h", "linea_v", "rectangulo", "rectangulo_relleno"];
const esFigura = (tipo) => TIPOS_FIGURA.includes(tipo);

const estiloFigura = (el) => {
    const grosorPx = Number(el.grosor || 0.3) * props.escala;
    const color = el.color || "#000000";
    if (el.tipo === "linea_h") return { borderTop: `${grosorPx}px solid ${color}`, width: "100%", height: `${grosorPx}px`, top: "50%", transform: "translateY(-50%)", position: "absolute" };
    if (el.tipo === "linea_v") return { borderLeft: `${grosorPx}px solid ${color}`, width: `${grosorPx}px`, height: "100%", left: "50%", transform: "translateX(-50%)", position: "absolute" };
    if (el.tipo === "rectangulo") return { border: `${grosorPx}px solid ${color}`, width: "100%", height: "100%", position: "absolute", inset: 0 };
    if (el.tipo === "rectangulo_relleno") return { backgroundColor: el.color_relleno || color, border: grosorPx > 0 ? `${grosorPx}px solid ${color}` : "none", width: "100%", height: "100%", position: "absolute", inset: 0 };
    return {};
};

const estilo = (el) => ({
    left: mm(el.x), top: mm(el.y), width: mm(el.ancho), height: mm(el.alto),
    fontSize: `${Number(el.fuente || 8) * props.escala * 0.3528}px`,
    fontWeight: el.negrita ? 700 : 400,
    fontFamily: el.familia_fuente || undefined,
    textAlign: el.alineacion === "centro" ? "center" : el.alineacion === "derecha" ? "right" : "left",
    alignItems: "center",
});
const valor = (el) => formatearValor(el, props.datos, props.precioImpresion);
const barcode = (el) => {
    let val = resolverCampo(props.datos, el.campo, props.precioImpresion);
    if (!String(val ?? "").trim()) val = resolverCampo(props.datos, "calculados.codigo_preferido");
    return crearSvgBarcode(val, el.ancho, el.alto, el.mostrar_texto !== false, el.fuente_barcode || null);
};

function iniciarMovimiento(event, el) {
    arrastrar(event, el, false);
}
function iniciarRedimension(event, el) {
    arrastrar(event, el, true);
}
function arrastrar(event, el, resize) {
    emit("seleccionar", el.id);
    const inicio = { x: event.clientX, y: event.clientY, el: { ...el } };
    const mover = (e) => {
        const dx = Math.round(((e.clientX - inicio.x) / props.escala) * 2) / 2;
        const dy = Math.round(((e.clientY - inicio.y) / props.escala) * 2) / 2;
        const cambio = resize
            ? { ancho: Math.max(3, inicio.el.ancho + dx), alto: Math.max(3, inicio.el.alto + dy) }
            : { x: Math.max(0, inicio.el.x + dx), y: Math.max(0, inicio.el.y + dy) };
        emit("cambiar", { id: el.id, cambio });
    };
    const terminar = () => {
        window.removeEventListener("pointermove", mover);
        window.removeEventListener("pointerup", terminar);
    };
    window.addEventListener("pointermove", mover);
    window.addEventListener("pointerup", terminar);
}
</script>
