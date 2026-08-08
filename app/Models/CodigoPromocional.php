<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodigoPromocional extends Model
{
    protected $table = 'codigos_promocionales';
    protected $fillable = ['codigo', 'descripcion', 'plan_id', 'empresa_destino_id', 'duracion_tipo', 'duracion_cantidad', 'expira_en', 'activo', 'creado_por_id', 'canjeado_por_empresa_id', 'canjeado_por_user_id', 'canjeado_en'];
    protected function casts(): array { return ['expira_en' => 'datetime', 'canjeado_en' => 'datetime', 'activo' => 'boolean']; }
    public function plan() { return $this->belongsTo(Plan::class); }
    public function empresaDestino() { return $this->belongsTo(Empresa::class, 'empresa_destino_id'); }
    public function empresaCanje() { return $this->belongsTo(Empresa::class, 'canjeado_por_empresa_id'); }
}
