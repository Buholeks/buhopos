<template>
    <div class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-200 bg-white px-4 py-3">
            <div class="mx-auto flex max-w-[1500px] flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-black">Diseñador de ticket de venta</h1>
                    <p class="text-sm text-slate-500">Arrastra campos en el encabezado y pie, ajusta las secciones fijas y guarda.</p>
                </div>
                <div class="flex gap-2">
                    <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold" @click="restaurar">Restablecer</button>
                    <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold" @click="imprimirPrueba">Imprimir prueba</button>
                    <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white" @click="guardar">Guardar</button>
                </div>
            </div>
        </header>

        <main class="mx-auto grid max-w-[1500px] gap-4 p-4 xl:grid-cols-[280px_1fr_280px]">
            <!-- Panel izquierdo -->
            <aside class="space-y-4">
                <!-- Papel -->
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Papel</h2>
                    <label class="label">Ancho</label>
                    <select v-model.number="cfg.ancho_mm" class="input">
                        <option :value="58">58 mm</option>
                        <option :value="80">80 mm</option>
                    </select>
                    <label class="label mt-2">Margen lateral (mm)</label>
                    <input v-model.number="cfg.margen_mm" type="number" min="0" max="10" step="0.5" class="input">
                </section>

                <!-- Zona activa + campos -->
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Campos arrastrables</h2>
                    <div class="mb-3 flex gap-1 rounded-xl bg-slate-100 p-1 text-xs font-bold">
                        <button
                            :class="['flex-1 rounded-lg py-1.5 transition-all', zonaActiva === 'encabezado' ? 'bg-white shadow text-blue-700' : 'text-slate-500']"
                            @click="zonaActiva = 'encabezado'"
                        >Encabezado</button>
                        <button
                            :class="['flex-1 rounded-lg py-1.5 transition-all', zonaActiva === 'pie' ? 'bg-white shadow text-blue-700' : 'text-slate-500']"
                            @click="zonaActiva = 'pie'"
                        >Pie</button>
                    </div>
                    <div v-for="grupo in variables" :key="grupo.grupo" class="mb-3">
                        <p class="mb-1 text-xs font-bold uppercase text-slate-400">{{ grupo.grupo }}</p>
                        <button
                            v-for="item in grupo.items" :key="item.campo"
                            class="mb-1 mr-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs hover:border-emerald-400"
                            @click="agregar(item)"
                        >{{ item.label }}</button>
                    </div>
                    <div class="space-y-2 border-t pt-3">
                        <button
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs font-bold hover:border-emerald-400"
                            @click="agregarSeparador"
                        >— Separador</button>
                        <button
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs font-bold hover:border-emerald-400"
                            @click="agregarCodigoBarras"
                        >▐█▌ Código de barras</button>
                        <div class="flex gap-2">
                            <input v-model="textoLibre" class="input flex-1 text-xs" placeholder="Texto libre...">
                            <button class="rounded-lg bg-slate-800 px-3 py-1 text-xs font-bold text-white" @click="agregarTexto">+</button>
                        </div>
                    </div>
                </section>

                <!-- Alturas de zonas -->
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Altura de zonas (mm)</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-xs">Encabezado<input v-model.number="cfg.encabezado.alto_mm" type="number" min="10" max="80" step="1" class="input"></label>
                        <label class="text-xs">Pie<input v-model.number="cfg.pie.alto_mm" type="number" min="10" max="60" step="1" class="input"></label>
                    </div>
                </section>

                <!-- Datos fijos -->
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Datos del ticket (fijos)</h2>
                    <div class="space-y-2 text-sm">
                        <label class="flex gap-2"><input v-model="cfg.mostrar_folio" type="checkbox"> Folio</label>
                        <label class="flex gap-2"><input v-model="cfg.mostrar_vendedor" type="checkbox"> Vendedor</label>
                        <label class="flex gap-2"><input v-model="cfg.mostrar_cliente" type="checkbox"> Cliente</label>
                    </div>
                </section>

                <!-- Productos -->
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Productos (fijos)</h2>
                    <div class="space-y-2 text-sm">
                        <label class="flex gap-2"><input v-model="cfg.productos.mostrar_variante" type="checkbox"> Variante</label>
                        <label class="flex gap-2"><input v-model="cfg.productos.mostrar_identificador" type="checkbox"> Serie / IMEI</label>
                        <label class="flex gap-2"><input v-model="cfg.productos.mostrar_precio_unitario" type="checkbox"> Precio unitario</label>
                        <label class="flex gap-2"><input v-model="cfg.productos.mostrar_descuento" type="checkbox"> Descuento</label>
                    </div>
                </section>

                <!-- Resumen -->
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Resumen (fijo)</h2>
                    <div class="space-y-2 text-sm">
                        <label class="flex gap-2"><input v-model="cfg.resumen.mostrar_subtotal_lista" type="checkbox"> Subtotal lista</label>
                        <label class="flex gap-2"><input v-model="cfg.resumen.mostrar_desc_precios" type="checkbox"> Desc. de precios</label>
                        <label class="flex gap-2"><input v-model="cfg.resumen.mostrar_forma_pago" type="checkbox"> Forma de pago</label>
                        <label class="flex gap-2"><input v-model="cfg.resumen.mostrar_cambio" type="checkbox"> Efectivo / cambio</label>
                    </div>
                </section>

                <!-- QZ -->
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Impresora QZ Tray</h2>
                    <div class="mb-2 flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" :class="conectado ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                        <span class="text-sm font-semibold">{{ conectado ? "Conectado" : "Desconectado" }}</span>
                        <button v-if="!conectado" class="ml-auto text-xs font-bold text-emerald-700" @click="intentarConectar">Conectar</button>
                        <button v-else class="ml-auto text-xs text-slate-500" @click="recargarImpresoras">Actualizar</button>
                    </div>
                    <select v-if="conectado" v-model="impresoraLocal" class="input" @change="guardarImpresora">
                        <option value="">— Sin QZ (diálogo del navegador) —</option>
                        <option v-for="p in impresoras" :key="p" :value="p">{{ p }}</option>
                    </select>
                    <p v-if="impresoraLocal" class="mt-1 text-xs font-semibold text-emerald-700">✓ Impresión directa activa en esta PC</p>
                </section>
            </aside>

            <!-- Centro: lienzo -->
            <section class="min-h-[70vh] overflow-auto rounded-2xl border border-slate-200 bg-slate-200 p-8 shadow-inner">
                <div class="mb-3 text-xs font-bold text-slate-500">
                    {{ cfg.ancho_mm }} mm · cuadrícula 1 mm · arrastra elementos, selecciona para editar propiedades
                </div>
                <div class="inline-block bg-[linear-gradient(to_right,#dbeafe_1px,transparent_1px),linear-gradient(to_bottom,#dbeafe_1px,transparent_1px)] bg-[size:18px_18px] p-1">
                    <!-- Papel -->
                    <div
                        class="relative bg-white shadow"
                        :style="{ width: `${cfg.ancho_mm * escala}px`, padding: `${cfg.margen_mm * escala}px` }"
                    >
                        <!-- Zona encabezado -->
                        <div class="relative mt-5">
                            <div
                                :class="[
                                    'absolute -top-5 left-0 rounded px-2 py-0.5 text-xs font-bold',
                                    zonaActiva === 'encabezado' ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-600',
                                ]"
                            >Encabezado</div>
                            <div :class="['rounded-sm', zonaActiva === 'encabezado' ? 'ring-2 ring-blue-400' : 'ring-1 ring-slate-200']">
                                <TicketCanvasVista
                                    :elementos="cfg.encabezado.elementos"
                                    :datos="muestra"
                                    :ancho-mm="anchoInterior"
                                    :alto-mm="cfg.encabezado.alto_mm"
                                    :escala="escala"
                                    editable
                                    :seleccionado="zonaActiva === 'encabezado' ? seleccionadoId : null"
                                    @seleccionar="id => seleccionar('encabezado', id)"
                                    @cambiar="cambiarElemento"
                                />
                            </div>
                        </div>

                        <!-- Separador -->
                        <div class="my-2 border-t border-dashed border-slate-400"></div>

                        <!-- Datos fijos (preview no editable) -->
                        <div
                            :style="{
                                display: 'grid',
                                gridTemplateColumns: '1fr 1fr',
                                gap: `${1.5 * escala}px ${3 * escala}px`,
                                marginBottom: `${2 * escala}px`,
                                fontSize: `${8 * escala * 0.3528}px`,
                            }"
                        >
                            <div>
                                <span :style="labelStl">Fecha</span>
                                <span :style="valStl">15 jun 2026 10:30</span>
                            </div>
                            <div v-if="cfg.mostrar_folio">
                                <span :style="labelStl">Folio</span>
                                <span :style="valStl">VTA-000123</span>
                            </div>
                            <div v-if="cfg.mostrar_vendedor">
                                <span :style="labelStl">Vendedor</span>
                                <span :style="valStl">Admin</span>
                            </div>
                            <div v-if="cfg.mostrar_cliente">
                                <span :style="labelStl">Cliente</span>
                                <span :style="valStl">Público general</span>
                            </div>
                        </div>

                        <!-- Productos fijos (preview) -->
                        <div :style="{ borderTop: '1px dashed #94a3b8', paddingTop: `${2 * escala}px`, marginTop: `${1 * escala}px` }">
                            <div :style="secStl">Productos</div>
                            <div
                                v-for="p in muestra.productos"
                                :key="p.nombre"
                                :style="{ padding: `${1.5 * escala}px 0`, borderBottom: '1px dotted #cbd5e1', fontSize: `${9 * escala * 0.3528}px` }"
                            >
                                <div :style="{ display: 'flex', justifyContent: 'space-between', gap: `${2 * escala}px` }">
                                    <span :style="{ fontWeight: 800, textTransform: 'uppercase' }">{{ p.nombre }}</span>
                                    <span :style="{ fontWeight: 800, whiteSpace: 'nowrap' }">{{ fmt(p.importe) }}</span>
                                </div>
                                <div v-if="cfg.productos.mostrar_variante && p.variante" :style="miniStl">{{ p.variante }}</div>
                                <div v-if="cfg.productos.mostrar_precio_unitario" :style="miniStl">{{ p.cantidad }} × {{ fmt(p.precio_unitario) }}</div>
                            </div>
                        </div>

                        <!-- Resumen fijo (preview) -->
                        <div :style="{ borderTop: '1px dashed #94a3b8', paddingTop: `${2 * escala}px`, marginTop: `${2 * escala}px` }">
                            <div :style="secStl">Resumen</div>
                            <div v-if="cfg.resumen.mostrar_subtotal_lista" :style="filaStl">
                                <span>Subtotal lista</span><span :style="{ fontWeight: 700 }">{{ fmt(muestra.subtotal_lista) }}</span>
                            </div>
                            <div :style="filaStl">
                                <span>Subtotal</span><span :style="{ fontWeight: 700 }">{{ fmt(muestra.subtotal) }}</span>
                            </div>
                            <div :style="{ ...filaStl, background: '#0f172a', color: '#fff', padding: `${1.5 * escala}px ${2 * escala}px`, fontWeight: 800, fontSize: `${12 * escala * 0.3528}px`, margin: `${1.5 * escala}px 0` }">
                                <span>Total</span><span>{{ fmt(muestra.total) }}</span>
                            </div>
                            <div v-if="cfg.resumen.mostrar_forma_pago" :style="filaStl">
                                <span>Forma de pago</span><span :style="{ fontWeight: 700 }">Efectivo</span>
                            </div>
                            <template v-if="cfg.resumen.mostrar_cambio">
                                <div :style="filaStl"><span>Recibido</span><span :style="{ fontWeight: 700 }">{{ fmt(muestra.monto_recibido) }}</span></div>
                                <div :style="filaStl"><span>Cambio</span><span :style="{ fontWeight: 700 }">{{ fmt(muestra.cambio) }}</span></div>
                            </template>
                        </div>

                        <!-- Separador -->
                        <div class="my-2 border-t border-dashed border-slate-400"></div>

                        <!-- Zona pie -->
                        <div class="relative mt-5">
                            <div
                                :class="[
                                    'absolute -top-5 left-0 rounded px-2 py-0.5 text-xs font-bold',
                                    zonaActiva === 'pie' ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-600',
                                ]"
                            >Pie</div>
                            <div :class="['rounded-sm', zonaActiva === 'pie' ? 'ring-2 ring-blue-400' : 'ring-1 ring-slate-200']">
                                <TicketCanvasVista
                                    :elementos="cfg.pie.elementos"
                                    :datos="muestra"
                                    :ancho-mm="anchoInterior"
                                    :alto-mm="cfg.pie.alto_mm"
                                    :escala="escala"
                                    editable
                                    :seleccionado="zonaActiva === 'pie' ? seleccionadoId : null"
                                    @seleccionar="id => seleccionar('pie', id)"
                                    @cambiar="cambiarElemento"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Panel derecho: propiedades -->
            <aside class="space-y-4">
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-black">Propiedades del elemento</h2>
                    <template v-if="elemento">
                        <label class="label">Tipo</label>
                        <select v-model="elemento.tipo" class="input">
                            <option value="campo">Campo dinámico</option>
                            <option value="texto">Texto libre</option>
                            <option value="separador">Separador</option>
                            <option value="codigo_barras">Código de barras</option>
                        </select>

                        <template v-if="elemento.tipo === 'campo'">
                            <label class="label">Campo</label>
                            <input v-model="elemento.campo" class="input" placeholder="ej. empresa.nombre">
                        </template>
                        <template v-if="elemento.tipo === 'texto'">
                            <label class="label">Texto</label>
                            <input v-model="elemento.texto" class="input">
                        </template>
                        <template v-if="elemento.tipo === 'codigo_barras'">
                            <label class="label">Campo a codificar</label>
                            <select v-model="elemento.campo" class="input">
                                <option value="folio">Folio</option>
                                <option value="folio_corto">Folio (número)</option>
                            </select>
                            <label class="mt-2 flex gap-2 text-sm"><input v-model="elemento.mostrar_texto" type="checkbox"> Mostrar número debajo</label>
                            <label class="label">Tamaño fuente (pt)</label>
                            <input v-model.number="elemento.fuente_barcode" type="number" step="0.5" class="input" placeholder="Auto">
                        </template>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <label class="text-xs">X mm<input v-model.number="elemento.x" type="number" step=".5" class="input"></label>
                            <label class="text-xs">Y mm<input v-model.number="elemento.y" type="number" step=".5" class="input"></label>
                            <label class="text-xs">Ancho<input v-model.number="elemento.ancho" type="number" step=".5" class="input"></label>
                            <label class="text-xs">Alto<input v-model.number="elemento.alto" type="number" step=".5" class="input"></label>
                        </div>

                        <template v-if="elemento.tipo !== 'separador'">
                            <label class="label">Fuente (pt)</label>
                            <input v-model.number="elemento.fuente" type="number" step="0.5" class="input">
                            <label class="label">Tipo de fuente</label>
                            <select
                                v-model="elemento.familia_fuente"
                                class="input"
                                :style="elemento.familia_fuente ? `font-family:${elemento.familia_fuente}` : ''"
                            >
                                <option value="">Arial (predeterminado)</option>
                                <option v-for="f in familiasFuente" :key="f.valor" :value="f.valor" :style="`font-family:${f.valor}`">{{ f.nombre }}</option>
                            </select>
                            <label class="label">Alineación</label>
                            <select v-model="elemento.alineacion" class="input">
                                <option value="izquierda">Izquierda</option>
                                <option value="centro">Centro</option>
                                <option value="derecha">Derecha</option>
                            </select>
                            <label class="mt-3 flex gap-2 text-sm"><input v-model="elemento.negrita" type="checkbox"> Negrita</label>
                        </template>

                        <button class="mt-4 w-full rounded-lg bg-red-50 px-3 py-2 text-sm font-bold text-red-700" @click="eliminarElemento">
                            Eliminar elemento
                        </button>
                    </template>
                    <p v-else class="text-sm text-slate-400">Selecciona un elemento del lienzo para editar sus propiedades.</p>
                </section>
            </aside>
        </main>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import Swal from "sweetalert2";
