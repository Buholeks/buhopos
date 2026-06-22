<template>
    <div class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white px-4 py-3">
            <div class="mx-auto flex max-w-[1500px] flex-wrap items-center justify-between gap-3">
                <div><h1 class="text-xl font-black">Diseñador de etiquetas</h1><p class="text-sm text-slate-500">Arrastra campos, ajusta medidas y guarda plantillas reutilizables.</p></div>
                <div class="flex gap-2">
                    <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold" @click="nueva">Nueva plantilla</button>
                    <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-60" :disabled="imprimiendoPrueba" @click="imprimirPrueba">{{ imprimiendoPrueba ? "Imprimiendo..." : "Imprimir prueba" }}</button>
                    <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white" @click="guardar">Guardar plantilla</button>
                </div>
            </div>
        </header>

        <main class="mx-auto grid max-w-[1500px] gap-4 p-4 xl:grid-cols-[280px_1fr_300px]">
            <aside class="space-y-4">
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <label class="text-xs font-bold uppercase text-slate-500">Plantilla</label>
                    <div class="mt-1 flex gap-2">
                        <select v-model="plantillaId" class="w-full rounded-xl border border-slate-300 px-3 py-2" @change="cargarSeleccionada"><option v-for="p in plantillas" :key="p.id" :value="p.id">{{ p.nombre }} · {{ p.tipo }}</option></select>
                        <button v-if="edicion.id" class="rounded-xl border border-red-200 bg-red-50 px-3 py-1 text-sm font-bold text-red-600 hover:bg-red-100" title="Eliminar plantilla" @click="eliminarPlantilla">✕</button>
                    </div>
                    <input v-model="edicion.nombre" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 font-bold" placeholder="Nombre">
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <select v-model="edicion.tipo" class="rounded-lg border border-slate-300 px-2 py-1"><option value="compra">Compra</option><option value="precio">Precio</option></select>
                        <input v-model.number="edicion.ancho_mm" type="number" class="rounded-lg border border-slate-300 px-2 py-1" title="Ancho mm">
                        <input v-model.number="edicion.alto_mm" type="number" class="rounded-lg border border-slate-300 px-2 py-1" title="Alto mm">
                    </div>
                </section>
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Figuras</h2>
                    <div class="flex flex-wrap gap-2">
                        <button class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold hover:border-emerald-400" @click="agregarFigura('linea_h')">— Línea H</button>
                        <button class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold hover:border-emerald-400" @click="agregarFigura('linea_v')">| Línea V</button>
                        <button class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold hover:border-emerald-400" @click="agregarFigura('rectangulo')">▭ Rectángulo</button>
                        <button class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold hover:border-emerald-400" @click="agregarFigura('rectangulo_relleno')">▬ Relleno</button>
                    </div>
                </section>
                <section class="max-h-[65vh] overflow-y-auto rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Campos disponibles</h2>
                    <div v-for="grupo in variables" :key="grupo.grupo" class="mb-4">
                        <p class="mb-1 text-xs font-bold uppercase text-slate-400">{{ grupo.grupo }}</p>
                        <button v-for="item in grupo.items" :key="item.campo" class="mb-1 mr-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs hover:border-emerald-400" @click="agregar(item)">{{ item.label }}</button>
                    </div>
                    <div class="border-t pt-3">
                        <input v-model="campoPersonalizado" class="w-full rounded-lg border border-slate-300 px-2 py-1 text-xs" placeholder="Ej. variante.atributos.Color">
                        <button class="mt-2 w-full rounded-lg bg-slate-800 px-2 py-1 text-xs font-bold text-white" @click="agregar({ campo: campoPersonalizado, label: campoPersonalizado })">Agregar campo personalizado</button>
                    </div>
                    <p v-if="perfil.material === 'continua'" class="mt-2 text-xs font-semibold text-emerald-700">Cinta continua: diseña a 62 mm de ancho × longitud de corte (p.ej. 89.8 mm). Usa el mismo tamaño en la plantilla y en el perfil para que no haya escala.</p>
                </section>
            </aside>

            <section class="min-h-[70vh] overflow-auto rounded-2xl border border-slate-200 bg-slate-200 p-8 shadow-inner">
                <div class="mb-3 flex items-center justify-between text-xs font-bold text-slate-500"><span>{{ edicion.ancho_mm }} × {{ edicion.alto_mm }} mm · cuadrícula 0.5 mm</span><span>Brother QL-800: ancho máximo recomendado 62 mm</span></div>
                <div class="inline-block bg-[linear-gradient(to_right,#dbeafe_1px,transparent_1px),linear-gradient(to_bottom,#dbeafe_1px,transparent_1px)] bg-[size:20px_20px] p-1">
                    <EtiquetaVista :plantilla="edicion" :datos="muestra" :precio-impresion="899" :escala="6" :rotacion="perfil.rotacion || 0" editable :seleccionado="seleccionado" @seleccionar="seleccionado = $event" @cambiar="cambiarElemento" />
                </div>
            </section>

            <aside class="space-y-4">
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Propiedades del elemento</h2>
                    <template v-if="elemento">
                        <label class="label">Campo</label><input v-model="elemento.campo" class="input">
                        <label v-if="elemento.tipo === 'texto'" class="label">Texto</label><input v-if="elemento.tipo === 'texto'" v-model="elemento.texto" class="input">
                        <div class="mt-3 grid grid-cols-2 gap-2"><label>X mm<input v-model.number="elemento.x" type="number" step=".5" class="input"></label><label>Y mm<input v-model.number="elemento.y" type="number" step=".5" class="input"></label><label>Ancho<input v-model.number="elemento.ancho" type="number" step=".5" class="input"></label><label>Alto<input v-model.number="elemento.alto" type="number" step=".5" class="input"></label></div>
                        <label class="label">Tipo</label>
                        <select v-model="elemento.tipo" class="input">
                            <option value="campo">Campo</option>
                            <option value="precio">Precio</option>
                            <option value="codigo_barras">Código de barras</option>
                            <option value="texto">Texto libre</option>
                            <option value="linea_h">— Línea horizontal</option>
                            <option value="linea_v">— Línea vertical</option>
                            <option value="rectangulo">▭ Rectángulo (borde)</option>
                            <option value="rectangulo_relleno">▬ Rectángulo relleno</option>
                        </select>

                        <!-- Propiedades de figuras -->
                        <template v-if="esFigura(elemento.tipo)">
                            <label class="label mt-2">Color</label>
                            <div class="flex gap-2">
                                <input v-model="elemento.color" type="color" class="h-9 w-12 cursor-pointer rounded-lg border border-slate-300 p-1">
                                <input v-model="elemento.color" class="input flex-1" placeholder="#000000">
                            </div>
                            <template v-if="elemento.tipo === 'rectangulo_relleno'">
                                <label class="label mt-2">Color relleno</label>
                                <div class="flex gap-2">
                                    <input v-model="elemento.color_relleno" type="color" class="h-9 w-12 cursor-pointer rounded-lg border border-slate-300 p-1">
                                    <input v-model="elemento.color_relleno" class="input flex-1" placeholder="#000000">
                                </div>
                            </template>
                            <label class="label mt-2">Grosor (mm)</label>
                            <input v-model.number="elemento.grosor" type="number" min="0.1" max="10" step="0.1" class="input">
                        </template>

                        <!-- Propiedades de texto -->
                        <template v-if="!esFigura(elemento.tipo) && elemento.tipo !== 'codigo_barras'">
                            <label class="label">Fuente (pt)</label><input v-model.number="elemento.fuente" type="number" class="input">
                            <label class="label">Tipo de fuente</label>
                            <select v-model="elemento.familia_fuente" class="input" :style="elemento.familia_fuente ? `font-family:${elemento.familia_fuente}` : ''">
                                <option value="">Arial (predeterminado)</option>
                                <option v-for="f in familiasFuente" :key="f.valor" :value="f.valor" :style="`font-family:${f.valor}`">{{ f.nombre }}</option>
                            </select>
                            <label class="label">Alineación</label><select v-model="elemento.alineacion" class="input"><option value="izquierda">Izquierda</option><option value="centro">Centro</option><option value="derecha">Derecha</option></select>
                            <label class="mt-3 flex gap-2 text-sm"><input v-model="elemento.negrita" type="checkbox"> Negrita</label>
                        </template>
                        <template v-if="elemento.tipo === 'codigo_barras'">
                            <label class="label">Fuente (pt)</label><input v-model.number="elemento.fuente" type="number" class="input">
                            <label class="mt-2 flex gap-2 text-sm"><input v-model="elemento.mostrar_texto" type="checkbox"> Mostrar código escrito</label>
                            <label class="label mt-2">Fuente texto código (pt) <span class="font-normal normal-case text-slate-400">— vacío = automático</span></label>
                            <input v-model.number="elemento.fuente_barcode" type="number" min="4" max="30" step="0.5" class="input" placeholder="auto">
                        </template>
                        <button class="mt-4 w-full rounded-lg bg-red-50 px-3 py-2 text-sm font-bold text-red-700" @click="eliminarElemento">Eliminar elemento</button>
                    </template>
                    <p v-else class="text-sm text-slate-400">Selecciona un elemento del lienzo.</p>
                </section>

                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Perfil Brother QL-800</h2>
                    <select v-model="perfilId" class="input" @change="cargarPerfil"><option v-for="p in perfiles" :key="p.id" :value="p.id">{{ p.nombre }}</option></select>
                    <input v-model="perfil.nombre" class="input mt-2" placeholder="Nombre del perfil">
                    <select v-model="perfil.material" class="input mt-2"><option value="precortada">Precortada</option><option value="continua">Cinta continua</option><option value="hoja">Hoja</option></select>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <label>{{ perfil.material === 'continua' ? 'Ancho cinta mm' : 'Ancho mm' }}<input v-model.number="perfil.ancho_mm" type="number" class="input"></label>
                        <label>{{ perfil.material === 'continua' ? 'Longitud corte mm' : 'Alto mm' }}<input v-model.number="perfil.alto_mm" type="number" class="input"></label>
                        <label>Offset X<input v-model.number="perfil.offset_x_mm" type="number" step=".1" class="input"></label>
                        <label>Offset Y<input v-model.number="perfil.offset_y_mm" type="number" step=".1" class="input"></label>
                        <label>Escala<input v-model.number="perfil.escala" type="number" step=".001" class="input"></label>
                        <label>Rotación<select v-model.number="perfil.rotacion" class="input"><option :value="0">0°</option><option :value="90">90°</option><option :value="180">180°</option><option :value="270">270°</option></select></label>
                    </div>
                    <p v-if="perfil.material === 'continua' && perfil.ancho_mm > 62" class="mt-1 rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">⚠ Ancho cinta mayor a 62 mm — el QL-800 acepta máximo 62 mm. ¿Pusiste la longitud de corte aquí?</p>
                    <button class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700" @click="aplicarTamanoPerfil">Aplicar tamaño al diseño</button>
                    <label class="mt-3 flex gap-2 text-sm"><input v-model="perfil.predeterminado" type="checkbox"> Usar como perfil predeterminado al imprimir</label>
                    <button class="mt-3 w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white" @click="guardarPerfil">Guardar perfil</button>
                    <p class="mt-3 text-xs text-slate-500">En el diálogo de impresión desactiva "Encabezados y pies de página" para eliminar fecha, about:blank y número de página.</p>
                </section>

                <QzImpresoraSelector :perfil-id="perfilId" @cambiar="impresoraQz = $event" />
            </aside>
        </main>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import Swal from "sweetalert2";
