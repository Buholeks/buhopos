<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('corte_id')->nullable()->after('sucursal_id');
            $table->index(['empresa_id','sucursal_id','corte_id']);
            $table->foreign('corte_id')->references('id')->on('cortes_caja')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['corte_id']);
            $table->dropIndex(['empresa_id','sucursal_id','corte_id']);
            $table->dropColumn('corte_id');
        });
    }
};