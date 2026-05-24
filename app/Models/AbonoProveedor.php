<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Proveedor;

class AbonoProveedor extends Model
{
    protected $table = 'abonos_proveedor';

    protected $fillable = [
        'empresa_id','sucursal_id','user_id',
        'proveedor_id','compra_id','fecha','monto',
        'metodo_pago','referencia','nota',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function compra()
    {
        return $this->belongsTo(CompraProveedor::class, 'compra_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}
