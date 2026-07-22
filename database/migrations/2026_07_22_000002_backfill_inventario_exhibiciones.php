<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('inventario')
            ->where('exhibido', true)
            ->orderBy('id')
            ->get()
            ->each(function ($inv) use ($now) {
                $existe = DB::table('inventario_exhibiciones')
                    ->where('empresa_id', $inv->empresa_id)
                    ->where('sucursal_id', $inv->sucursal_id)
                    ->where('producto_id', $inv->producto_id)
                    ->where('activo', true)
                    ->where('estado', 'activa')
                    ->when(
                        $inv->variante_exhibida_id,
                        fn($q) => $q->where('variante_id', $inv->variante_exhibida_id),
                        fn($q) => $q->whereNull('variante_id')
                    )
                    ->exists();

                if ($existe) {
                    return;
                }

                DB::table('inventario_exhibiciones')->insert([
                    'empresa_id' => $inv->empresa_id,
                    'sucursal_id' => $inv->sucursal_id,
                    'producto_id' => $inv->producto_id,
                    'variante_id' => $inv->variante_exhibida_id,
                    'user_id' => null,
                    'tipo_cobertura' => $inv->variante_exhibida_id ? 'variante' : 'producto',
                    'estado_exhibicion' => $inv->estado_exhibicion ?: 'perfecto',
                    'estado' => 'activa',
                    'activo' => true,
                    'venta_id' => null,
                    'venta_detalle_id' => null,
                    'vendido_at' => null,
                    'retirado_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('inventario_exhibiciones')
            ->whereNull('user_id')
            ->whereNull('venta_id')
            ->whereNull('venta_detalle_id')
            ->delete();
    }
};
