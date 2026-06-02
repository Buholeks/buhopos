<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            if (! Schema::hasColumn('venta_detalles', 'producto_nombre')) {
                $table->string('producto_nombre')->nullable()->after('producto_id');
            }

            if (! Schema::hasColumn('venta_detalles', 'variante_nombre')) {
                $table->string('variante_nombre')->nullable()->after('variante_id');
            }

            if (! Schema::hasColumn('venta_detalles', 'precio_lista_original')) {
                $table->decimal('precio_lista_original', 12, 2)->nullable()->after('precio_costo');
            }

            if (! Schema::hasColumn('venta_detalles', 'precio_aplicado')) {
                $table->decimal('precio_aplicado', 12, 2)->nullable()->after('precio_lista_original');
            }

            if (! Schema::hasColumn('venta_detalles', 'lista_precio_usada')) {
                $table->string('lista_precio_usada', 30)->nullable()->after('precio_aplicado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $columnas = [
                'producto_nombre',
                'variante_nombre',
                'precio_lista_original',
                'precio_aplicado',
                'lista_precio_usada',
            ];

            foreach ($columnas as $columna) {
                if (Schema::hasColumn('venta_detalles', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
