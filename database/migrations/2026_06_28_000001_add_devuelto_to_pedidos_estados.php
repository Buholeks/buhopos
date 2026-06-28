<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE pedidos MODIFY estado ENUM('pendiente','en_proceso','disponible','parcial','entregado','devuelto','cancelado','vencido') NOT NULL DEFAULT 'pendiente'");
        DB::statement("ALTER TABLE pedido_detalles MODIFY estado ENUM('pendiente','disponible','reservado','entregado','devuelto','cancelado') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('pedido_detalles')->where('estado', 'devuelto')->update(['estado' => 'entregado']);
        DB::table('pedidos')->where('estado', 'devuelto')->update(['estado' => 'entregado']);

        DB::statement("ALTER TABLE pedidos MODIFY estado ENUM('pendiente','en_proceso','disponible','parcial','entregado','cancelado','vencido') NOT NULL DEFAULT 'pendiente'");
        DB::statement("ALTER TABLE pedido_detalles MODIFY estado ENUM('pendiente','disponible','reservado','entregado','cancelado') NOT NULL DEFAULT 'pendiente'");
    }
};
