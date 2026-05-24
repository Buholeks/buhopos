<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Campo en productos ─────────────────────────────────────────────
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('tiene_series')->default(false)->after('tiene_variantes');
        });

        // ── 2. Crear tabla series ─────────────────────────────────────────────
        //    Debe existir ANTES de que venta_detalles la referencie (FK circular).
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('variante_id')->nullable();
            $table->unsignedBigInteger('compra_id')->nullable();
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->unsignedBigInteger('venta_detalle_id')->nullable(); // FK se agrega en paso 4

            $table->string('imei', 20)->nullable();
            $table->string('imei2', 20)->nullable();
            $table->string('serie', 100)->nullable();

            $table->decimal('precio_costo', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->nullable();

            $table->enum('estado', ['disponible', 'vendido', 'apartado', 'devuelto'])
                  ->default('disponible');

            $table->text('notas')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['empresa_id', 'sucursal_id', 'estado']);
            $table->index(['empresa_id', 'producto_id', 'estado']);
            $table->index('imei');
            $table->index('serie');
            $table->unique(['empresa_id', 'imei']);

            // FKs (excepto venta_detalle_id, que se agrega en paso 4)
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('sucursal_id')->references('id')->on('sucursales');
            $table->foreign('producto_id')->references('id')->on('productos');
            $table->foreign('variante_id')->references('id')->on('producto_variantes')->nullOnDelete();
            $table->foreign('compra_id')->references('id')->on('compras')->nullOnDelete();
            $table->foreign('proveedor_id')->references('id')->on('proveedores')->nullOnDelete();
            $table->foreign('venta_id')->references('id')->on('ventas')->nullOnDelete();
        });

        // ── 3. Agregar serie_id a venta_detalles ─────────────────────────────
        //    series ya existe, así que la FK es válida.
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('serie_id')->nullable()->after('variante_id');
            $table->foreign('serie_id')->references('id')->on('series')->nullOnDelete();
        });

        // ── 4. Cerrar FK circular: series.venta_detalle_id → venta_detalles ──
        //    venta_detalles.serie_id ya existe, así que esta FK es válida.
        Schema::table('series', function (Blueprint $table) {
            $table->foreign('venta_detalle_id')->references('id')->on('venta_detalles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Romper FK circular primero
        Schema::table('series', function (Blueprint $table) {
            $table->dropForeign(['venta_detalle_id']);
        });

        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropForeign(['serie_id']);
            $table->dropColumn('serie_id');
        });

        Schema::dropIfExists('series');

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('tiene_series');
        });
    }
};