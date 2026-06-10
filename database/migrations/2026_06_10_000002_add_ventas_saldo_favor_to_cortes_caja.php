<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cortes_caja', function (Blueprint $table) {
            $table->decimal('ventas_saldo_favor', 14, 2)->default(0)->after('ventas_credito');
        });
    }

    public function down(): void
    {
        Schema::table('cortes_caja', function (Blueprint $table) {
            $table->dropColumn('ventas_saldo_favor');
        });
    }
};
