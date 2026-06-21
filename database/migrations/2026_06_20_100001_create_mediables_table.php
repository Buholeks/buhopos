<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('mediable_type', 255);
            $table->unsignedBigInteger('mediable_id');
            $table->string('role', 50)->default('imagen'); // 'imagen', 'logo'
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id']);
            $table->unique(['media_id', 'mediable_type', 'mediable_id', 'role'], 'mediables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediables');
    }
};
