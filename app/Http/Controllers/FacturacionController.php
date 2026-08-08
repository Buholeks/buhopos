<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EjecutaAccionesStripe;
use App\Models\CodigoPromocional;
use App\Models\CuentaCobroPlataforma;
use App\Models\Plan;
use App\Models\SolicitudPagoSuscripcion;
use App\Models\Sucursal;
use App\Models\Suscripcion;
use App\Services\LimitesSuscripcionService;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FacturacionController extends Controller
{
    use EjecutaAccionesStripe;

    public function show(Request $request, LimitesSuscripcionService $limites): JsonResponse
    {
        $user = $request->user();
        $empresa = $user->empresa;
        $suscripcion = $empresa->suscripcion()->with([
            'plan',
            'planPendiente',
            'pagos' => fn ($query) => $query
                ->select([
                    'id', 'suscripcion_id', 'importe', 'fecha_pago',
                    'periodo_inicio', 'periodo_fin', 'metodo', 'referencia', 'estado',
                ])
                ->latest('fecha_pago')
                ->latest('id')
                ->limit(12),
        ])->first();

        return response()->json([
            'empresa' => $empresa->only(['id', 'nombre', 'correo', 'stripe_customer_id']),
            'suscripcion' => $suscripcion,
            'planes' => Plan::where('activo', true)->where('es_prueba', false)->orderBy('precio_mensual')->get(),
            'uso' => $limites->uso($empresa),
            'sucursales' => $empresa->sucursales()->orderByDesc('activo')->orderBy('nombre')->get(),
            'puede_gestionar' => (bool) $user->es_super_admin,
            'stripe_configurado' => filled(config('services.stripe.key')) && filled(config('services.stripe.secret')),
            'stripe_key' => $user->es_super_admin ? config('services.stripe.key') : null,
            'cuenta_cobro' => $user->es_super_admin ? CuentaCobroPlataforma::where('activa', true)->orderByDesc('predeterminada')->first() : null,
            'solicitud_pago_manual' => $user->es_super_admin && $suscripcion
                ? $suscripcion->solicitudesPago()->with(['plan:id,nombre', 'cuentaCobro'])->whereIn('estado', ['pendiente', 'en_revision', 'rechazado'])->latest()->first()
                : null,
        ]);
    }

    public function solicitarPagoManual(Request $request, StripeBillingService $stripe): JsonResponse
    {
        abort_unless($request->user()->es_super_admin, 403, 'Solo el superadministrador puede gestionar la suscripción.');
        $data = $request->validate(['metodo' => ['required', 'in:transferencia,deposito']]);
        $empresa = $request->user()->empresa;
        $suscripcion = $empresa->suscripcion()->with('plan')->first();
        abort_unless($suscripcion?->plan, 422, 'Selecciona un plan antes de solicitar el pago.');
        abort_if($suscripcion->plan->es_prueba, 422, 'Selecciona un plan comercial antes de solicitar el pago.');
        $cuenta = CuentaCobroPlataforma::where('activa', true)->orderByDesc('predeterminada')->first();
        abort_unless($cuenta, 422, 'No hay una cuenta bancaria disponible. Contacta a soporte.');

        try {
            $status = 200;
            $solicitud = DB::transaction(function () use ($suscripcion, $cuenta, $data, $empresa, $stripe, &$status) {
                // Bloquea la suscripción para que dos solicitudes concurrentes no creen
                // dos filas "pendiente" a la vez (no hay unicidad a nivel de BD para esto).
                $suscripcion = Suscripcion::whereKey($suscripcion->id)->lockForUpdate()->with('plan')->firstOrFail();

                $existente = $suscripcion->solicitudesPago()->whereIn('estado', ['pendiente', 'en_revision'])->latest()->first();
                if ($existente) {
                    return $existente;
                }

                // Una solicitud rechazada previamente ya no es accionable; se cancela
                // para no dejarla huérfana al generar una nueva.
                $suscripcion->solicitudesPago()->where('estado', 'rechazado')->update(['estado' => 'cancelado']);

                $inicio = $suscripcion->fecha_vencimiento && $suscripcion->fecha_vencimiento->isFuture()
                    ? $suscripcion->fecha_vencimiento->copy()->addDay()
                    : today();
                $fin = $inicio->copy()->addMonthNoOverflow()->subDay();

                $stripe->cambiarACobroManual($suscripcion);
                $status = 201;

                return $suscripcion->solicitudesPago()->create([
                    'empresa_id' => $empresa->id,
                    'plan_id' => $suscripcion->plan_id,
                    'cuenta_cobro_id' => $cuenta->id,
                    'metodo' => $data['metodo'],
                    'importe' => $suscripcion->precio_acordado ?: $suscripcion->plan->precio_mensual,
                    'referencia_unica' => 'BUHO-'.str_pad((string) $empresa->id, 5, '0', STR_PAD_LEFT).'-'.strtoupper(Str::random(8)),
                    'periodo_inicio' => $inicio,
                    'periodo_fin' => $fin,
                    'fecha_limite' => now()->addDays(3),
                ]);
            });

            return response()->json($solicitud->load(['plan:id,nombre', 'cuentaCobro']), $status);
        } catch (RuntimeException|\Stripe\Exception\ApiErrorException $e) {
            return $this->errorStripe($e);
        }
    }

    public function subirComprobante(Request $request, SolicitudPagoSuscripcion $solicitud): JsonResponse
    {
        abort_unless($request->user()->es_super_admin && $solicitud->empresa_id === $request->user()->empresa_id, 404);
        abort_unless(in_array($solicitud->estado, ['pendiente', 'rechazado'], true), 422, 'Esta solicitud ya no admite comprobantes.');
        $data = $request->validate([
            'comprobante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'fecha_reportada' => ['required', 'date', 'before_or_equal:today'],
            'numero_operacion' => ['nullable', 'string', 'max:120'],
            'notas_cliente' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($solicitud->comprobante_ruta) {
            Storage::disk('local')->delete($solicitud->comprobante_ruta);
        }
        $archivo = $request->file('comprobante');
        $ruta = $archivo->store("comprobantes-suscripcion/{$solicitud->empresa_id}", 'local');
        $solicitud->update([
            ...collect($data)->except('comprobante')->all(),
            'comprobante_ruta' => $ruta,
            'comprobante_nombre' => $archivo->getClientOriginalName(),
            'comprobante_mime' => $archivo->getMimeType(),
            'estado' => 'en_revision',
            'notas_revision' => null,
        ]);

        return response()->json($solicitud->fresh(['plan:id,nombre', 'cuentaCobro']));
    }

    public function cancelarSolicitudPago(Request $request, SolicitudPagoSuscripcion $solicitud): JsonResponse
    {
        abort_unless($request->user()->es_super_admin && $solicitud->empresa_id === $request->user()->empresa_id, 404);
        $ruta = DB::transaction(function () use ($solicitud) {
            $solicitud = SolicitudPagoSuscripcion::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($solicitud->estado, ['pendiente', 'en_revision', 'rechazado'], true), 422, 'Esta solicitud ya no se puede cancelar.');
            $ruta = $solicitud->comprobante_ruta;
            $solicitud->update(['estado' => 'cancelado', 'comprobante_ruta' => null, 'comprobante_nombre' => null, 'comprobante_mime' => null]);

            return $ruta;
        });
        if ($ruta) {
            Storage::disk('local')->delete($ruta);
        }

        return response()->json(['mensaje' => 'Solicitud cancelada.']);
    }

    public function volverATarjeta(Request $request, StripeBillingService $stripe): JsonResponse
    {
        abort_unless($request->user()->es_super_admin, 403, 'Solo el superadministrador puede gestionar la suscripción.');
        $suscripcion = $request->user()->empresa->suscripcion;
        abort_unless($suscripcion, 422, 'No existe una suscripción para actualizar.');

        return $this->ejecutar(function () use ($suscripcion, $stripe) {
            $resultado = $stripe->cambiarACobroStripe($suscripcion);
            $abiertas = $suscripcion->solicitudesPago()->whereIn('estado', ['pendiente', 'en_revision', 'rechazado'])->get();
            $suscripcion->solicitudesPago()->whereIn('id', $abiertas->pluck('id'))->update([
                'estado' => 'cancelado', 'comprobante_ruta' => null,
                'comprobante_nombre' => null, 'comprobante_mime' => null,
            ]);
            $abiertas->pluck('comprobante_ruta')->filter()->each(fn ($ruta) => Storage::disk('local')->delete($ruta));

            return $resultado;
        });
    }

    public function descargarComprobante(Request $request, SolicitudPagoSuscripcion $solicitud)
    {
        abort_unless($request->user()->es_super_admin && $request->user()->empresa_id === $solicitud->empresa_id, 404);
        abort_unless($solicitud->comprobante_ruta && Storage::disk('local')->exists($solicitud->comprobante_ruta), 404);

        return Storage::disk('local')->download($solicitud->comprobante_ruta, $solicitud->comprobante_nombre);
    }

    public function cambiarPlan(Request $request, StripeBillingService $stripe, LimitesSuscripcionService $limites): JsonResponse
    {
        abort_unless($request->user()->es_super_admin, 403, 'Solo el superadministrador puede cambiar el plan.');
        $data = $request->validate(['plan_id' => ['required', 'integer', 'exists:planes,id']]);
        $plan = Plan::whereKey($data['plan_id'])->where('activo', true)->firstOrFail();
        $empresa = $request->user()->empresa;
        $limites->validarCambio($empresa, $plan);

        $suscripcion = $empresa->suscripcion;
        if (! $suscripcion?->plan_id || ! $suscripcion->stripe_subscription_id || in_array($suscripcion->stripe_status, ['canceled', 'incomplete_expired'], true)) {
            $pruebaVigente = $suscripcion?->estado === 'prueba'
                && $suscripcion?->fecha_vencimiento
                && today()->lte($suscripcion->fecha_vencimiento);
            $empresa->suscripcion()->updateOrCreate(['empresa_id' => $empresa->id], [
                'plan_id' => $plan->id,
                'plan_pendiente_id' => null,
                'cambio_plan_en' => null,
                'estado' => $pruebaVigente ? 'prueba' : 'pendiente',
                'precio_acordado' => $plan->precio_mensual,
            ]);

            return response()->json([
                'tipo' => 'seleccion',
                'mensaje' => $pruebaVigente
                    ? 'Plan seleccionado. Tu prueba continúa activa hasta su fecha de vencimiento.'
                    : 'Plan seleccionado. Ya puedes realizar el pago con tarjeta.',
            ]);
        }

        return $this->ejecutar(fn () => $stripe->cambiarPlan($empresa, $plan));
    }

    public function canjearCodigo(Request $request, LimitesSuscripcionService $limites, StripeBillingService $stripe): JsonResponse
    {
        abort_unless($request->user()->es_super_admin, 403, 'Solo el superadministrador puede canjear códigos.');
        $data = $request->validate(['codigo' => ['required', 'string', 'max:40']]);
        $empresa = $request->user()->empresa;

        try {
            return DB::transaction(function () use ($data, $empresa, $request, $limites, $stripe) {
                $codigo = CodigoPromocional::where('codigo', strtoupper(trim($data['codigo'])))->lockForUpdate()->first();
                if (! $codigo) {
                    throw ValidationException::withMessages(['codigo' => 'El código no es válido.']);
                }
                if ($codigo->canjeado_en) {
                    throw ValidationException::withMessages(['codigo' => 'El código ya fue canjeado.']);
                }
                if (! $codigo->activo) {
                    throw ValidationException::withMessages(['codigo' => 'El código está desactivado.']);
                }
                if ($codigo->expira_en && now()->isAfter($codigo->expira_en)) {
                    throw ValidationException::withMessages(['codigo' => 'El código ya caducó.']);
                }
                if ($codigo->empresa_destino_id && $codigo->empresa_destino_id !== $empresa->id) {
                    throw ValidationException::withMessages(['codigo' => 'Este código fue creado para otra empresa.']);
                }
                $codigo->load('plan');
                $limites->validarCambio($empresa, $codigo->plan);
                $suscripcion = $empresa->suscripcion;
                if ($suscripcion?->stripe_subscription_id && in_array($suscripcion->stripe_status, ['active', 'trialing', 'past_due'], true) && $suscripcion->plan_id !== $codigo->plan_id) {
                    throw ValidationException::withMessages(['codigo' => 'Este código corresponde a otro plan. Cambia primero tu plan actual.']);
                }

                $fechasBase = collect([Carbon::today(), $suscripcion?->acceso_promocional_hasta, $suscripcion?->fecha_vencimiento])
                    ->filter()->map(fn ($fecha) => $fecha instanceof Carbon ? $fecha->copy() : Carbon::parse($fecha));
                $base = $fechasBase->sortByDesc(fn ($fecha) => $fecha->timestamp)->first();
                $hasta = $codigo->duracion_tipo === 'meses'
                    ? $base->addMonthsNoOverflow($codigo->duracion_cantidad)
                    : $base->addDays($codigo->duracion_cantidad);
                if ($suscripcion) {
                    $stripe->aplicarAccesoPromocional($suscripcion, $hasta);
                }
                $empresa->suscripcion()->updateOrCreate(['empresa_id' => $empresa->id], [
                    'plan_id' => $codigo->plan_id, 'estado' => 'activa',
                    'acceso_promocional_hasta' => $hasta->toDateString(), 'precio_acordado' => $codigo->plan->precio_mensual,
                ]);
                $empresa->update(['activo' => true]);
                $codigo->update(['canjeado_por_empresa_id' => $empresa->id, 'canjeado_por_user_id' => $request->user()->id, 'canjeado_en' => now(), 'activo' => false]);

                return response()->json(['mensaje' => 'Código aplicado correctamente.', 'acceso_promocional_hasta' => $hasta->toDateString()]);
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            report($e);

            return response()->json(['message' => 'Stripe rechazó la promoción: '.$e->getMessage()], 422);
        }
    }

    public function guardarSucursal(Request $request, LimitesSuscripcionService $limites): JsonResponse
    {
        abort_unless($request->user()->es_super_admin, 403, 'Solo el superadministrador puede gestionar sucursales.');
        abort_unless($request->user()->empresa->suscripcion?->permiteAcceso(), 402, 'La suscripción debe estar vigente para agregar sucursales.');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'], 'direccion' => ['nullable', 'string', 'max:500'],
            'telefono' => ['nullable', 'string', 'max:30'], 'correo' => ['nullable', 'email', 'max:255'],
        ]);
        $limites->validarNuevaSucursal($request->user()->empresa);
        $sucursal = $request->user()->empresa->sucursales()->create($data + ['activo' => true]);

        return response()->json($sucursal, 201);
    }

    public function cambiarEstadoSucursal(Request $request, Sucursal $sucursal, LimitesSuscripcionService $limites): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->es_super_admin && $sucursal->empresa_id === $user->empresa_id, 403);
        abort_unless($user->empresa->suscripcion?->permiteAcceso(), 402, 'La suscripción debe estar vigente para gestionar sucursales.');
        $data = $request->validate(['activo' => ['required', 'boolean']]);
        $activar = (bool) $data['activo'];
        if ($activar && ! $sucursal->activo) {
            $limites->validarNuevaSucursal($user->empresa);
        }
        if (! $activar && $sucursal->activo) {
            $usuarios = $user->empresa->usuarios()
                ->where(fn ($query) => $query->where('sucursal_id', $sucursal->id)
                    ->orWhereHas('sucursales', fn ($s) => $s->where('sucursales.id', $sucursal->id)))
                ->get();
            $sinAlternativa = $usuarios->filter(fn ($usuario) => $usuario->activo && ! $usuario->sucursales()
                ->where('sucursales.activo', true)->where('sucursales.id', '!=', $sucursal->id)->exists());
            if ($sinAlternativa->isNotEmpty()) {
                return response()->json(['message' => 'No se puede desactivar: '.$sinAlternativa->pluck('name')->join(', ').' se quedaría sin una sucursal activa. Asigna otra sucursal primero.'], 422);
            }
            DB::transaction(function () use ($usuarios, $sucursal) {
                foreach ($usuarios as $usuario) {
                    if ($usuario->sucursal_id === $sucursal->id) {
                        $alternativa = $usuario->sucursales()->where('sucursales.activo', true)
                            ->where('sucursales.id', '!=', $sucursal->id)->orderBy('sucursales.nombre')->value('sucursales.id');
                        $usuario->update(['sucursal_id' => $alternativa]);
                    }
                    $usuario->sucursales()->detach($sucursal->id);
                }
                $sucursal->update(['activo' => false]);
            });

            return response()->json($sucursal->fresh());
        }
        $sucursal->update(['activo' => $activar]);

        return response()->json($sucursal->fresh());
    }

    public function checkout(Request $request, StripeBillingService $stripe): JsonResponse
    {
        abort_unless($request->user()->es_super_admin, 403, 'Solo el superadministrador puede gestionar la suscripción.');
        abort_if($request->user()->empresa->suscripcion?->plan?->es_prueba, 422, 'Selecciona un plan comercial antes de pagar.');

        return $this->ejecutar(fn () => ['client_secret' => $stripe->checkoutEmbebido($request->user()->empresa)]);
    }

    public function portal(Request $request, StripeBillingService $stripe): JsonResponse
    {
        abort_unless($request->user()->es_super_admin, 403, 'Solo el superadministrador puede gestionar la suscripción.');

        return $this->ejecutar(fn () => ['url' => $stripe->portal($request->user()->empresa)]);
    }

    public function prepararTarjeta(Request $request, StripeBillingService $stripe): JsonResponse
    {
        abort_unless($request->user()->es_super_admin, 403, 'Solo el superadministrador puede gestionar la suscripción.');

        return $this->ejecutar(fn () => ['client_secret' => $stripe->iniciarActualizacionTarjeta($request->user()->empresa)]);
    }

    public function guardarTarjeta(Request $request, StripeBillingService $stripe): JsonResponse
    {
        abort_unless($request->user()->es_super_admin, 403, 'Solo el superadministrador puede gestionar la suscripción.');
        $data = $request->validate(['setup_intent_id' => ['required', 'string', 'max:255']]);

        return $this->ejecutar(fn () => [
            'mensaje' => 'Tarjeta actualizada correctamente.',
            'tarjeta' => $stripe->guardarTarjeta($request->user()->empresa, $data['setup_intent_id']),
        ]);
    }
}
