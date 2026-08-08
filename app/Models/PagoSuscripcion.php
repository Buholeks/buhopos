<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoSuscripcion extends Model
{
    protected $table = 'pagos_suscripcion';

    protected $fillable = [
        'suscripcion_id', 'stripe_invoice_id', 'stripe_payment_intent_id', 'registrado_por', 'importe', 'fecha_pago', 'periodo_inicio',
        'periodo_fin', 'metodo', 'referencia', 'estado', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
            'fecha_pago' => 'date',
            'periodo_inicio' => 'date',
            'periodo_fin' => 'date',
        ];
    }

    public function suscripcion()
    {
        return $this->belongsTo(Suscripcion::class);
    }

    public function administrador()
    {
        return $this->belongsTo(PlataformaAdministrador::class, 'registrado_por');
    }
}
