<?php

namespace App\Servicios;

use App\Models\KardexMovimiento;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;

class KardexServicio
{
    public function registrar(array $datos): KardexMovimiento
    {
        $direccion = $datos['direccion'] ?? 'neutro';
        $cantidad = abs((float) ($datos['cantidad'] ?? 0));

        $datos['cantidad'] = $cantidad;
        $datos['direccion'] = $direccion;
        $datos['entrada'] = (float) ($datos['entrada'] ?? ($direccion === 'entrada' ? $cantidad : 0));
        $datos['salida'] = (float) ($datos['salida'] ?? ($direccion === 'salida' ? $cantidad : 0));
        $datos['stock_antes'] = (float) ($datos['stock_antes'] ?? 0);
        $datos['stock_despues'] = (float) ($datos['stock_despues'] ?? $datos['stock_antes']);
        $datos['fecha'] = $this->normalizarFecha($datos['fecha'] ?? null);

        return KardexMovimiento::create(Arr::only($datos, [
            'empresa_id',
            'sucursal_id',
            'producto_id',
            'variante_id',
            'serie_id',
            'user_id',
            'tipo',
            'direccion',
            'cantidad',
            'entrada',
            'salida',
            'stock_antes',
            'stock_despues',
            'costo_unitario',
            'precio_unitario',
            'importe',
            'referencia_tipo',
            'referencia_id',
            'referencia_detalle_id',
            'folio',
            'motivo',
            'notas',
            'metadata',
            'fecha',
        ]));
    }

    private function normalizarFecha(mixed $fecha): string
    {
        $timezone = 'America/Mexico_City';

        if ($fecha instanceof CarbonInterface) {
            return $fecha->copy()->timezone($timezone)->format('Y-m-d H:i:s');
        }

        if ($fecha) {
            return Carbon::parse($fecha, $timezone)->format('Y-m-d H:i:s');
        }

        return now($timezone)->format('Y-m-d H:i:s');
    }
}