import http from "@/lib/http";
import EtiquetaVista from "@/components/etiquetas/EtiquetaVista.vue";
import QzImpresoraSelector from "@/components/etiquetas/QzImpresoraSelector.vue";
import { imprimirEtiquetas } from "@/helpers/etiquetas";

const TIPOS_FIGURA = ["linea_h", "linea_v", "rectangulo", "rectangulo_relleno"];
const esFigura = (tipo) => TIPOS_FIGURA.includes(tipo);

const familiasFuente = [
    { nombre: "Helvetica", valor: "Helvetica, Arial, sans-serif" },
    { nombre: "Verdana", valor: "Verdana, Geneva, sans-serif" },
    { nombre: "Tahoma", valor: "Tahoma, Geneva, sans-serif" },
    { nombre: "Trebuchet MS", valor: "'Trebuchet MS', sans-serif" },
    { nombre: "Georgia", valor: "Georgia, serif" },
    { nombre: "Times New Roman", valor: "'Times New Roman', Times, serif" },
    { nombre: "Courier New (mono)", valor: "'Courier New', Courier, monospace" },
    { nombre: "Impact (condensada)", valor: "Impact, Haettenschweiler, sans-serif" },
];
const plantillas = ref([]); const perfiles = ref([]); const variables = ref([]);
const plantillaId = ref(null); const perfilId = ref(null); const seleccionado = ref(null); const campoPersonalizado = ref("");
const impresoraQz = ref(null);
const imprimiendoPrueba = ref(false);
const edicion = reactive({ id: null, nombre: "", tipo: "compra", ancho_mm: 62, alto_mm: 29, predeterminada: false, diseno: { elementos: [] } });
const perfil = reactive({ id: null, nombre: "Brother QL-800", impresora: "Brother QL-800", material: "precortada", ancho_mm: 62, alto_mm: 29, separacion_mm: 0, offset_x_mm: 0, offset_y_mm: 0, escala: 1, rotacion: 0, corte_automatico: true, predeterminado: false });
const muestra = { empresa: { nombre: "MI EMPRESA", rfc: "RFC010101" }, sucursal: { nombre: "Sucursal Centro" }, compra: { folio: "12062601", fecha: "2026-06-12", folio_fecha: "12062601 · 12/06/2026", proveedor: "Proveedor" }, producto: { nombre: "Tenis deportivo", codigo: "TEN-001", marca: "Marca", modelo: "Runner", categoria: "Calzado" }, variante: { nombre: "Negro / 26 MX", sku: "TEN-N-26", codigo_barras: "7501234567890", atributos: { Color: "Negro", Talla: "26 MX" } }, precios: { compra: 500, venta: 899, precio1: 850, precio2: 800 }, calculados: { codigo_preferido: "7501234567890", producto_variante: "Tenis deportivo - Negro / 26 MX" } };
const elemento = computed(() => (edicion.diseno?.elementos || []).find((e) => e.id === seleccionado.value));

