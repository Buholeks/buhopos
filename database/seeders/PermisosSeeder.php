<?php

namespace Database\Seeders;

use App\Models\Permiso;
use Illuminate\Database\Seeder;

class PermisosSeeder extends Seeder
{
    /**
     * Lista maestra de permisos del sistema.
     * Esta tabla la controla el sistema, no los usuarios.
     * Se usa updateOrCreate para que sea seguro correr múltiples veces.
     */
    public function run(): void
    {
        $permisos = [
            // ── Ventas ────────────────────────────────────────────────────────
            ['modulo' => 'ventas',     'clave' => 'ventas.ver',               'descripcion' => 'Ver historial de ventas'],
            ['modulo' => 'ventas',     'clave' => 'ventas.crear',             'descripcion' => 'Realizar ventas'],
            ['modulo' => 'ventas',     'clave' => 'ventas.cancelar',          'descripcion' => 'Cancelar ventas'],
            ['modulo' => 'ventas',     'clave' => 'ventas.descuento',         'descripcion' => 'Aplicar descuentos en ventas'],

            // ── Caja ──────────────────────────────────────────────────────────
            ['modulo' => 'caja',       'clave' => 'caja.abrir',               'descripcion' => 'Abrir corte de caja'],
            ['modulo' => 'caja',       'clave' => 'caja.cerrar',              'descripcion' => 'Cerrar corte de caja'],
            ['modulo' => 'caja',       'clave' => 'caja.historial',           'descripcion' => 'Ver historial de cortes de caja'],

            // ── Compras ───────────────────────────────────────────────────────
            ['modulo' => 'compras',    'clave' => 'compras.ver',              'descripcion' => 'Consultar compras registradas'],
            ['modulo' => 'compras',    'clave' => 'compras.crear',            'descripcion' => 'Registrar nuevas compras'],

            // ── Productos ─────────────────────────────────────────────────────
            ['modulo' => 'productos',  'clave' => 'productos.ver',            'descripcion' => 'Ver catálogo de productos'],
            ['modulo' => 'productos',  'clave' => 'productos.editar',         'descripcion' => 'Crear y editar productos'],
            ['modulo' => 'productos',  'clave' => 'productos.eliminar',       'descripcion' => 'Eliminar productos del catálogo'],
            ['modulo' => 'productos',  'clave' => 'productos.precios',        'descripcion' => 'Ver y editar precios de costo'],

            // ── Pedidos ───────────────────────────────────────────────────────
            ['modulo' => 'pedidos',    'clave' => 'pedidos.ver',              'descripcion' => 'Consultar pedidos y apartados'],
            ['modulo' => 'pedidos',    'clave' => 'pedidos.crear',            'descripcion' => 'Crear nuevos pedidos y apartados'],
            ['modulo' => 'pedidos',    'clave' => 'pedidos.cancelar',         'descripcion' => 'Cancelar pedidos y apartados'],

            // ── Clientes ──────────────────────────────────────────────────────
            ['modulo' => 'clientes',   'clave' => 'clientes.ver',             'descripcion' => 'Ver listado y datos de clientes'],
            ['modulo' => 'clientes',   'clave' => 'clientes.editar',          'descripcion' => 'Crear y editar clientes'],

            // ── Reportes ──────────────────────────────────────────────────────
            ['modulo' => 'reportes',   'clave' => 'reportes.ver',             'descripcion' => 'Acceder a reportes generales'],
            ['modulo' => 'reportes',   'clave' => 'reportes.utilidades',      'descripcion' => 'Ver reportes con costos y utilidades'],

            // ── Inventario ────────────────────────────────────────────────────
            ['modulo' => 'inventario', 'clave' => 'inventario.ver',           'descripcion' => 'Ver exhibición de productos'],
            ['modulo' => 'inventario', 'clave' => 'inventario.traspasos',     'descripcion' => 'Realizar traspasos entre sucursales'],

            // ── Catálogos ─────────────────────────────────────────────────────
            ['modulo' => 'catalogos',  'clave' => 'catalogos.ver',            'descripcion' => 'Ver catálogos: proveedores, categorías, marcas, atributos'],
            ['modulo' => 'catalogos',  'clave' => 'catalogos.editar',         'descripcion' => 'Crear y editar registros de catálogos'],

            // ── Usuarios ──────────────────────────────────────────────────────
            ['modulo' => 'usuarios',   'clave' => 'usuarios.gestionar',       'descripcion' => 'Gestionar usuarios, roles y permisos'],
        ];

        foreach ($permisos as $permiso) {
            Permiso::updateOrCreate(
                ['clave' => $permiso['clave']],
                ['modulo' => $permiso['modulo'], 'descripcion' => $permiso['descripcion']]
            );
        }
    }
}
