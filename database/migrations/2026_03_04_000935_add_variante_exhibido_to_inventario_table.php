<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario', function (Blueprint $table) {
            // Qué variante específica está en piso (null = sin variante o no exhibido)
            $table->unsignedBigInteger('variante_exhibida_id')
                  ->nullable()
                  ->after('exhibido');

            // Condición física del exhibido (null = no está exhibido)
            $table->enum('estado_exhibicion', ['perfecto', 'caja_abierta', 'con_detalles'])
                  ->nullable()
                  ->after('variante_exhibida_id');

            $table->foreign('variante_exhibida_id')
                  ->references('id')
                  ->on('producto_variantes')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventario', function (Blueprint $table) {
            $table->dropForeign(['variante_exhibida_id']);
            $table->dropColumn(['variante_exhibida_id', 'estado_exhibicion']);
        });
    }
};