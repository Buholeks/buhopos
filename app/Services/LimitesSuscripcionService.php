<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Plan;
use Illuminate\Validation\ValidationException;

class LimitesSuscripcionService
{
    public function uso(Empresa $empresa, ?Plan $plan = null): array
    {
        if (! $plan) {
            $suscripcion = $empresa->suscripcion;
            $plan = $suscripcion?->estado === 'prueba'
                ? Plan::where('es_prueba', true)->where('activo', true)->first()
                : $suscripcion?->plan;
        }

        return [
            'sucursales_activas' => $empresa->sucursales()->where('activo', true)->count(),
            'usuarios_activos' => $empresa->usuarios()->where('activo', true)->count(),
            'sucursales_limite' => $plan?->sucursales_incluidas,
            'usuarios_limite' => $plan?->usuarios_incluidos,
        ];
    }

    public function validarCambio(Empresa $empresa, Plan $plan): array
    {
        $uso = $this->uso($empresa, $plan);
        $errores = [];
        if ($uso['sucursales_limite'] !== null && $uso['sucursales_activas'] > $uso['sucursales_limite']) {
            $errores[] = "Tienes {$uso['sucursales_activas']} sucursales activas y el plan permite {$uso['sucursales_limite']}.";
        }
        if ($uso['usuarios_limite'] !== null && $uso['usuarios_activos'] > $uso['usuarios_limite']) {
            $errores[] = "Tienes {$uso['usuarios_activos']} usuarios activos y el plan permite {$uso['usuarios_limite']}.";
        }
        if ($errores) throw ValidationException::withMessages(['plan_id' => $errores]);

        return $uso;
    }

    public function validarNuevaSucursal(Empresa $empresa): void
    {
        $uso = $this->uso($empresa);
        if ($uso['sucursales_limite'] !== null && $uso['sucursales_activas'] >= $uso['sucursales_limite']) {
            throw ValidationException::withMessages(['sucursales' => 'Tu plan ya alcanzó el límite de sucursales activas.']);
        }
    }

    public function validarNuevoUsuario(Empresa $empresa): void
    {
        $uso = $this->uso($empresa);
        if ($uso['usuarios_limite'] !== null && $uso['usuarios_activos'] >= $uso['usuarios_limite']) {
            throw ValidationException::withMessages(['usuarios' => 'Tu plan ya alcanzó el límite de usuarios activos.']);
        }
    }
}
