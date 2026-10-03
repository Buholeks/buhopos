<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Esquema = conjunto de reglas reutilizable que se asigna a usuarios.
        // beneficiario: vendedor | cajero (string validado en backend para poder crecer).
        Schema::create('comision_esquemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('beneficiario', 20);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre'], 'uq_comision_esquemas_empresa_nombre');
            $table->index(['empresa_id', 'beneficiario', 'activo'], 'idx_comision_esquemas_beneficiario');
        });

        // Regla dentro de un esquema. Orden de resolución: producto → categoria → general.
        // tipo/valor son el default cuando la regla no tiene tabla de precios.
        Schema::create('comision_reglas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('esquema_id')->constrained('comision_esquemas')->cascadeOnDelete();

            $table->string('aplica_a', 20);                         // general | categoria | producto
            $table->unsignedBigInteger('referencia_id')->nullable(); // categoria_id / producto_id

            $table->string('base', 20)->default('venta');           // venta | utilidad
            $table->string('tipo', 20)->default('porcentaje');      // porcentaje | fijo_por_pieza
            $table->decimal('valor', 12, 4)->default(0);

            $table->decimal('precio_min', 12, 2)->nullable();
            $table->decimal('precio_max', 12, 2)->nullable();
            $table->string('fuera_de_rango', 20)->default('usar_siguiente'); // usar_siguiente | sin_comision

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['esquema_id', 'aplica_a', 'referencia_id'], 'idx_comision_reglas_resolucion');
        });

        // Tabla de precios opcional por regla (P.Venta, P1..P5, MANUAL).
        // Fila con activo = false ⇒ ese precio no genera comisión ($0).
        Schema::create('comision_regla_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regla_id')->constrained('comision_reglas')->cascadeOnDelete();
            $table->string('lista', 20);
            $table->boolean('activo')->default(true);
            $table->string('tipo', 20)->default('porcentaje');
            $table->decimal('valor', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(['regla_id', 'lista'], 'uq_comision_regla_precios_lista');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_regla_precios');
        Schema::dropIfExists('comision_reglas');
        Schema::dropIfExists('comision_esquemas');
    }
};
