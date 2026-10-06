<?php

namespace App\Http\Controllers;

use App\Models\ComisionMovimiento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ComisionReporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Reporte de comisiones por empleado y "Mis comisiones".
 */
class ComisionReporteController extends Controller
{
    private const MAX_DIAS = 366;

    public function __construct(private ComisionReporte $reporte) {}

    // ── GET /api/comisiones/reporte ───────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user->tienePermiso('comisiones.ver'), 403, 'Sin permiso: comisiones.ver');

        $filtros = $this->validarFiltros($request, (int) $user->empresa_id);

        return response()->json($this->reporte->resumen((int) $user->empresa_id, $filtros) + [
            'sucursales' => Sucursal::where('empresa_id', $user->empresa_id)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    // ── GET /api/comisiones/reporte/{userId} ──────────────────────────────────

    public function detalle(Request $request, int $userId): JsonResponse
    {
        $user = Auth::user();
        $propio = $userId === (int) $user->id;
        abort_unless(
            $user->tienePermiso('comisiones.ver') || ($propio && $user->tienePermiso('comisiones.ver_propias')),
            403,
            'Sin permiso para ver estas comisiones.'
        );

        $empleado = User::where('empresa_id', $user->empresa_id)->findOrFail($userId);
        $filtros = $this->validarFiltros($request, (int) $user->empresa_id, conDetalle: true);

        return response()->json([
            'empleado'    => $empleado->only(['id', 'name']),
            'resumen'     => $this->resumenDe((int) $user->empresa_id, $empleado->id, $filtros),
            'movimientos' => $this->reporte->detalle((int) $user->empresa_id, $empleado->id, $filtros),
        ]);
    }

    // ── GET /api/comisiones/mias ──────────────────────────────────────────────

    public function mias(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless(
            $user->tienePermiso('comisiones.ver_propias') || $user->tienePermiso('comisiones.ver'),
            403,
            'Sin permiso: comisiones.ver_propias'
        );

        $filtros = $this->validarFiltros($request, (int) $user->empresa_id, conDetalle: true);

        return response()->json([
            'empleado'    => $user->only(['id', 'name']),
            'resumen'     => $this->resumenDe((int) $user->empresa_id, (int) $user->id, $filtros),
            'movimientos' => $this->reporte->detalle((int) $user->empresa_id, (int) $user->id, $filtros),
            'esquemas'    => [
                'vendedor' => $user->esquemaVendedor?->only(['id', 'nombre', 'activo']),
                'cajero'   => $user->esquemaCajero?->only(['id', 'nombre', 'activo']),
            ],
        ]);
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    private function validarFiltros(Request $request, int $empresaId, bool $conDetalle = false): array
    {
        $reglas = [
            'desde'       => ['required', 'date_format:Y-m-d'],
            'hasta'       => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursales', 'id')->where('empresa_id', $empresaId)],
        ];
        if ($conDetalle) {
            $reglas['beneficiario'] = ['nullable', Rule::in(['vendedor', 'cajero'])];
            $reglas['tipo'] = ['nullable', Rule::in(ComisionMovimiento::TIPOS)];
            $reglas['page'] = ['nullable', 'integer', 'min:1'];
        }

        $data = $request->validate($reglas, [
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'sucursal_id.exists'   => 'La sucursal no pertenece a la empresa.',
        ]);

        if (Carbon::parse($data['desde'])->diffInDays(Carbon::parse($data['hasta'])) > self::MAX_DIAS) {
            abort(response()->json([
                'message' => 'El periodo no puede ser mayor a un año.',
                'errors'  => ['hasta' => ['El periodo no puede ser mayor a un año.']],
            ], 422));
        }

        return $data;
    }

    private function resumenDe(int $empresaId, int $userId, array $filtros): array
    {
        $resumen = $this->reporte->resumen($empresaId, $filtros + ['user_id' => $userId]);

        return $resumen['empleados'][0] ?? [
            'user_id' => $userId, 'tickets_vendedor' => 0, 'tickets_cajero' => 0,
            'comision_vendedor' => 0, 'comision_cajero' => 0, 'descuentos' => 0, 'ajustes' => 0,
            'total' => 0, 'pagado' => 0, 'pendiente' => 0,
        ];
    }
}