import { conectar, isConectado, listarImpresoras, obtenerImpresoraTicket, guardarImpresoraTicket } from "@/helpers/qzTray";
import { obtenerConfigTicket, guardarConfigTicket, cargarConfigTicketDesdeServidor, imprimirTicketVenta } from "@/helpers/tickets/imprimirTicketVenta";
import { crearTicketVenta } from "@/helpers/tickets/ticketVenta";
import TicketCanvasVista from "@/components/ventas/TicketCanvasVista.vue";

const escala = 3;

const familiasFuente = [
    { nombre: "Helvetica", valor: "Helvetica, Arial, sans-serif" },
    { nombre: "Verdana", valor: "Verdana, Geneva, sans-serif" },
    { nombre: "Tahoma", valor: "Tahoma, Geneva, sans-serif" },
    { nombre: "Courier New (mono)", valor: "'Courier New', Courier, monospace" },
    { nombre: "Impact (condensada)", valor: "Impact, Haettenschweiler, sans-serif" },
];

const variables = [
    {
        grupo: "Empresa",
        items: [
            { campo: "empresa.nombre", label: "Nombre empresa" },
            { campo: "empresa.rfc", label: "RFC" },
        ],
    },
    {
        grupo: "Sucursal",
        items: [
            { campo: "sucursal.nombre", label: "Nombre sucursal" },
            { campo: "sucursal.direccion", label: "Dirección" },
            { campo: "sucursal.telefono", label: "Teléfono" },
        ],
    },
    {
        grupo: "Ticket",
        items: [
            { campo: "folio", label: "Folio" },
            { campo: "fecha", label: "Fecha" },
            { campo: "vendedor.name", label: "Vendedor" },
            { campo: "cliente.nombre", label: "Cliente" },
        ],
    },
];

