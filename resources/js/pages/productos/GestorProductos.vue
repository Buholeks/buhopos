<template>
    <div class="min-h-screen bg-slate-50 p-3 sm:p-6">
        <!-- TOPBAR -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600"
                >
                    <Boxes class="h-5 w-5" />
                </div>
                <div>
                    <h1
                        class="text-lg font-semibold tracking-tight text-slate-900"
                    >
                        Catálogo de Productos
                    </h1>
                    <p class="text-xs text-slate-500">
                        <span class="font-semibold text-emerald-600">{{
                            paginacion.total ?? 0
                        }}</span>
                        productos registrados
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <BaseInput
                        v-model="busqueda"
                        placeholder="Nombre, código, SKU variante…"
                        :rootClass="'w-full sm:w-64'"
                        @input="buscarDebounce"
                    />
                    <BtnCerrar
                        v-if="busqueda"
                        @click="limpiarBusqueda"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        tooltip="Limpiar busqueda"
                        size="xs"
                    />
                </div>
                <BtnAgregar
                    v-if="auth.can('productos.editar')"
                    type="button"
                    @click="abrirModal()"
                >
                    Nuevo
                </BtnAgregar>
            </div>
        </div>

        <!-- EMPTY -->
        <div
            v-if="productos.length === 0 && !cargandoLista"
            class="rounded-xl border border-slate-200 bg-white p-14 text-center"
        >
            <div
                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600"
            >
                <Boxes class="h-6 w-6" />
            </div>
            <p class="text-sm font-semibold text-slate-900">
                {{
                    busqueda
                        ? `Sin resultados para «${busqueda}»`
                        : "Sin productos"
                }}
            </p>
            <p class="mt-1 text-sm text-slate-500">
                {{
                    busqueda
                        ? "Intenta con otro término"
                        : "Crea tu primer producto"
                }}
            </p>
            <BtnAgregar
                v-if="!busqueda && auth.can('productos.editar')"
                @click="abrirModal()"
                class="mt-5"
            >
                Crear Producto
            </BtnAgregar>
        </div>

        <!-- TABLE -->
        <div
            v-else
            class="overflow-hidden rounded-xl border border-slate-200 bg-white"
        >
            <div
                v-if="cargandoLista"
                class="flex items-center justify-center p-16"
            >
                <Loader2 class="h-6 w-6 animate-spin text-slate-400" />
            </div>

            <ProductosTable
                v-else
                :productos="productos"
                :formatPrecio="formatPrecio"
                :categoriaNombre="categoriaNombre"
                @editar="abrirModal"
                @duplicar="duplicarProducto"
                @toggle-activo="toggleActivoProducto"
                @eliminar="confirmarEliminar"
                @variantes="abrirVariantes"
            />

            <div
                v-if="paginacion.last_page > 1"
                class="flex items-center justify-center gap-3 border-t border-slate-200 bg-slate-50 p-3"
            >
                <BtnAnterior
                    :disabled="paginacion.current_page === 1"
                    @click="irPagina(paginacion.current_page - 1)"
                />
                <span class="text-sm text-slate-600">
                    Página
                    <span class="font-semibold">{{
                        paginacion.current_page
                    }}</span>
                    de
                    <span class="font-semibold">{{
                        paginacion.last_page
                    }}</span>
                </span>
                <BtnSiguiente
                    :disabled="paginacion.current_page === paginacion.last_page"
                    @click="irPagina(paginacion.current_page + 1)"
                />
            </div>
        </div>

        <!-- MODAL PRODUCTO -->
        <ProductoModal
            :mostrar="modal.mostrar"
            :editando="modal.editando"
            :cargando="cargando"
            :TABS="TABS"
            :tabActivo="tabActivo"
            :form="form"
            :err="err"
            :catalogos="catalogos"
            :modelosDeMarca="modelosDeMarca"
            :margen="margen"
            :formatPrecio="formatPrecio"
            @cerrar="cerrarModal"
            @enviar="enviarForm"
            @imagen-change="onImagenChange"
            @quitar-imagen="quitarImagen"
            @update:tabActivo="tabActivo = $event"
            @update:form="Object.assign(form, $event)"
        />

        <!-- MODAL VARIANTES -->
        <VariantesModal
            :mostrar="modalVar.mostrar"
            :productoNombre="modalVar.productoNombre"
            :catalogos="catalogos"
            :variantes="variantes"
            :varTab="varTab"
            :varEditandoId="varEditandoId"
            :cargandoVar="cargandoVar"
            :formEditVar="formEditVar"
            :reset-generador-key="resetGeneradorKey"
            :formatPrecio="formatPrecio"
            @cerrar="cerrarVariantes"
            @update:varTab="varTab = $event"
            @crear-masivo="agregarVariantesMasivas"
            @toggle-editar="abrirEditarVariante"
            @cerrar-edicion="varEditandoId = null"
            @guardar-edicion="(v) => guardarEditarVariante(v.id)"
            @imagen-edit-change="onImagenEditVarChange"
            @quitar-imagen-edit="quitarImagenEditVar"
            @eliminar="confirmarEliminarVariante"
            @update:formEditVar="Object.assign(formEditVar, $event)"
        />
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import http from "@/lib/http";
import { ofrecerRecuperacion } from "@/helpers/recuperar";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();
import Swal from "sweetalert2";
import { toastSuccess, toastError } from "@/lib/alert";

