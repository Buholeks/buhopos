<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComisionReglaPrecio extends Model
{
    protected $table = 'comision_regla_precios';

    // Mismos valores que VentaDetalle.lista_precio_usada; MANUAL = precio que no coincide con ninguna lista.
    public const LISTA_MANUAL = 'MANUAL';
    public const LISTAS = ['P.Venta', 'P1', 'P2', 'P3', 'P4', 'P5', self::LISTA_MANUAL];

    protected $fillable = [
        'regla_id',
        'lista',
        'activo',
        'tipo',
        'valor',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'valor'  => 'decimal:4',
    ];

    public function regla(): BelongsTo
    {
        return $this->belongsTo(ComisionRegla::class, 'regla_id');
    }
}
