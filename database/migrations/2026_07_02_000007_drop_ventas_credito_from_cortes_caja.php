<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cortes_caja', function (Blueprint $table) {
            $table->dropColumn('ventas_credito');
        });
    }

    public function down(): void
    {
        Schema::table('cortes_caja', function (Blueprint $table) {
            $table->decimal('ventas_credito', 12, 2)->default(0);
        });
    }
};