import ProductosTable from "@/components/productos/ProductosTable.vue";
import ProductoModal from "@/components/productos/ProductoModal.vue";
import VariantesModal from "@/components/productos/VariantesModal.vue";

import BaseInput from "@/components/ui/BaseInput.vue";
import { Boxes, Loader2 } from "lucide-vue-next";

const TABS = [
    { id: "general", label: "General" },
    { id: "precios", label: "Precios" },
];

// ── Estado ─────────────────────────────────────────────────────────────────────
const productos = ref([]);
const cargandoLista = ref(false);
const cargando = ref(false);
const cargandoVar = ref(false);
const busqueda = ref("");
const paginacion = ref({ total: 0, current_page: 1, last_page: 1 });
let buscarTimer = null;

const catalogos = reactive({
    categorias: [],
    marcas: [],
    unidades: [],
    tiposAtributo: [],
});

const modal = reactive({ mostrar: false, editando: false, idEditando: null });
const tabActivo = ref("general");

const form = reactive({
    nombre: "",
    codigo: null,
    descripcion: "",
    categoria_id: "",
    marca_id: "",
    modelo_id: "",
    unidad_medida_id: "",
    imagen: null,
    imagenPreview: null,
    imagenActualUrl: null,
    eliminarImagen: false,
    precio_costo: 0,
    precio_venta: 0,
    precio1: null,
    precio2: null,
    precio3: null,
    precio4: null,
    precio5: null,
    stock_minimo: 0,
    peso: null,
    activo: true,
    tiene_series: false, // ← nuevo
    pedido_generico: false,
});

const err = reactive({
    nombre: "",
    codigo: "",
    modelo_id: "",
    precio_costo: "",
    precio_venta: "",
});

// ── Modal variantes ────────────────────────────────────────────────────────────
const modalVar = reactive({
    mostrar: false,
    productoId: null,
    productoNombre: "",
});
const varTab = ref("lista");
const variantes = ref([]);
const resetGeneradorKey = ref(0);

const varEditandoId = ref(null);
const formEditVar = reactive({
    atributos: {},
    sku: "",
    codigo_barras: "",
    imagen: null,
    imagenPreview: null,
    imagenActualUrl: null,
    eliminarImagen: false,
    precio_costo: null,
    precio_venta: null,
    precio1: null,
    precio2: null,
    precio3: null,
    precio4: null,
    precio5: null,
    precio_oferta: null,
    oferta_activa: false,
    oferta_hasta: "",
    stock_minimo: null,
    activo: true,
});

// ── Computed ───────────────────────────────────────────────────────────────────
const modelosDeMarca = computed(() => {
    const id = Number(form.marca_id);
    if (!id) return [];
    return catalogos.marcas.find((m) => Number(m.id) === id)?.modelos ?? [];
});

const margen = computed(() => {
    if (!form.precio_costo || !form.precio_venta) return 0;
    return ((form.precio_venta - form.precio_costo) / form.precio_costo) * 100;
});

// ── Init ───────────────────────────────────────────────────────────────────────
onMounted(() => Promise.all([cargarProductos(), cargarCatalogos()]));

async function cargarCatalogos() {
    const { data } = await http.get("/api/productos/atributos-empresa");
    catalogos.tiposAtributo = data?.data ?? data;
}

