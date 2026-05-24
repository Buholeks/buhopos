<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Proveedor;
use App\Models\CompraDetalleProveedor;
use App\Models\AbonoProveedor;

class CompraProveedor extends Model
{
    protected $table = 'compras_proveedor';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'proveedor_id',
        'folio',
        'fecha_compra',
        'tipo_pago',
        'total',
        'pagado_inicial',
        'saldo',
        'fecha_vencimiento',
        'estatus',
        'observaciones',
    ];

    protected $casts = [
        'fecha_compra' => 'date',
        'fecha_vencimiento' => 'date',
        'total' => 'decimal:2',
        'pagado_inicial' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function detalles()
    {
        return $this->hasMany(CompraDetalleProveedor::class, 'compra_id');
    }

    public function abonos()
    {
        return $this->hasMany(AbonoProveedor::class, 'compra_id');
    }
}
