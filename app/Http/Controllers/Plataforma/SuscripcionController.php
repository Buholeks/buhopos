<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\PagoSuscripcion;
use App\Models\Suscripcion;
use App\Models\SolicitudPagoSuscripcion;
use App\Models\CuentaCobroPlataforma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SuscripcionController extends Controller
{
    public function solicitudesPago(Request $request): JsonResponse
    {
        $estado = $request->query('estado', 'en_revision');
        abort_unless(in_array($estado, ['pendiente', 'en_revision', 'confirmado', 'rechazado', 'vencido', 'cancelado'], true), 422);
        return response()->json(SolicitudPagoSuscripcion::with(['empresa:id,nombre,correo', 'plan:id,nombre', 'cuentaCobro'])
            ->where('estado', $estado)->latest()->paginate(20));
    }

    public function cuentaCobro(): JsonResponse
    {
        return response()->json(CuentaCobroPlataforma::orderByDesc('predeterminada')->first());
    }

    public function guardarCuentaCobro(Request $request): JsonResponse
    {
        $data = $request->validate([
            'banco' => ['required', 'string', 'max:120'],
            'beneficiario' => ['required', 'string', 'max:255'],
            'clabe' => ['nullable', 'digits:18', 'required_without:numero_cuenta'],
            'numero_cuenta' => ['nullable', 'string', 'max:40', 'required_without:clabe'],
            'instrucciones' => ['nullable', 'string', 'max:1000'],
        ]);
        $cuenta = CuentaCobroPlataforma::firstOrNew(['predeterminada' => true]);
        $cuenta->fill($data + ['activa' => true, 'predeterminada' => true])->save();
        return response()->json($cuenta);
    }

    public function revisarSolicitud(Request $request, SolicitudPagoSuscripcion $solicitud): JsonResponse
    {
        $data = $request->validate([
            'accion' => ['required', Rule::in(['confirmar', 'rechazar'])],
            'notas_revision' => ['nullable', 'string', 'max:2000', 'required_if:accion,rechazar'],
        ]);
        DB::transaction(function () use ($solicitud, $data) {
            $solicitud = SolicitudPagoSuscripcion::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
            abort_unless($solicitud->estado === 'en_revision', 422, 'La solicitud ya fue procesada.');
            if ($data['accion'] === 'rechazar') {
                $solicitud->update(['estado' => 'rechazado', 'notas_revision' => $data['notas_revision'], 'revisado_por' => auth('plataforma')->id(), 'revisado_en' => now()]);
                return;
            }
            $suscripcion = $solicitud->suscripcion()->lockForUpdate()->firstOrFail();
            $pago = $suscripcion->pagos()->create([
                'registrado_por' => auth('plataforma')->id(),
                'importe' => $solicitud->importe,
                'fecha_pago' => $solicitud->fecha_reportada,
                'periodo_inicio' => $solicitud->periodo_inicio,
                'periodo_fin' => $solicitud->periodo_fin,
                'metodo' => $solicitud->metodo,
                'referencia' => $solicitud->numero_operacion ?: $solicitud->referencia_unica,
                'estado' => 'confirmado',
                'notas' => 'Confirmado desde solicitud '.$solicitud->referencia_unica,
            ]);
            $vencimiento = $suscripcion->fecha_vencimiento && $suscripcion->fecha_vencimiento->greaterThan($solicitud->periodo_fin)
                ? $suscripcion->fecha_vencimiento : $solicitud->periodo_fin;
            $suscripcion->update(['estado' => 'activa', 'modalidad_cobro' => 'manual', 'fecha_inicio' => $suscripcion->fecha_inicio ?: $solicitud->periodo_inicio, 'fecha_vencimiento' => $vencimiento, 'cancelada_en' => null]);
            $suscripcion->empresa()->update(['activo' => true]);
            $solicitud->update(['estado' => 'confirmado', 'pago_suscripcion_id' => $pago->id, 'notas_revision' => $data['notas_revision'] ?? null, 'revisado_por' => auth('plataforma')->id(), 'revisado_en' => now()]);
        });
        return response()->json($solicitud->fresh(['plan:id,nombre', 'cuentaCobro', 'pago']));
    }

    public function descargarComprobante(SolicitudPagoSuscripcion $solicitud)
    {
        abort_unless($solicitud->comprobante_ruta && Storage::disk('local')->exists($solicitud->comprobante_ruta), 404);
        return Storage::disk('local')->download($solicitud->comprobante_ruta, $solicitud->comprobante_nombre);
    }

    public function guardar(Request $request, Empresa $empresa): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['nullable', Rule::exists('planes', 'id')],
            'estado' => ['required', Rule::in(['pendiente', 'prueba', 'activa', 'vencida', 'suspendida', 'cancelada'])],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'dias_gracia' => ['required', 'integer', 'min:0', 'max:60'],
            'precio_acordado' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($data['estado'] === 'cancelada') $data['cancelada_en'] = now();
        else $data['cancelada_en'] = null;

        $suscripcion = $empresa->suscripcion()->updateOrCreate([], $data);
        return response()->json($suscripcion->load('plan'));
    }

    public function registrarPago(Request $request, Empresa $empresa): JsonResponse
    {
        $suscripcion = $empresa->suscripcion;
        abort_unless($suscripcion, 422, 'Primero configura la suscripción.');

        $data = $request->validate([
            'importe' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fin' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'metodo' => ['required', Rule::in(['transferencia', 'efectivo', 'tarjeta', 'deposito', 'otro'])],
            'referencia' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', Rule::in(['pendiente', 'confirmado', 'rechazado', 'reembolsado'])],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $pago = DB::transaction(function () use ($suscripcion, $data) {
            $pago = $suscripcion->pagos()->create($data + ['registrado_por' => auth('plataforma')->id()]);
            if ($pago->estado === 'confirmado') {
                // Un pago registrado fuera de orden (p. ej. una transferencia antigua capturada tarde)
                // nunca debe recortar un vencimiento ya extendido por un pago más reciente.
                $vencimiento = $suscripcion->fecha_vencimiento && $suscripcion->fecha_vencimiento->greaterThan($pago->periodo_fin)
                    ? $suscripcion->fecha_vencimiento
                    : $pago->periodo_fin;
                $suscripcion->update([
                    'estado' => 'activa',
                    'modalidad_cobro' => in_array($pago->metodo, ['transferencia', 'deposito', 'efectivo', 'otro'], true) ? 'manual' : $suscripcion->modalidad_cobro,
                    'fecha_inicio' => $suscripcion->fecha_inicio ?: $pago->periodo_inicio,
                    'fecha_vencimiento' => $vencimiento,
                    'cancelada_en' => null,
                ]);
                $suscripcion->empresa()->update(['activo' => true]);
            }
            return $pago;
        });

        return response()->json($pago->load('administrador:id,nombre'), 201);
    }
}
