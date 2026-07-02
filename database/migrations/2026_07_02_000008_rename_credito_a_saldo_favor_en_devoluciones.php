<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE devoluciones MODIFY forma_devolucion ENUM('efectivo','tarjeta','transferencia','credito','saldo_favor') DEFAULT 'efectivo'");
        DB::table('devoluciones')->where('forma_devolucion', 'credito')->update(['forma_devolucion' => 'saldo_favor']);
        DB::statement("ALTER TABLE devoluciones MODIFY forma_devolucion ENUM('efectivo','tarjeta','transferencia','saldo_favor') DEFAULT 'efectivo'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE devoluciones MODIFY forma_devolucion ENUM('efectivo','tarjeta','transferencia','saldo_favor','credito') DEFAULT 'efectivo'");
        DB::table('devoluciones')->where('forma_devolucion', 'saldo_favor')->update(['forma_devolucion' => 'credito']);
        DB::statement("ALTER TABLE devoluciones MODIFY forma_devolucion ENUM('efectivo','tarjeta','transferencia','credito') DEFAULT 'efectivo'");
    }
};
