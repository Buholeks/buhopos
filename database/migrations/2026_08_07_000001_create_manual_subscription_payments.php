<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cuentas_cobro_plataforma', function (Blueprint $table) {
            $table->id();
            $table->string('banco');
            $table->string('beneficiario');
            $table->string('clabe', 18)->nullable();
            $table->string('numero_cuenta', 40)->nullable();
            $table->text('instrucciones')->nullable();
            $table->boolean('activa')->default(true);
            $table->boolean('predeterminada')->default(false);
            $table->timestamps();
        });

        Schema::create('solicitudes_pago_suscripcion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('suscripcion_id')->constrained('suscripciones')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('planes');
            $table->foreignId('cuenta_cobro_id')->constrained('cuentas_cobro_plataforma');
            $table->foreignId('pago_suscripcion_id')->nullable()->constrained('pagos_suscripcion')->nullOnDelete();
            $table->foreignId('revisado_por')->nullable()->constrained('plataforma_administradores')->nullOnDelete();
            $table->enum('metodo', ['transferencia', 'deposito']);
            $table->decimal('importe', 12, 2);
            $table->string('moneda', 3)->default('MXN');
            $table->string('referencia_unica', 40)->unique();
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->dateTime('fecha_limite');
            $table->date('fecha_reportada')->nullable();
            $table->string('numero_operacion', 120)->nullable();
            $table->string('comprobante_ruta')->nullable();
            $table->string('comprobante_nombre')->nullable();
            $table->string('comprobante_mime', 100)->nullable();
            $table->enum('estado', ['pendiente', 'en_revision', 'confirmado', 'rechazado', 'vencido', 'cancelado'])->default('pendiente');
            $table->text('notas_cliente')->nullable();
            $table->text('notas_revision')->nullable();
            $table->dateTime('revisado_en')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'estado']);
        });

        Schema::table('suscripciones', function (Blueprint $table) {
            $table->enum('modalidad_cobro', ['stripe', 'manual'])->default('stripe')->after('stripe_status');
        });
    }

    public function down(): void
    {
        Schema::table('suscripciones', fn (Blueprint $table) => $table->dropColumn('modalidad_cobro'));
        Schema::dropIfExists('solicitudes_pago_suscripcion');
        Schema::dropIfExists('cuentas_cobro_plataforma');
    }
};
