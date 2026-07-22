<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_exhibiciones', function (Blueprint $table) {
            $table->unsignedBigInteger('atributo_id')->nullable()->after('variante_id');
            $table->index(['empresa_id', 'sucursal_id', 'producto_id', 'atributo_id', 'activo'], 'idx_inv_exh_color_activa');
        });

        DB::statement("ALTER TABLE inventario_exhibiciones MODIFY tipo_cobertura ENUM('producto', 'color', 'variante') NOT NULL DEFAULT 'producto'");

        Schema::table('inventario_exhibiciones', function (Blueprint $table) {
            $table->foreign('atributo_id')
                ->references('id')
                ->on('atributos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventario_exhibiciones', function (Blueprint $table) {
            $table->dropForeign(['atributo_id']);
            $table->dropIndex('idx_inv_exh_color_activa');
            $table->dropColumn('atributo_id');
        });

        DB::statement("ALTER TABLE inventario_exhibiciones MODIFY tipo_cobertura ENUM('producto', 'variante') NOT NULL DEFAULT 'producto'");
    }
};
