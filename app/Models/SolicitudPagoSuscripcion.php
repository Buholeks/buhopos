<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudPagoSuscripcion extends Model
{
    protected $hidden = ['comprobante_ruta'];
    protected $table = 'solicitudes_pago_suscripcion';
    protected $fillable = ['empresa_id', 'suscripcion_id', 'plan_id', 'cuenta_cobro_id', 'pago_suscripcion_id', 'revisado_por', 'metodo', 'importe', 'moneda', 'referencia_unica', 'periodo_inicio', 'periodo_fin', 'fecha_limite', 'fecha_reportada', 'numero_operacion', 'comprobante_ruta', 'comprobante_nombre', 'comprobante_mime', 'estado', 'notas_cliente', 'notas_revision', 'revisado_en'];
    protected function casts(): array { return ['importe'=>'decimal:2','periodo_inicio'=>'date','periodo_fin'=>'date','fecha_limite'=>'datetime','fecha_reportada'=>'date','revisado_en'=>'datetime']; }
    public function empresa() { return $this->belongsTo(Empresa::class); }
    public function suscripcion() { return $this->belongsTo(Suscripcion::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
    public function cuentaCobro() { return $this->belongsTo(CuentaCobroPlataforma::class, 'cuenta_cobro_id'); }
    public function pago() { return $this->belongsTo(PagoSuscripcion::class, 'pago_suscripcion_id'); }
}
