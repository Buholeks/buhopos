<?php
// app/Models/Cliente.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'nombre',
        'correo',
        'telefono',
        'direccion',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ventas()
{
    return $this->hasMany(Venta::class, 'cliente_id');
}
}
