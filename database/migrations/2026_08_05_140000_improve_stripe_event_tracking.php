<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stripe_eventos', function (Blueprint $table) {
            $table->string('estado', 20)->default('recibido')->after('tipo')->index();
            $table->unsignedSmallInteger('intentos')->default(0)->after('estado');
            $table->string('objeto_id')->nullable()->after('intentos')->index();
            $table->boolean('livemode')->default(false)->after('objeto_id');
            $table->text('ultimo_error')->nullable()->after('livemode');
            $table->timestamp('recibido_en')->nullable()->after('ultimo_error');
            $table->timestamp('procesado_en')->nullable()->change();
        });
        DB::table('stripe_eventos')->update(['estado' => 'procesado', 'recibido_en' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('stripe_eventos', function (Blueprint $table) {
            $table->dropIndex(['estado']); $table->dropIndex(['objeto_id']);
            $table->dropColumn(['estado', 'intentos', 'objeto_id', 'livemode', 'ultimo_error', 'recibido_en']);
        });
    }
};
