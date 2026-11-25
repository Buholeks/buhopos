<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Support\Carbon;

class EstadoEmpresaService
{
    public function obtener(Empresa $empresa): array
    {
        $s = $empresa->suscripcion;
        $hoy = Carbon::today();
        $dias = $s?->fecha_vencimiento ? (int) $hoy->diffInDays($s->fecha_vencimiento, false) : null;
        $estado = $s?->estado ?? 'sin_suscripcion';
        if ($s && in_array($estado, ['activa', 'prueba'], true) && $dias !== null && $dias < 0) {
            $estado = 'vencida';
        }
        $permitido = (bool) $empresa->activo && (! $s || $s->permiteAcceso());
        $promocional = $s?->acceso_promocional_hasta && $hoy->lte($s->acceso_promocional_hasta);
        $gracia = $s && $dias !== null && $dias < 0 && $s->permiteAcceso() && ! $promocional;
        $motivo = match (true) {
            ! $empresa->activo => 'Empresa suspendida manualmente',
            ! $s => 'Acceso permitido por compatibilidad: sin suscripción',
            (bool) $promocional => 'Acceso promocional hasta '.$s->acceso_promocional_hasta->format('d/m/Y'),
            (bool) $gracia => 'Período de gracia hasta '.$s->fecha_vencimiento->copy()->addDays($s->dias_gracia)->format('d/m/Y'),
            ! $permitido => 'Suscripción '.$estado.': acceso bloqueado',
            default => 'Suscripción vigente',
        };

        return [
            'suscripcion' => $estado,
            'acceso_permitido' => $permitido,
            'motivo' => $motivo,
            'en_gracia' => (bool) $gracia,
            'por_vencer' => in_array($estado, ['activa', 'prueba'], true) && $dias !== null && $dias >= 0 && $dias <= 7,
            'vigencia' => $dias === null ? ($s ? 'Sin fecha de vencimiento' : 'Sin suscripción') : match (true) {
                $dias < 0 => 'Venció hace '.abs($dias).' días',
                $dias === 0 => 'Vence hoy',
                default => 'Vence en '.$dias.' días',
            },
        ];
    }
}