async function cargarProductos(pagina = 1) {
    cargandoLista.value = true;
    try {
        const params = { page: pagina, por_pagina: 20 };
        if (busqueda.value) params.buscar = busqueda.value;
        const { data } = await http.get("/api/productos", { params });
        productos.value = data.data;
        paginacion.value = {
            total: data.total,
            current_page: data.current_page,
            last_page: data.last_page,
        };
    } catch {
        toastError("Error al cargar productos");
    } finally {
        cargandoLista.value = false;
    }
}

function buscarDebounce() {
    clearTimeout(buscarTimer);
    buscarTimer = setTimeout(() => cargarProductos(1), 400);
}

function limpiarBusqueda() {
    busqueda.value = "";
    cargarProductos(1);
}

function irPagina(p) {
    cargarProductos(p);
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function formatPrecio(v) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(v ?? 0);
}

function categoriaNombre(p) {
    if (!p?.categoria_id) return "Sin categoria";

    return (
        catalogos.categorias.find(
            (c) => Number(c.id) === Number(p.categoria_id),
        )?.ruta ??
        p.categoria?.nombre ??
        "Categoria asignada"
    );
}

function duplicarProducto(p) {
    abrirModal();
    asegurarCatalogosSeleccionados(p);
    Object.assign(form, {
        nombre: `Copia de ${p.nombre}`,
        codigo: "",
        descripcion: p.descripcion ?? "",
        categoria_id: p.categoria_id ?? "",
        marca_id: p.marca_id ?? "",
        modelo_id: p.modelo_id ?? "",
        unidad_medida_id: p.unidad_medida_id ?? "",
        precio_costo: p.precio_costo,
        precio_venta: p.precio_venta,
        precio1: p.precio1,
        precio2: p.precio2,
        precio3: p.precio3,
        precio4: p.precio4,
        precio5: p.precio5,
        stock_minimo: p.stock_minimo,
        peso: p.peso,
        activo: p.activo,
        tiene_series: p.tiene_series ?? false,
        pedido_generico: p.pedido_generico ?? false,
        imagenActualUrl: null,
    });
    modal.editando = false;
    modal.idEditando = null;
}

async function toggleActivoProducto(p) {
    const fd = formDataDesdeProducto(p);
    fd.append("_method", "PUT");
    fd.set("activo", p.activo ? "0" : "1");

    try {
        await http.post(`/api/productos/${p.id}`, fd);
        toastSuccess(p.activo ? "Producto desactivado" : "Producto activado");
        await cargarProductos(paginacion.value.current_page);
    } catch (e) {
        toastError(
            e.response?.data?.message ?? "No se pudo actualizar el estado",
        );
    }
}

function formDataDesdeProducto(p) {
    const fd = new FormData();
    [
        "nombre",
        "codigo",
        "descripcion",
        "categoria_id",
        "marca_id",
        "modelo_id",
        "unidad_medida_id",
        "precio_costo",
        "precio_venta",
        "precio1",
        "precio2",
        "precio3",
        "precio4",
        "precio5",
        "stock_minimo",
        "peso",
    ].forEach((campo) => {
        if (p[campo] !== null && p[campo] !== undefined && p[campo] !== "") {
            fd.append(campo, p[campo]);
        }
    });
    fd.append("activo", p.activo ? "1" : "0");
    fd.append("tiene_series", p.tiene_series ? "1" : "0");
    fd.append("pedido_generico", p.pedido_generico ? "1" : "0");
    return fd;
}

// function generarCodigo() {
//     form.codigo =
//         "" +
//         String(
//             productos.value.length + 1 + Math.floor(Math.random() * 10),
//         ).padStart(5, "0");
// }

function resumenPorTipo(tipoId) {
    const map = new Map();
    for (const v of variantes.value) {
        const attrs = v.atributos ?? [];
        for (const a of attrs) {
            const tId =
                a.tipo_atributo_id ??
                a.tipo_atributo?.id ??
                a.tipo_id ??
                a.tipo?.id;
            if (Number(tId) !== Number(tipoId)) continue;
            const valor = a.atributo?.valor ?? a.valor ?? "—";
            map.set(valor, (map.get(valor) ?? 0) + 1);
        }
    }
    return Array.from(map.entries())
        .map(([valor, count]) => ({ valor, count }))
        .sort((a, b) => b.count - a.count || a.valor.localeCompare(b.valor));
}

