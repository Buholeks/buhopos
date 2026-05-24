<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraDetalleProveedor extends Model
{
    protected $table = 'compra_detalles_proveedor';

    protected $fillable = [
        'compra_id','producto_id','descripcion',
        'cantidad','costo_unitario','subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'costo_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function compra()
    {
        return $this->belongsTo(CompraProveedor::class, 'compra_id');
    }
}