function uid() { return crypto.randomUUID(); }

function configDefault(anchoMm = 80, margenMm = 3.5) {
    const a = anchoMm - 2 * margenMm;
    return {
        ancho_mm: anchoMm,
        margen_mm: margenMm,
        mostrar_folio: true,
        mostrar_vendedor: true,
        mostrar_cliente: true,
        encabezado: {
            alto_mm: 32,
            elementos: [
                { id: uid(), tipo: "campo", campo: "empresa.nombre", x: 0, y: 2, ancho: a, alto: 7, fuente: 14, negrita: true, alineacion: "centro" },
                { id: uid(), tipo: "campo", campo: "empresa.rfc", x: 0, y: 10, ancho: a, alto: 4, fuente: 8, negrita: false, alineacion: "centro" },
                { id: uid(), tipo: "campo", campo: "sucursal.nombre", x: 0, y: 15, ancho: a, alto: 5, fuente: 10, negrita: true, alineacion: "centro" },
                { id: uid(), tipo: "campo", campo: "sucursal.direccion", x: 0, y: 21, ancho: a, alto: 4, fuente: 8, negrita: false, alineacion: "centro" },
                { id: uid(), tipo: "campo", campo: "sucursal.telefono", x: 0, y: 26, ancho: a, alto: 4, fuente: 8, negrita: false, alineacion: "centro" },
            ],
        },
        productos: {
            mostrar_variante: true,
            mostrar_identificador: true,
            mostrar_precio_unitario: true,
            mostrar_descuento: true,
        },
        resumen: {
            mostrar_subtotal_lista: true,
            mostrar_desc_precios: true,
            mostrar_forma_pago: true,
            mostrar_cambio: true,
        },
        pie: {
            alto_mm: 22,
            elementos: [
                { id: uid(), tipo: "separador", campo: "", x: 0, y: 1, ancho: a, alto: 2 },
                { id: uid(), tipo: "texto", campo: "", texto: "Gracias por su compra", x: 0, y: 4, ancho: a, alto: 6, fuente: 11, negrita: true, alineacion: "centro" },
                { id: uid(), tipo: "texto", campo: "", texto: "Conserve este ticket", x: 0, y: 11, ancho: a, alto: 4, fuente: 8, negrita: false, alineacion: "centro" },
                { id: uid(), tipo: "campo", campo: "folio", x: 0, y: 16, ancho: a, alto: 5, fuente: 9, negrita: false, alineacion: "centro" },
            ],
        },
    };
}