// ── Modal Producto ─────────────────────────────────────────────────────────────
function abrirModal(p = null) {
    resetForm();
    if (p) {
        asegurarCatalogosSeleccionados(p);
        modal.editando = true;
        modal.idEditando = p.id;
        Object.assign(form, {
            nombre: p.nombre,
            codigo: p.codigo,
            descripcion: p.descripcion ?? "",
            categoria_id: p.categoria_id ?? "",
            marca_id: p.marca_id ?? "",
            modelo_id: p.modelo_id ?? "",
            unidad_medida_id: p.unidad_medida_id ?? "",
            precio_costo: p.precio_costo,
            precio_venta: p.precio_venta,
            precio1: p.precio1,
            precio2: p.precio2,
            precio3: p.precio3,
            precio4: p.precio4,
            precio5: p.precio5,
            stock_minimo: p.stock_minimo,
            peso: p.peso,
            activo: p.activo,
            tiene_series: p.tiene_series ?? false, // ← nuevo
            pedido_generico: p.pedido_generico ?? false,
            imagenActualUrl: p.imagen_url,
        });
    }
    tabActivo.value = "general";
    modal.mostrar = true;
}

function asegurarCatalogosSeleccionados(p) {
    agregarSiFalta(catalogos.categorias, p?.categoria);
    agregarSiFalta(catalogos.unidades, p?.unidad_medida);

    if (p?.marca) {
        let marca = catalogos.marcas.find(
            (actual) => Number(actual.id) === Number(p.marca.id),
        );

        if (!marca) {
            marca = { ...p.marca, modelos: [] };
            catalogos.marcas.push(marca);
        }

        agregarSiFalta(marca.modelos, p.modelo);
    }
}

function agregarSiFalta(items, item) {
    if (!item?.id) return;
    if (items.some((actual) => Number(actual.id) === Number(item.id))) return;
    items.push(item);
}

function cerrarModal() {
    modal.mostrar = false;
    resetForm();
}

function resetForm() {
    Object.assign(form, {
        nombre: "",
        codigo: "",
        descripcion: "",
        categoria_id: "",
        marca_id: "",
        modelo_id: "",
        unidad_medida_id: "",
        imagen: null,
        imagenPreview: null,
        imagenActualUrl: null,
        eliminarImagen: false,
        precio_costo: 0,
        precio_venta: 0,
        precio1: null,
        precio2: null,
        precio3: null,
        precio4: null,
        precio5: null,
        stock_minimo: 0,
        peso: null,
        activo: true,
        tiene_series: false, // ← nuevo
        pedido_generico: false,
    });
    Object.assign(err, {
        nombre: "",
        codigo: "",
        modelo_id: "",
        precio_costo: "",
        precio_venta: "",
    });
    modal.editando = false;
    modal.idEditando = null;
}

function onImagenChange(file) {
    form.imagen = file;
    form.imagenPreview = URL.createObjectURL(file);
}

function quitarImagen() {
    form.imagen = null;
    form.imagenPreview = null;
    form.imagenActualUrl = null;
    form.eliminarImagen = true;
}

function validar() {
    Object.assign(err, {
        nombre: "",
        codigo: "",
        modelo_id: "",
        precio_costo: "",
        precio_venta: "",
    });
    let ok = true;
    if (!form.nombre) {
        err.nombre = "El nombre es requerido.";
        ok = false;
    }
    if (form.precio_costo < 0) {
        err.precio_costo = "Debe ser ≥ 0.";
        ok = false;
    }
    if (form.precio_venta <= 0) {
        err.precio_venta = "Debe ser > 0.";
        ok = false;
    }
    return ok;
}

