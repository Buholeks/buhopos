<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('folio_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();

            $table->string('serie', 20)->default('TKT'); // por si luego quieres FAC, NC, etc.
            $table->unsignedBigInteger('ultimo_numero')->default(1);

            $table->timestamps();

            $table->unique(['empresa_id', 'sucursal_id', 'serie'], 'uq_folio_counter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_counters');
    }
};
