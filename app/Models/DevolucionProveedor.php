<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionProveedor extends Model
{
    protected $table = 'devoluciones_proveedor';

    protected $fillable = [
        'empresa_id', 'sucursal_id', 'compra_id', 'proveedor_id', 'user_id',
        'fecha', 'referencia', 'motivo', 'total', 'aplicado_saldo',
        'reembolso_pendiente', 'estado',
        'destino_excedente', 'forma_reembolso', 'movimiento_caja_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:2',
        'aplicado_saldo' => 'decimal:2',
        'reembolso_pendiente' => 'decimal:2',
    ];

    public function detalles()
    {
        return $this->hasMany(DevolucionProveedorDetalle::class);
    }
}
