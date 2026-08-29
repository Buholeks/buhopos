<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'nombre', 'descripcion', 'precio_mensual', 'precio_anual', 'sucursales_incluidas',
        'usuarios_incluidos', 'precio_sucursal_adicional', 'activo', 'es_prueba',
        'stripe_product_id', 'stripe_price_id', 'stripe_price_anual_id', 'stripe_sincronizado_en',
    ];

    protected function casts(): array
    {
        return [
            'precio_mensual' => 'decimal:2',
            'precio_anual' => 'decimal:2',
            'precio_sucursal_adicional' => 'decimal:2',
            'activo' => 'boolean',
            'es_prueba' => 'boolean',
            'stripe_sincronizado_en' => 'datetime',
        ];
    }

    public function precioPara(string $periodicidad): string
    {
        return (string) ($periodicidad === 'anual' ? $this->precio_anual : $this->precio_mensual);
    }

    public function stripePricePara(string $periodicidad): ?string
    {
        return $periodicidad === 'anual' ? $this->stripe_price_anual_id : $this->stripe_price_id;
    }

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class);
    }
}
