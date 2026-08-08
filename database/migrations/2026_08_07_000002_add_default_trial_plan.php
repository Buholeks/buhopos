<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->boolean('es_prueba')->default(false)->after('activo')->index();
        });

        if (! DB::table('planes')->where('es_prueba', true)->exists()) {
            DB::table('planes')->insert([
                'nombre' => 'Prueba',
                'descripcion' => 'Plan interno para los primeros 15 días de acceso.',
                'precio_mensual' => 0,
                'sucursales_incluidas' => 1,
                'usuarios_incluidos' => 1,
                'precio_sucursal_adicional' => 0,
                'activo' => true,
                'es_prueba' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('planes')->where('es_prueba', true)->delete();
        Schema::table('planes', fn (Blueprint $table) => $table->dropColumn('es_prueba'));
    }
};
