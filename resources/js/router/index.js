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
import Exhibicion from "../pages/productos/Exhibicion.vue";

import PedidosIndex from "@/pages/pedidos/PedidosIndex.vue";
import NuevoPedido from "@/pages/pedidos/NuevoPedido.vue";
import ConsultaPedidos from "@/pages/pedidos/ConsultaPedidos.vue";

import ApartadosIndex from "@/pages/apartados/ApartadosIndex.vue";
import NuevoApartado from "@/pages/apartados/NuevoApartado.vue";
import ConsultaApartados from "@/pages/apartados/ConsultaApartados.vue";

import NuevaVenta from "../pages/ventas/NuevaVenta.vue";
import CancelacionesDevoluciones from "../pages/ventas/CancelacionesDevoluciones.vue";

import NuevaCompra from "../pages/compras/NuevaCompra.vue";
import reportecompras from "@/pages/compras/Reporte.vue";
import DevolucionProveedor from "@/pages/compras/DevolucionProveedor.vue";

import CorteCaja from "../pages/caja/CorteCaja.vue";
import HistorialCortes from "../pages/caja/HistorialCortes.vue";
import CorteDetalle from "../pages/caja/CorteDetalle.vue";

import ReporteCaja from "@/pages/reportes/caja/Index.vue";
import ReporteVentas from "@/pages/reportes/venta/Index.vue";
import VentasAgrupado from "@/pages/reportes/venta/VentasAgrupado.vue";
import ReporteUtilidades from "@/pages/reportes/utilidades/Index.vue";

import UsuariosIndex from "@/pages/usuarios/UsuariosIndex.vue";
import RolesIndex from "@/pages/roles/RolesIndex.vue";
import SinPermiso from "@/pages/errores/SinPermiso.vue";
import PerfilIndex from "@/pages/perfil/PerfilIndex.vue";

