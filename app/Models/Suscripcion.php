<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Suscripcion extends Model
{
    protected $table = 'suscripciones';

    protected $fillable = [
        'empresa_id', 'stripe_subscription_id', 'stripe_schedule_id', 'plan_id', 'periodicidad', 'plan_pendiente_id', 'periodicidad_pendiente', 'estado', 'stripe_status', 'modalidad_cobro', 'cancelar_al_final', 'fecha_inicio', 'fecha_vencimiento', 'cambio_plan_en',
        'dias_gracia', 'precio_acordado', 'notas', 'cancelada_en', 'acceso_promocional_hasta',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_vencimiento' => 'date',
            'acceso_promocional_hasta' => 'date',
            'cambio_plan_en' => 'date',
            'cancelada_en' => 'datetime',
            'precio_acordado' => 'decimal:2',
            'cancelar_al_final' => 'boolean',
        ];
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function planPendiente()
    {
        return $this->belongsTo(Plan::class, 'plan_pendiente_id');
    }

    public function pagos()
    {
        return $this->hasMany(PagoSuscripcion::class);
    }

    public function solicitudesPago()
    {
        return $this->hasMany(SolicitudPagoSuscripcion::class);
    }

    public function permiteAcceso(): bool
    {
        if ($this->acceso_promocional_hasta && Carbon::today()->lte($this->acceso_promocional_hasta)) return true;
        if (! in_array($this->estado, ['activa', 'prueba', 'vencida'], true)) {
            return false;
        }
        if (! $this->fecha_vencimiento) {
            return in_array($this->estado, ['activa', 'prueba'], true);
        }

        return Carbon::today()->lte($this->fecha_vencimiento->copy()->addDays($this->dias_gracia));
    }
}