const cfg = reactive(configDefault());
const zonaActiva = ref("encabezado");
const seleccionadoId = ref(null);
const textoLibre = ref("");

const conectado = ref(false);
const impresoras = ref([]);
const impresoraLocal = ref("");

const anchoInterior = computed(() => cfg.ancho_mm - 2 * cfg.margen_mm);

const muestra = crearTicketVenta({
    folio: "VTA-000123",
    created_at: new Date().toISOString(),
    empresa: { nombre: "Mi Empresa S.A.", rfc: "XAXX010101000" },
    sucursal: { nombre: "Sucursal Centro", direccion: "Calle 5 de Mayo #10", telefono: "55 1234 5678" },
    vendedor: { name: "Vendedor" },
    cliente: null,
    forma_pago: "efectivo",
    subtotal: 450,
    descuento: 0,
    total: 450,
    monto_recibido: 500,
    cambio: 50,
    detalles: [
        { cantidad: 2, producto_nombre: "Producto A", precio_aplicado: 100, subtotal: 200 },
        { cantidad: 1, producto_nombre: "Producto B", precio_aplicado: 250, subtotal: 250 },
    ],
});

const elemento = computed(() => {
    if (!seleccionadoId.value) return null;
    return (
        cfg.encabezado.elementos.find((e) => e.id === seleccionadoId.value) ||
        cfg.pie.elementos.find((e) => e.id === seleccionadoId.value) ||
        null
    );
});

