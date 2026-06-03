<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE traspasos MODIFY estado ENUM('completado','pendiente','recibido','rechazado','cancelado') NOT NULL DEFAULT 'pendiente'");

        DB::table('traspasos')
            ->where('estado', 'completado')
            ->update(['estado' => 'recibido']);

        DB::statement("ALTER TABLE traspasos MODIFY estado ENUM('pendiente','recibido','rechazado','cancelado') NOT NULL DEFAULT 'pendiente'");

        Schema::table('traspasos', function (Blueprint $table) {
            $table->foreignId('recibido_por')->nullable()->after('cancelado_por')->constrained('users')->nullOnDelete();
            $table->foreignId('rechazado_por')->nullable()->after('recibido_por')->constrained('users')->nullOnDelete();
            $table->timestamp('recibido_at')->nullable()->after('notas');
            $table->timestamp('rechazado_at')->nullable()->after('recibido_at');
            $table->text('motivo_rechazo')->nullable()->after('motivo_cancelacion');
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE traspasos MODIFY estado ENUM('completado','pendiente','recibido','rechazado','cancelado') NOT NULL DEFAULT 'pendiente'");

        DB::table('traspasos')
            ->where('estado', 'pendiente')
            ->update(['estado' => 'cancelado']);

        DB::table('traspasos')
            ->where('estado', 'rechazado')
            ->update(['estado' => 'cancelado']);

        DB::table('traspasos')
            ->where('estado', 'recibido')
            ->update(['estado' => 'completado']);

        Schema::table('traspasos', function (Blueprint $table) {
            $table->dropForeign(['recibido_por']);
            $table->dropForeign(['rechazado_por']);
            $table->dropColumn([
                'recibido_por',
                'rechazado_por',
                'recibido_at',
                'rechazado_at',
                'motivo_rechazo',
            ]);
        });

        DB::statement("ALTER TABLE traspasos MODIFY estado ENUM('completado','cancelado') NOT NULL DEFAULT 'completado'");
    }
};