async function enviarForm() {
    if (!validar()) {
        tabActivo.value = "general";
        return;
    }
    cargando.value = true;
    const fd = new FormData();
    const campos = [
        "nombre",
        "codigo",
        "descripcion",
        "categoria_id",
        "marca_id",
        "modelo_id",
        "unidad_medida_id",
        "precio_costo",
        "precio_venta",
        "precio1",
        "precio2",
        "precio3",
        "precio4",
        "precio5",
        "stock_minimo",
        "peso",
    ];
    campos.forEach((c) => {
        if (form[c] !== null && form[c] !== "") fd.append(c, form[c]);
    });
    fd.append("activo", form.activo ? "1" : "0");
    fd.append("tiene_series", form.tiene_series ? "1" : "0"); // ← nuevo
    fd.append("pedido_generico", form.pedido_generico ? "1" : "0");
    if (form.eliminarImagen) fd.append("eliminar_imagen", "1");
    if (form.imagen) fd.append("imagen", form.imagen);
    try {
        if (modal.editando) {
            fd.append("_method", "PUT");
            await http.post(`/api/productos/${modal.idEditando}`, fd);
            toastSuccess("Producto actualizado");
        } else {
            await http.post("/api/productos", fd);
            toastSuccess("Producto creado");
        }
        cerrarModal();
        await cargarProductos(paginacion.value.current_page);
    } catch (e) {
        const handled = await ofrecerRecuperacion(e, "/api/productos", async () => {
            cerrarModal();
            await cargarProductos(paginacion.value.current_page);
        });
        if (!handled) {
            toastError(e.response?.data?.message ?? "Error");
            const le = e.response?.data?.errors ?? {};
            if (le.nombre) { err.nombre = le.nombre[0]; tabActivo.value = "general"; }
            if (le.codigo) { err.codigo = le.codigo[0]; tabActivo.value = "general"; }
            if (le.modelo_id) { err.modelo_id = le.modelo_id[0]; tabActivo.value = "general"; }
            if (le.precio_costo) { err.precio_costo = le.precio_costo[0]; tabActivo.value = "precios"; }
            if (le.precio_venta) { err.precio_venta = le.precio_venta[0]; tabActivo.value = "precios"; }
        }
    } finally {
        cargando.value = false;
    }
}

async function confirmarEliminar(p) {
    const r = await Swal.fire({
        title: `Eliminar "${p.nombre}"`,
        icon: "warning",
        html: '<p class="text-sm text-slate-500">Esta acción no se puede deshacer.</p>',
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
    });
    if (!r.isConfirmed) return;
    try {
        await http.delete(`/api/productos/${p.id}`);
        toastSuccess("Producto eliminado");
        await cargarProductos(1);
    } catch (e) {
        Swal.fire({
            icon: "error",
            title: "Error",
            text: e.response?.data?.mensaje ?? "No se pudo eliminar",
        });
    }
}

// ── Variantes ──────────────────────────────────────────────────────────────────
async function abrirVariantes(p) {
    modalVar.productoId = p.id;
    modalVar.productoNombre = p.nombre;
    modalVar.mostrar = true;
    varTab.value = p.tiene_variantes ? "lista" : "generar";
    varEditandoId.value = null;
    await cargarVariantes(p.id);
}

function cerrarVariantes() {
    modalVar.mostrar = false;
    varEditandoId.value = null;
    varTab.value = "lista";
}

async function cargarVariantes(id) {
    try {
        const { data } = await http.get(`/api/productos/${id}/variantes`);
        variantes.value = data;
    } catch {
        toastError("Error al cargar variantes");
    }
}

function abrirEditarVariante(v) {
    if (varEditandoId.value === v.id) {
        varEditandoId.value = null;
        return;
    }
    varEditandoId.value = v.id;
    const atributosEdit = {};
    catalogos.tiposAtributo.forEach((t) => {
        const actual = (v.atributos ?? []).find(
            (a) =>
                Number(a.tipo_atributo_id ?? a.tipo_atributo?.id) ===
                Number(t.id),
        );
        atributosEdit[t.id] = actual?.atributo_id ?? actual?.atributo?.id ?? "";
    });
    Object.assign(formEditVar, {
        atributos: atributosEdit,
        sku: v.sku ?? "",
        codigo_barras: v.codigo_barras ?? "",
        imagen: null,
        imagenPreview: null,
        imagenActualUrl: v.imagen_url ?? null,
        eliminarImagen: false,
        precio_costo: v.precio_costo ?? null,
        precio_venta: v.precio_venta ?? null,
        precio1: v.precio1 ?? null,
        precio2: v.precio2 ?? null,
        precio3: v.precio3 ?? null,
        precio4: v.precio4 ?? null,
        precio5: v.precio5 ?? null,
        precio_oferta: v.precio_oferta ?? null,
        oferta_activa: v.oferta_activa ?? false,
        oferta_hasta: v.oferta_hasta ?? "",
        stock_minimo: v.stock_minimo ?? null,
        activo: v.activo ?? true,
    });
}

