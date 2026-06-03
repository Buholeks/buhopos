import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";
import AppLayout from "../layouts/AppLayout.vue";
import GuestLayout from "../layouts/GuestLayout.vue";
import Login from "../pages/Login.vue";
import Register from "../pages/Register.vue";
import Dashboard from "../pages/Dashboard.vue";
import ProcesosIndex from "../pages/procesos/ProcesosIndex.vue";
import CatalogosIndex from "../pages/catalogos/CatalogosIndex.vue";
import ConsultasReportes from "@/pages/procesos/ConsultasReportes.vue";
import ClientesIndex from "../pages/clientes/ClientesIndex.vue";
import ProveedoresIndex from "../pages/proveedores/ProveedoresIndex.vue";
import CategoriasIndex from "../pages/categorias/CategoriasIndex.vue";
import MarcasIndex from "../pages/marcas/MarcasIndex.vue";
import GestorAtributos from "../pages/atributos/GestorAtributos.vue";
import GestorUnidadesMedida from "../pages/atributos/GestorUnidadesMedida.vue";
import GestorProductos from "../pages/productos/GestorProductos.vue";
import CatalogoPrecios from "../pages/productos/CatalogoPrecios.vue";
import Traspasos from "../pages/productos/Traspasos.vue";
import NuevaVenta from "../pages/ventas/NuevaVenta.vue";
import CancelacionesDevoluciones from "../pages/ventas/CancelacionesDevoluciones.vue";
import NuevaCompra from "../pages/compras/NuevaCompra.vue";
import CorteCaja from "../pages/caja/CorteCaja.vue";
import HistorialCortes from "../pages/caja/HistorialCortes.vue";
import CorteDetalle from "../pages/caja/CorteDetalle.vue";
import Exhibicion from "../pages/productos/Exhibicion.vue";
import ReporteCaja from "@/pages/reportes/caja/Index.vue";
import ReporteVentas from "@/pages/reportes/venta/Index.vue";
import VentasAgrupado from "@/pages/reportes/venta/VentasAgrupado.vue";
import reportecompras from "@/pages/compras/Reporte.vue";

const routes = [
  {
    path: "/",
    component: AppLayout,
    meta: { auth: true },
    children: [
      { path: "", name: "dashboard", component: Dashboard, meta: { title: "Inicio" } },
      { path: "procesos", name: "procesos", component: ProcesosIndex, meta: { title: "Procesos" } },
      { path: "consultasreportes", name: "consultasreportes", component: ConsultasReportes, meta: { title: "Consultas y reportes" } },
      { path: "catalogos", name: "catalogos", component: CatalogosIndex, meta: { title: "Catalogos" } },
      { path: "clientes", name: "clientes", component: ClientesIndex, meta: { title: "Clientes" } },
      { path: "proveedores", name: "proveedores", component: ProveedoresIndex, meta: { title: "Proveedores" } },
      { path: "categorias", name: "categorias", component: CategoriasIndex, meta: { title: "Categorias" } },
      { path: "marcas", name: "marcas", component: MarcasIndex, meta: { title: "Marcas" } },
      { path: "atributos", name: "atributos", component: GestorAtributos, meta: { title: "Atributos" } },
      { path: "unidades-medida", name: "unidades-medida", component: GestorUnidadesMedida, meta: { title: "Unidades de medida" } },
      { path: "productos", name: "productos", component: GestorProductos, meta: { title: "Productos" } },
      { path: "catalogo-precios", name: "catalogo-precios", component: CatalogoPrecios, meta: { title: "Catalogo de precios" } },
      { path: "traspasos", redirect: { name: "traspasos-nuevo" } },
      { path: "traspasos/nuevo", name: "traspasos-nuevo", component: Traspasos, props: { modo: "nuevo" }, meta: { title: "Nuevo traspaso" } },
      { path: "traspasos/consulta", redirect: { name: "traspasos-entrada" } },
      { path: "traspasos/entrada", name: "traspasos-entrada", component: Traspasos, props: { modo: "consulta", tipo: "entrada" }, meta: { title: "Traspasos de entrada" } },
      { path: "traspasos/salida", name: "traspasos-salida", component: Traspasos, props: { modo: "consulta", tipo: "salida" }, meta: { title: "Traspasos de salida" } },
      { path: "ventas", name: "ventas", component: NuevaVenta, meta: { title: "Ventas" } },
      { path: "cancelaciones-devoluciones", name: "cancelaciones-devoluciones", component: CancelacionesDevoluciones, meta: { title: "Cancelaciones y devoluciones" } },
      { path: "compras", name: "compras", component: NuevaCompra, meta: { title: "Compras" } },
      { path: "caja", name: "caja", component: CorteCaja, meta: { title: "Corte de caja" } },
      { path: "cortes-caja", name: "cortes-caja", component: HistorialCortes, meta: { title: "Cortes de caja" } },
      { path: "corte-detalle/:id", name: "corte-detalle", component: CorteDetalle, props: true, meta: { title: "Detalle de corte" } },
      { path: "exhibicion", name: "exhibicion", component: Exhibicion, meta: { title: "Exhibicion" } },
      { path: "reportes-caja", name: "reportes-caja", component: ReporteCaja, meta: { title: "Reporte de caja" } },
      { path: "reportes-ventas", name: "reportes-ventas", component: ReporteVentas, meta: { title: "Consulta de ventas" } },
      { path: "reportes-ventas-agrupado", name: "reportes-ventas-agrupado", component: VentasAgrupado, meta: { title: "Ventas agrupadas" } },
      { path: "reportes-compras", name: "reportes-compras", component: reportecompras, props: { vista: "compras" }, meta: { title: "Consulta de compras" } },
      { path: "reportes-pagos-proveedores", name: "reportes-pagos-proveedores", component: reportecompras, props: { vista: "pagos" }, meta: { title: "Pagos a proveedores" } },
    ],
  },
  {
    path: "/",
    component: GuestLayout,
    meta: { guest: true },
    children: [
      { path: "login", name: "login", component: Login, meta: { title: "Iniciar sesion" } },
      { path: "register", name: "register", component: Register, meta: { title: "Registro" } },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();

  if (!auth.booted) {
    await auth.bootstrap();
  }

  if (to.meta.auth && !auth.isAuth) {
    return { name: "login" };
  }

  if (to.meta.guest && auth.isAuth) {
    return { name: "dashboard" };
  }

  return true;
});

router.afterEach((to) => {
  const title = to.meta.title ? `${to.meta.title} | BuhoPOS` : "BuhoPOS";
  document.title = title;
});

export default router;
