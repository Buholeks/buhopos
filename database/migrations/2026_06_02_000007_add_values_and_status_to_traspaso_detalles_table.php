<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traspaso_detalles', function (Blueprint $table) {
            $table->decimal('precio_costo', 12, 2)->default(0)->after('cantidad');
            $table->decimal('precio_venta', 12, 2)->default(0)->after('precio_costo');
            $table->decimal('cantidad_recibida', 12, 3)->default(0)->after('precio_venta');
            $table->enum('estado', ['pendiente', 'recibido', 'rechazado'])->default('pendiente')->after('cantidad_recibida');
        });

        DB::table('traspaso_detalles as d')
            ->join('traspasos as t', 't.id', '=', 'd.traspaso_id')
            ->where('t.estado', 'recibido')
            ->update([
                'd.estado' => 'recibido',
                'd.cantidad_recibida' => DB::raw('d.cantidad'),
            ]);

        DB::table('traspaso_detalles as d')
            ->join('traspasos as t', 't.id', '=', 'd.traspaso_id')
            ->whereIn('t.estado', ['rechazado', 'cancelado'])
            ->update(['d.estado' => 'rechazado']);
    }

    public function down(): void
    {
        Schema::table('traspaso_detalles', function (Blueprint $table) {
            $table->dropColumn(['precio_costo', 'precio_venta', 'cantidad_recibida', 'estado']);
        });
    }
};