const e = computed(() => escala);
const labelStl = computed(() => ({ display: "block", color: "#64748b", fontSize: `${7 * e.value * 0.3528}px`, textTransform: "uppercase" }));
const valStl = computed(() => ({ display: "block", fontWeight: 700, overflowWrap: "anywhere" }));
const miniStl = computed(() => ({ fontSize: `${8 * e.value * 0.3528}px`, color: "#475569", marginTop: `${e.value}px` }));
const secStl = computed(() => ({ fontSize: `${7 * e.value * 0.3528}px`, fontWeight: 800, letterSpacing: "1.2px", textTransform: "uppercase", marginBottom: `${1.5 * e.value}px` }));
const filaStl = computed(() => ({ display: "flex", justifyContent: "space-between", gap: `${3 * e.value}px`, fontSize: `${9 * e.value * 0.3528}px` }));
const fmt = (v) => new Intl.NumberFormat("es-MX", { style: "currency", currency: "MXN" }).format(Number(v ?? 0));

onMounted(async () => {
    await cargarConfigTicketDesdeServidor();
    const guardado = obtenerConfigTicket();
    if (guardado?.encabezado?.elementos) {
        cfg.ancho_mm = guardado.ancho_mm ?? cfg.ancho_mm;
        cfg.margen_mm = guardado.margen_mm ?? cfg.margen_mm;
        cfg.mostrar_folio = guardado.mostrar_folio ?? cfg.mostrar_folio;
        cfg.mostrar_vendedor = guardado.mostrar_vendedor ?? cfg.mostrar_vendedor;
        cfg.mostrar_cliente = guardado.mostrar_cliente ?? cfg.mostrar_cliente;
        Object.assign(cfg.encabezado, guardado.encabezado);
        Object.assign(cfg.productos, guardado.productos ?? {});
        Object.assign(cfg.resumen, guardado.resumen ?? {});
        Object.assign(cfg.pie, guardado.pie ?? {});
    }
    conectado.value = isConectado();
    if (!conectado.value) conectado.value = await conectar();
    if (conectado.value) await recargarImpresoras();
    impresoraLocal.value = obtenerImpresoraTicket() || "";
});