function onImagenEditVarChange(file) {
    formEditVar.imagen = file;
    formEditVar.imagenPreview = URL.createObjectURL(file);
    formEditVar.imagenActualUrl = null;
    formEditVar.eliminarImagen = false;
}

function quitarImagenEditVar() {
    formEditVar.imagen = null;
    formEditVar.imagenPreview = null;
    formEditVar.imagenActualUrl = null;
    formEditVar.eliminarImagen = true;
}

async function agregarVariantesMasivas(items) {
    const variantesNuevas = Array.isArray(items) ? items : [];
    if (!variantesNuevas.length) {
        toastError("No hay variantes nuevas por crear");
        return;
    }

    const confirmar = await Swal.fire({
        title: `Crear ${variantesNuevas.length} variantes`,
        text: "Se guardaran las combinaciones nuevas y se omitiran las existentes.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Crear variantes",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#059669",
        reverseButtons: true,
    });

    if (!confirmar.isConfirmed) return;

    cargandoVar.value = true;
    let creadas = 0;
    let fallidas = 0;

    try {
        for (const item of variantesNuevas) {
            const fd = new FormData();

            Object.entries(item.atributos ?? {}).forEach(([tipoId, atributoId]) => {
                fd.append(`atributos[${tipoId}]`, atributoId);
            });

            if (item.sku) fd.append("sku", item.sku);
            fd.append("oferta_activa", "0");

            try {
                await http.post(`/api/productos/${modalVar.productoId}/variantes`, fd);
                creadas++;
            } catch {
                fallidas++;
            }
        }

        await cargarVariantes(modalVar.productoId);
        await cargarProductos(paginacion.value.current_page);

        if (fallidas > 0) {
            toastError(`${creadas} variantes creadas, ${fallidas} no se pudieron crear`);
        } else {
            toastSuccess(`${creadas} variantes creadas`);
            varTab.value = "lista";
        }

        if (creadas > 0) resetGeneradorKey.value++;
    } finally {
        cargandoVar.value = false;
    }
}

async function guardarEditarVariante(varianteId) {
    cargandoVar.value = true;
    try {
        const fd = new FormData();
        fd.append("_method", "PUT");
        fd.append("sku", formEditVar.sku || "");
        fd.append("codigo_barras", formEditVar.codigo_barras || "");
        const camposPrecios = [
            "precio_costo",
            "precio_venta",
            "precio1",
            "precio2",
            "precio3",
            "precio4",
            "precio5",
            "precio_oferta",
            "stock_minimo",
        ];
        camposPrecios.forEach((campo) => {
            fd.append(campo, formEditVar[campo] ?? "");
        });
        Object.entries(formEditVar.atributos ?? {}).forEach(
            ([tipoId, atributoId]) => {
                if (atributoId !== null && atributoId !== "") {
                    fd.append(`atributos[${tipoId}]`, atributoId);
                }
            },
        );
        fd.append("oferta_activa", formEditVar.oferta_activa ? "1" : "0");
        fd.append("oferta_hasta", formEditVar.oferta_hasta || "");
        fd.append("activo", formEditVar.activo ? "1" : "0");
        if (formEditVar.imagen) fd.append("imagen", formEditVar.imagen);
        if (formEditVar.eliminarImagen) fd.append("eliminar_imagen", "1");
        const { data } = await http.post(
            `/api/productos/${modalVar.productoId}/variantes/${varianteId}`,
            fd,
            { headers: { "Content-Type": "multipart/form-data" } },
        );
        const idx = variantes.value.findIndex((v) => v.id === varianteId);
        if (idx !== -1) variantes.value[idx] = data.data ?? data;
        toastSuccess("Variante actualizada");
        varEditandoId.value = null;
        await cargarProductos(paginacion.value.current_page);
    } catch (e) {
        toastError(e.response?.data?.message ?? "Error al guardar");
    } finally {
        cargandoVar.value = false;
    }
}

async function confirmarEliminarVariante(v) {
    const r = await Swal.fire({
        title: `Eliminar variante "${v.nombre_variante || v.sku}"`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
    });
    if (!r.isConfirmed) return;
    try {
        await http.delete(
            `/api/productos/${modalVar.productoId}/variantes/${v.id}`,
        );
        toastSuccess("Variante eliminada");
        await cargarVariantes(modalVar.productoId);
        await cargarProductos(paginacion.value.current_page);
    } catch (e) {
        toastError(e.response?.data?.mensaje ?? "Error");
    }
}
</script>
