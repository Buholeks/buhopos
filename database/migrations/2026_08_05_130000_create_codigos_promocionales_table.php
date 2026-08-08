<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->date('acceso_promocional_hasta')->nullable()->after('fecha_vencimiento');
        });
        Schema::create('codigos_promocionales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('descripcion')->nullable();
            $table->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            $table->foreignId('empresa_destino_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->enum('duracion_tipo', ['dias', 'meses']);
            $table->unsignedSmallInteger('duracion_cantidad');
            $table->timestamp('expira_en')->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('creado_por_id')->nullable()->constrained('plataforma_administradores')->nullOnDelete();
            $table->foreignId('canjeado_por_empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('canjeado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('canjeado_en')->nullable();
            $table->timestamps();
            $table->index(['activo', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_promocionales');
        Schema::table('suscripciones', fn (Blueprint $table) => $table->dropColumn('acceso_promocional_hasta'));
    }
};