watch(() => perfil.material, (material) => {
    if (material === "continua") normalizarContinua();
});
function normalizarContinua() {
    perfil.ancho_mm = Math.min(62, Number(perfil.ancho_mm) || 62);
    perfil.alto_mm = Math.max(29, Number(perfil.alto_mm) || 29);
}

onMounted(cargar);
async function cargar() {
    const plantillaSeleccionada = plantillaId.value;
    const perfilSeleccionado = perfilId.value;
    const { data } = await http.get("/api/etiquetas/configuracion");
    plantillas.value = data.plantillas; perfiles.value = data.perfiles; variables.value = data.variables;
    plantillaId.value = plantillas.value.some((p) => p.id === Number(plantillaSeleccionada)) ? Number(plantillaSeleccionada) : plantillas.value[0]?.id;
    perfilId.value = perfiles.value.some((p) => p.id === Number(perfilSeleccionado)) ? Number(perfilSeleccionado) : (perfiles.value.find((p) => p.predeterminado) || perfiles.value[0])?.id;
    cargarSeleccionada(); cargarPerfil();
}
function copiarPlano(valor) {
    return JSON.parse(JSON.stringify(valor));
}
function asignar(destino, origen) {
    const copia = copiarPlano(origen);
    if (destino === edicion) {
        copia.diseno = copia.diseno || {};
        copia.diseno.elementos = Array.isArray(copia.diseno.elementos) ? copia.diseno.elementos : [];
    }
    Object.assign(destino, copia);
}
function cargarSeleccionada() { const p = plantillas.value.find((x) => x.id === Number(plantillaId.value)); if (p) asignar(edicion, p); seleccionado.value = null; }
function cargarPerfil() { const p = perfiles.value.find((x) => x.id === Number(perfilId.value)); if (p) asignar(perfil, p); if (perfil.material === "continua") normalizarContinua(); }
function nueva() { asignar(edicion, { id: null, nombre: "Nueva plantilla", tipo: "compra", ancho_mm: 62, alto_mm: 29, predeterminada: false, diseno: { elementos: [] } }); plantillaId.value = null; }
function agregar(item) {
    if (!item.campo) return;
    if (!edicion.diseno) edicion.diseno = { elementos: [] };
    if (!Array.isArray(edicion.diseno.elementos)) edicion.diseno.elementos = [];
    const el = { id: crypto.randomUUID(), tipo: item.tipo || (item.campo.startsWith("precios.") ? "precio" : "campo"), campo: item.campo, texto: item.tipo === "texto" ? "Texto libre" : "", x: 2, y: 2, ancho: item.tipo === "codigo_barras" ? 40 : 30, alto: item.tipo === "codigo_barras" ? 12 : 5, fuente: 8, negrita: false, alineacion: "izquierda", mostrar_texto: true };
    edicion.diseno.elementos.push(el); seleccionado.value = el.id;
}
function agregarFigura(tipo) {
    if (!edicion.diseno) edicion.diseno = { elementos: [] };
    if (!Array.isArray(edicion.diseno.elementos)) edicion.diseno.elementos = [];
    const esLinea = tipo === "linea_h" || tipo === "linea_v";
    const el = {
        id: crypto.randomUUID(), tipo, campo: "", texto: "",
        x: 2, y: 2,
        ancho: tipo === "linea_v" ? 0.5 : 30,
        alto: tipo === "linea_h" ? 0.5 : (esLinea ? 15 : 10),
        color: "#000000", color_relleno: "#000000", grosor: 0.3,
        fuente: 8, negrita: false, alineacion: "izquierda",
    };
    edicion.diseno.elementos.push(el);
    seleccionado.value = el.id;
}
function cambiarElemento({ id, cambio }) {
    const actual = edicion.diseno?.elementos?.find((e) => e.id === id);
    if (actual) Object.assign(actual, cambio);
}
function eliminarElemento() {
    if (edicion.diseno?.elementos) edicion.diseno.elementos = edicion.diseno.elementos.filter((e) => e.id !== seleccionado.value);
    seleccionado.value = null;
}
async function eliminarPlantilla() {
    const r = await Swal.fire({
        title: "¿Eliminar plantilla?",
        text: `Se eliminará "${edicion.nombre}" permanentemente.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
    });
    if (!r.isConfirmed) return;
    await http.delete(`/api/etiquetas/plantillas/${edicion.id}`);
    plantillas.value = plantillas.value.filter((p) => p.id !== edicion.id);
    const siguiente = plantillas.value[0] ?? null;
    if (siguiente) { plantillaId.value = siguiente.id; asignar(edicion, siguiente); }
    else nueva();
}
async function guardar() {
    const url = edicion.id ? `/api/etiquetas/plantillas/${edicion.id}` : "/api/etiquetas/plantillas";
    const { data } = await http[edicion.id ? "put" : "post"](url, edicion);
    const indice = plantillas.value.findIndex((p) => p.id === data.id);
    if (indice >= 0) plantillas.value[indice] = data;
    else plantillas.value.push(data);
    plantillaId.value = data.id;
    asignar(edicion, data);
    await Swal.fire("Plantilla guardada", data.nombre, "success");
}
async function guardarPerfil() {
    const url = perfil.id ? `/api/etiquetas/perfiles/${perfil.id}` : "/api/etiquetas/perfiles";
    const { data } = await http[perfil.id ? "put" : "post"](url, perfil);
    if (data.predeterminado) perfiles.value.forEach((p) => { p.predeterminado = p.id === data.id; });
    const indice = perfiles.value.findIndex((p) => p.id === data.id);
    if (indice >= 0) perfiles.value[indice] = data;
    else perfiles.value.push(data);
    perfilId.value = data.id;
    asignar(perfil, data);
    await Swal.fire("Perfil guardado", `Medida conservada: ${data.ancho_mm} × ${data.alto_mm} mm.`, "success");
}
async function imprimirPrueba() {
    if (imprimiendoPrueba.value) return;
    imprimiendoPrueba.value = true;
    try {
        await imprimirEtiquetas({ plantilla: edicion, perfil, items: [{ seleccionado: true, cantidad: 1, precio_impresion: 899, datos: muestra }], impresoraQz: impresoraQz.value });
    } catch (e) {
        Swal.fire("No se pudo imprimir", e.response?.data?.message || e.message, "error");
    } finally {
        imprimiendoPrueba.value = false;
    }
}
function aplicarTamanoPerfil() {
    if (perfil.material === "continua") normalizarContinua();
    edicion.ancho_mm = Number(perfil.ancho_mm);
    edicion.alto_mm = Number(perfil.alto_mm);
}
</script>

<style scoped>
.label { display:block; margin-top:.7rem; font-size:.7rem; font-weight:700; text-transform:uppercase; color:#64748b; }
.input { width:100%; border:1px solid #cbd5e1; border-radius:.6rem; padding:.4rem .55rem; font-size:.8rem; }
</style>
