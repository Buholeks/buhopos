<?php

namespace Tests\Feature\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema mínimo de tablas para probar el módulo de comisiones en SQLite en memoria.
 */
trait CreaTablasComisiones
{
    protected function crearTablasComisiones(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->integer('empresa_id')->default(1); $t->integer('sucursal_id')->nullable();
            $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable();
            $t->boolean('activo')->default(true); $t->boolean('es_super_admin')->default(false);
            $t->integer('esquema_vendedor_id')->nullable(); $t->integer('esquema_cajero_id')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        Schema::create('categorias', function (Blueprint $t) {
            $t->id(); $t->integer('empresa_id')->default(1); $t->string('nombre')->nullable();
            $t->integer('categoria_padre_id')->nullable(); $t->boolean('activo')->default(true); $t->softDeletes();
        });
        Schema::create('productos', function (Blueprint $t) {
            $t->id(); $t->integer('empresa_id')->default(1); $t->string('nombre')->nullable(); $t->string('codigo')->nullable();
            $t->integer('categoria_id')->nullable(); $t->decimal('precio_costo', 12, 2)->nullable();
            foreach (['precio_venta', 'precio1', 'precio2', 'precio3', 'precio4', 'precio5'] as $c) {
                $t->decimal($c, 12, 2)->nullable();
            }
            $t->softDeletes();
        });
        Schema::create('ventas', function (Blueprint $t) {
            $t->id(); $t->integer('empresa_id'); $t->integer('sucursal_id'); $t->integer('user_id'); $t->integer('vendedor_id');
            $t->date('fecha'); $t->string('estado'); $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('descuento', 14, 2)->default(0); $t->decimal('total', 14, 2)->default(0);
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('venta_detalles', function (Blueprint $t) {
            $t->id(); $t->integer('venta_id'); $t->integer('producto_id'); $t->decimal('cantidad', 10, 3);
            $t->decimal('precio_venta', 12, 2); $t->decimal('precio_costo', 12, 2)->nullable();
            $t->decimal('subtotal', 14, 2); $t->string('lista_precio_usada')->nullable(); $t->timestamps();
        });
        Schema::create('devoluciones', function (Blueprint $t) {
            $t->id(); $t->integer('venta_id'); $t->string('estado')->default('confirmada'); $t->timestamps();
        });
        Schema::create('devolucion_detalles', function (Blueprint $t) {
            $t->id(); $t->integer('devolucion_id'); $t->integer('venta_detalle_id'); $t->decimal('cantidad', 10, 3); $t->timestamps();
        });
        Schema::create('comision_esquemas', function (Blueprint $t) {
            $t->id(); $t->integer('empresa_id'); $t->string('nombre'); $t->string('beneficiario'); $t->string('modo')->default('general');
            $t->string('descripcion')->nullable(); $t->boolean('activo')->default(true); $t->timestamps();
        });
        Schema::create('comision_reglas', function (Blueprint $t) {
            $t->id(); $t->integer('empresa_id'); $t->integer('esquema_id'); $t->string('aplica_a'); $t->integer('referencia_id')->nullable();
            $t->string('base')->default('venta'); $t->string('tipo')->default('porcentaje'); $t->decimal('valor', 12, 4)->default(0);
            $t->decimal('precio_min', 12, 2)->nullable(); $t->decimal('precio_max', 12, 2)->nullable();
            $t->string('fuera_de_rango')->default('usar_siguiente'); $t->boolean('activo')->default(true); $t->timestamps();
        });
        Schema::create('comision_regla_precios', function (Blueprint $t) {
            $t->id(); $t->integer('regla_id'); $t->string('lista'); $t->boolean('activo')->default(true);
            $t->string('tipo')->default('porcentaje'); $t->decimal('valor', 12, 4)->default(0); $t->timestamps();
        });
        Schema::create('comision_movimientos', function (Blueprint $t) {
            $t->id(); $t->integer('empresa_id'); $t->integer('sucursal_id')->nullable(); $t->integer('user_id');
            $t->string('beneficiario'); $t->string('tipo'); $t->date('fecha');
            $t->integer('venta_id')->nullable(); $t->integer('venta_detalle_id')->nullable(); $t->integer('devolucion_id')->nullable();
            $t->integer('esquema_id')->nullable(); $t->integer('regla_id')->nullable(); $t->string('lista_precio')->nullable();
            $t->string('base')->nullable(); $t->string('regla_tipo')->nullable(); $t->decimal('cantidad', 10, 3)->default(0);
            $t->decimal('base_calculo', 14, 2)->default(0); $t->decimal('tasa', 12, 4)->default(0); $t->decimal('monto', 14, 2);
            $t->string('concepto')->nullable(); $t->integer('creado_por')->nullable(); $t->integer('liquidacion_id')->nullable(); $t->timestamps();
        });
    }
}
