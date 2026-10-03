<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // modo: general (igual para todo) | categoria | producto.
        // Define qué reglas admite el esquema además de la regla general ("para lo demás").
        Schema::table('comision_esquemas', function (Blueprint $table) {
            $table->string('modo', 20)->default('general')->after('beneficiario');
        });
    }

    public function down(): void
    {
        Schema::table('comision_esquemas', function (Blueprint $table) {
            $table->dropColumn('modo');
        });
    }
};
