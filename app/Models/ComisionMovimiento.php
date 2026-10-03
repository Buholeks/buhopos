<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComisionMovimiento extends Model
{
    protected $table = 'comision_movimientos';

    public const TIPO_VENTA       = 'venta';
    public const TIPO_CANCELACION = 'cancelacion';
    public const TIPO_DEVOLUCION  = 'devolucion';
    public const TIPO_AJUSTE      = 'ajuste';
    public const TIPOS = [self::TIPO_VENTA, self::TIPO_CANCELACION, self::TIPO_DEVOLUCION, self::TIPO_AJUSTE];

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'beneficiario',
        'tipo',
        'fecha',
        'venta_id',
        'venta_detalle_id',
        'devolucion_id',
        'esquema_id',
        'regla_id',
        'lista_precio',
        'base',
        'regla_tipo',
        'cantidad',
        'base_calculo',
        'tasa',
        'monto',
        'concepto',
        'creado_por',
        'liquidacion_id',
    ];

    protected $casts = [
        'fecha'        => 'date',
        'cantidad'     => 'decimal:3',
        'base_calculo' => 'decimal:2',
        'tasa'         => 'decimal:4',
        'monto'        => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class);
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function esquema(): BelongsTo
    {
        return $this->belongsTo(ComisionEsquema::class, 'esquema_id');
    }

    public function regla(): BelongsTo
    {
        return $this->belongsTo(ComisionRegla::class, 'regla_id');
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(ComisionLiquidacion::class, 'liquidacion_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
