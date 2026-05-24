import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";
import AppLayout from "../layouts/AppLayout.vue";
import GuestLayout from "../layouts/GuestLayout.vue";
import Login from "../pages/Login.vue";
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
import NuevaVenta from "../pages/ventas/NuevaVenta.vue";
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
      { path: "", name: "dashboard", component: Dashboard },
      { path: "procesos", name: "procesos", component: ProcesosIndex },
      {path: "consultasreportes", name: "consultasreportes", component: ConsultasReportes },
      { path: "catalogos", name: "catalogos", component: CatalogosIndex },
      { path: "clientes", name: "clientes", component: ClientesIndex },
      { path: "proveedores", name: "proveedores", component: ProveedoresIndex },
      { path: "categorias", name: "categorias", component: CategoriasIndex },
      { path: "marcas", name: "marcas", component: MarcasIndex },
      { path: "atributos", name: "atributos", component: GestorAtributos },
      { path: "unidades-medida", name: "unidades-medida", component: GestorUnidadesMedida },
      { path: "productos", name: "productos", component: GestorProductos },
      { path: "ventas", name: "ventas", component: NuevaVenta },
      { path: "compras", name: "compras", component: NuevaCompra },
      { path: "caja", name: "caja", component: CorteCaja },
      { path: "cortes-caja", name: "cortes-caja", component: HistorialCortes },
      { path: "corte-detalle/:id", name: "corte-detalle", component: CorteDetalle, props: true },
      { path: "exhibicion", name: "exhibicion", component: Exhibicion },
      { path: "reportes-caja", name: "reportes-caja", component: ReporteCaja },
      { path: "reportes-ventas", name: "reportes-ventas", component: ReporteVentas },
      { path: "reportes-ventas-agrupado", name: "reportes-ventas-agrupado", component: VentasAgrupado },
      { path: "reportes-compras", name: "reportes-compras", component: reportecompras }

    ],
  },
  {
    path: "/",
    component: GuestLayout,
    meta: { guest: true },
    children: [{ path: "login", name: "login", component: Login }],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();

  // ⏳ Aún no sabemos si hay sesión → esperar
  if (!auth.booted) {
    await auth.bootstrap();
  }

  // 🔐 Ruta protegida
  if (to.meta.auth && !auth.isAuth) {
    return { name: "login" };
  }

  // 🚫 Ruta guest
  if (to.meta.guest && auth.isAuth) {
    return { name: "dashboard" };
  }

  return true;
});


export default router;
