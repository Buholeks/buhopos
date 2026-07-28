<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventario_ajustes_rapidos MODIFY tipo ENUM('entrada','salida','mixto') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('inventario_ajustes_rapidos')->where('tipo', 'mixto')->update(['tipo' => 'salida']);
            DB::statement("ALTER TABLE inventario_ajustes_rapidos MODIFY tipo ENUM('entrada','salida') NOT NULL");
        }
    }
};
