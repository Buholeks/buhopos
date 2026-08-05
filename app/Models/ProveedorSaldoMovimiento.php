<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProveedorSaldoMovimiento extends Model
{
    public const TIPOS_CREDITO = ['credito', 'ajuste_credito', 'reverso_aplicacion'];

    protected $fillable = [
        'empresa_id', 'sucursal_id', 'proveedor_id', 'user_id',
        'devolucion_proveedor_id', 'compra_id', 'tipo', 'monto',
        'saldo_resultante', 'concepto',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'saldo_resultante' => 'decimal:2',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function devolucion()
    {
        return $this->belongsTo(DevolucionProveedor::class, 'devolucion_proveedor_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getImporteFirmadoAttribute(): float
    {
        return in_array($this->tipo, self::TIPOS_CREDITO, true)
            ? (float) $this->monto
            : -(float) $this->monto;
    }

    public static function expresionSaldo(string $alias = 'proveedor_saldo_movimientos'): string
    {
        return "CASE WHEN {$alias}.tipo IN ('credito','ajuste_credito','reverso_aplicacion') THEN {$alias}.monto ELSE -{$alias}.monto END";
    }
}