function seleccionar(zona, id) {
    zonaActiva.value = zona;
    seleccionadoId.value = id;
}

function cambiarElemento({ id, cambio }) {
    const elEnc = cfg.encabezado.elementos.find((e) => e.id === id);
    if (elEnc) { Object.assign(elEnc, cambio); return; }
    const elPie = cfg.pie.elementos.find((e) => e.id === id);
    if (elPie) Object.assign(elPie, cambio);
}

function eliminarElemento() {
    cfg.encabezado.elementos = cfg.encabezado.elementos.filter((e) => e.id !== seleccionadoId.value);
    cfg.pie.elementos = cfg.pie.elementos.filter((e) => e.id !== seleccionadoId.value);
    seleccionadoId.value = null;
}

function agregar(item) {
    const zona = zonaActiva.value === "pie" ? cfg.pie : cfg.encabezado;
    const a = anchoInterior.value;
    zona.elementos.push({
        id: uid(), tipo: "campo", campo: item.campo, texto: "",
        x: 0, y: 2, ancho: a, alto: 5, fuente: 10, negrita: false, alineacion: "centro",
    });
    seleccionadoId.value = zona.elementos.at(-1).id;
}

function agregarTexto() {
    if (!textoLibre.value.trim()) return;
    const zona = zonaActiva.value === "pie" ? cfg.pie : cfg.encabezado;
    const a = anchoInterior.value;
    zona.elementos.push({
        id: uid(), tipo: "texto", campo: "", texto: textoLibre.value,
        x: 0, y: 2, ancho: a, alto: 5, fuente: 10, negrita: false, alineacion: "centro",
    });
    seleccionadoId.value = zona.elementos.at(-1).id;
    textoLibre.value = "";
}