const routes = [
  {
    path: "/",
    component: AppLayout,
    meta: { auth: true },
    children: [
      { path: "", name: "dashboard", component: Dashboard, meta: { title: "Inicio" } },

      { path: "procesos",         name: "procesos",         component: ProcesosIndex,    meta: { title: "Procesos" } },
      { path: "consultasreportes",name: "consultasreportes",component: ConsultasReportes,meta: { title: "Consultas y reportes", permiso: "reportes.ver" } },

      { path: "catalogos",  name: "catalogos",  component: CatalogosIndex,  meta: { title: "Catalogos" } },
      { path: "clientes",   name: "clientes",   component: ClientesIndex,   meta: { title: "Clientes",   permiso: "clientes.ver" } },
      { path: "proveedores",name: "proveedores",component: ProveedoresIndex,meta: { title: "Proveedores",      permiso: "catalogos.ver" } },
      { path: "categorias", name: "categorias", component: CategoriasIndex, meta: { title: "Categorias",       permiso: "catalogos.ver" } },
      { path: "marcas",     name: "marcas",     component: MarcasIndex,     meta: { title: "Marcas",           permiso: "catalogos.ver" } },
      { path: "atributos",  name: "atributos",  component: GestorAtributos, meta: { title: "Atributos",        permiso: "catalogos.ver" } },
      { path: "unidades-medida", name: "unidades-medida", component: GestorUnidadesMedida, meta: { title: "Unidades de medida", permiso: "catalogos.ver" } },

      { path: "productos",       name: "productos",       component: GestorProductos, meta: { title: "Productos",         permiso: "productos.ver" } },
      { path: "catalogo-precios",name: "catalogo-precios",component: CatalogoPrecios, meta: { title: "Catalogo de precios",permiso: "productos.precios" } },
      { path: "exhibicion",      name: "exhibicion",      component: Exhibicion,      meta: { title: "Exhibicion",        permiso: "inventario.ver" } },

      { path: "traspasos",         redirect: { name: "traspasos-nuevo" } },
      { path: "traspasos/nuevo",   name: "traspasos-nuevo",   component: Traspasos, props: { modo: "nuevo" },           meta: { title: "Nuevo traspaso",       permiso: "inventario.traspasos" } },
      { path: "traspasos/consulta",redirect: { name: "traspasos-entrada" } },
      { path: "traspasos/entrada", name: "traspasos-entrada", component: Traspasos, props: { modo: "consulta", tipo: "entrada" }, meta: { title: "Traspasos de entrada", permiso: "inventario.traspasos" } },
      { path: "traspasos/salida",  name: "traspasos-salida",  component: Traspasos, props: { modo: "consulta", tipo: "salida" },  meta: { title: "Traspasos de salida",  permiso: "inventario.traspasos" } },

      { path: "pedidos",         name: "pedidos",         component: PedidosIndex,   meta: { title: "Pedidos",          permiso: "pedidos.ver" } },
      { path: "pedidos/nuevo",   name: "pedidos-nuevo",   component: NuevoPedido,    meta: { title: "Nuevo pedido",     permiso: "pedidos.crear" } },
      { path: "pedidos/consulta",name: "pedidos-consulta",component: ConsultaPedidos,meta: { title: "Consulta de pedidos", permiso: "pedidos.ver" } },

      { path: "apartados",         name: "apartados",         component: ApartadosIndex,   meta: { title: "Apartados",          permiso: "pedidos.ver" } },
      { path: "apartados/nuevo",   name: "apartados-nuevo",   component: NuevoApartado,    meta: { title: "Nuevo apartado",     permiso: "pedidos.crear" } },
      { path: "apartados/consulta",name: "apartados-consulta",component: ConsultaApartados,meta: { title: "Consulta de apartados", permiso: "pedidos.ver" } },

      { path: "ventas",                    name: "ventas",                    component: NuevaVenta,               meta: { title: "Ventas",                     permiso: "ventas.crear" } },
      { path: "cancelaciones-devoluciones",name: "cancelaciones-devoluciones",component: CancelacionesDevoluciones,meta: { title: "Cancelaciones y devoluciones",permiso: "ventas.cancelar" } },

      { path: "compras",                   name: "compras",                   component: NuevaCompra,       meta: { title: "Compras",                   permiso: "compras.crear" } },
      { path: "reportes-compras",          name: "reportes-compras",          component: reportecompras,    props: { vista: "compras" },                meta: { title: "Consulta de compras",       permiso: "compras.ver" } },
      { path: "reportes-pagos-proveedores",name: "reportes-pagos-proveedores",component: reportecompras,    props: { vista: "pagos" },                  meta: { title: "Pagos a proveedores",       permiso: "compras.ver" } },
      { path: "devoluciones-proveedor",    name: "devoluciones-proveedor",    component: DevolucionProveedor,                                           meta: { title: "Devoluciones a proveedor",  permiso: "compras.crear" } },

      { path: "caja",          name: "caja",          component: CorteCaja,      meta: { title: "Corte de caja",   permiso: "caja.abrir" } },
      { path: "cortes-caja",   name: "cortes-caja",   component: HistorialCortes,meta: { title: "Cortes de caja",  permiso: "caja.historial" } },
      { path: "corte-detalle/:id", name: "corte-detalle", component: CorteDetalle, props: true, meta: { title: "Detalle de corte", permiso: "caja.historial" } },

      { path: "reportes-caja",           name: "reportes-caja",           component: ReporteCaja,       meta: { title: "Reporte de caja",     permiso: "reportes.ver" } },
      { path: "reportes-ventas",         name: "reportes-ventas",         component: ReporteVentas,     meta: { title: "Consulta de ventas",  permiso: "ventas.ver" } },
      { path: "reportes-ventas-agrupado",name: "reportes-ventas-agrupado",component: VentasAgrupado,    meta: { title: "Ventas agrupadas",    permiso: "ventas.ver" } },
      { path: "reportes-utilidades",     name: "reportes-utilidades",     component: ReporteUtilidades, meta: { title: "Reporte de utilidades",permiso: "reportes.utilidades" } },

      { path: "usuarios", name: "usuarios", component: UsuariosIndex, meta: { title: "Usuarios",permiso: "usuarios.gestionar" } },
      { path: "roles",    name: "roles",    component: RolesIndex,    meta: { title: "Roles y permisos", permiso: "usuarios.gestionar" } },
      { path: "perfil",   name: "perfil",   component: PerfilIndex,   meta: { title: "Mi perfil" } },

      { path: "sin-permiso", name: "sin-permiso", component: SinPermiso, meta: { title: "Sin permiso" } },
    ],
  },
  {
    path: "/",
    component: GuestLayout,
    meta: { guest: true },
    children: [
      { path: "login",    name: "login",    component: Login,    meta: { title: "Iniciar sesion" } },
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

  // Verificar permiso de ruta
  if (to.meta.permiso && auth.isAuth && !auth.can(to.meta.permiso)) {
    return { name: "sin-permiso" };
  }

  return true;
});

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | BuhoPOS` : "BuhoPOS";
});

export default router;