function agregarSeparador() {
    const zona = zonaActiva.value === "pie" ? cfg.pie : cfg.encabezado;
    const a = anchoInterior.value;
    zona.elementos.push({ id: uid(), tipo: "separador", campo: "", x: 0, y: 2, ancho: a, alto: 2 });
    seleccionadoId.value = zona.elementos.at(-1).id;
}

function agregarCodigoBarras() {
    const zona = zonaActiva.value === "pie" ? cfg.pie : cfg.encabezado;
    const a = anchoInterior.value;
    zona.elementos.push({ id: uid(), tipo: "codigo_barras", campo: "folio", x: 0, y: 2, ancho: a, alto: 12, mostrar_texto: true, fuente_barcode: null });
    seleccionadoId.value = zona.elementos.at(-1).id;
}

function guardar() {
    guardarConfigTicket(JSON.parse(JSON.stringify(cfg)));
    Swal.fire({ icon: "success", title: "Configuración guardada", timer: 1500, showConfirmButton: false });
}

function restaurar() {
    const nuevo = configDefault(cfg.ancho_mm, cfg.margen_mm);
    cfg.mostrar_folio = nuevo.mostrar_folio;
    cfg.mostrar_vendedor = nuevo.mostrar_vendedor;
    cfg.mostrar_cliente = nuevo.mostrar_cliente;
    Object.assign(cfg.encabezado, nuevo.encabezado);
    Object.assign(cfg.productos, nuevo.productos);
    Object.assign(cfg.resumen, nuevo.resumen);
    Object.assign(cfg.pie, nuevo.pie);
    seleccionadoId.value = null;
}

async function intentarConectar() {
    conectado.value = await conectar();
    if (conectado.value) await recargarImpresoras();
}
async function recargarImpresoras() {
    try { impresoras.value = await listarImpresoras(); } catch { impresoras.value = []; }
}
function guardarImpresora() { guardarImpresoraTicket(impresoraLocal.value); }

async function imprimirPrueba() {
    try {
        await imprimirTicketVenta(muestra, impresoraLocal.value || null);
    } catch (err) {
        Swal.fire("Error", err.message, "error");
    }
}
</script>

<style scoped>
.label { display:block; margin-top:.6rem; font-size:.7rem; font-weight:700; text-transform:uppercase; color:#64748b; }
.input { width:100%; border:1px solid #cbd5e1; border-radius:.6rem; padding:.4rem .55rem; font-size:.8rem; margin-top:.2rem; }
</style>
