<?php

namespace App\Http\Controllers;

use App\Exportaciones\ServicioExportacion;
use App\Models\ComisionLiquidacion;
use App\Models\CorteCaja;
use App\Models\CuentaBancaria;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ComisionAcceso;
use App\Services\ComisionLiquidacionService;
use App\Services\ComisionReporte;
use App\Support\TerminalResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Liquidaciones de comisiones: pendientes por empleado, generar, pagar y cancelar.
 */
class ComisionLiquidacionController extends Controller
{
    public function __construct(private ComisionLiquidacionService $liquidaciones) {}

    // ── GET /api/comisiones/liquidaciones/pendientes?hasta= ───────────────────

    public function pendientes(Request $request): JsonResponse
    {
        [$user, $sucursales] = $this->autorizar();
        $data = $request->validate(['hasta' => ['required', 'date_format:Y-m-d']]);

        return response()->json([
            'empleados' => $this->liquidaciones->pendientes((int) $user->empresa_id, $data['hasta'], $sucursales),
        ]);
    }

    // ── GET /api/comisiones/liquidaciones ─────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        [$user, $sucursales] = $this->autorizar();
        $data = $request->validate([
            'estado'  => ['nullable', Rule::in([ComisionLiquidacion::ESTADO_BORRADOR, ComisionLiquidacion::ESTADO_PAGADA, ComisionLiquidacion::ESTADO_CANCELADA])],
            'user_id' => ['nullable', 'integer'],
            'page'    => ['nullable', 'integer', 'min:1'],
        ]);

        $pagina = ComisionLiquidacion::where('empresa_id', $user->empresa_id)
            ->when($data['estado'] ?? null, fn($q, $e) => $q->where('estado', $e))
            ->when($data['user_id'] ?? null, fn($q, $u) => $q->where('user_id', $u))
            // Solo las que no incluyen movimientos de sucursales sin acceso.
            ->whereDoesntHave('movimientos', fn($q) => $q->whereNotIn('sucursal_id', $sucursales))
            ->with(['user:id,name', 'pagador:id,name'])
            ->orderByDesc('id')
            ->paginate(25)
            ->through(fn(ComisionLiquidacion $l) => $this->formatear($l));

        return response()->json($pagina);
    }

    // ── POST /api/comisiones/liquidaciones ────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        [$user, $sucursales] = $this->autorizar();
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('empresa_id', $user->empresa_id)],
            'hasta'   => ['required', 'date_format:Y-m-d'],
            'notas'   => ['nullable', 'string', 'max:500'],
        ], ['user_id.exists' => 'El empleado no pertenece a la empresa.']);

        $liquidacion = $this->liquidaciones->generar(
            (int) $user->empresa_id, $user, (int) $data['user_id'], $data['hasta'], $sucursales, $data['notas'] ?? null
        );

        return response()->json($this->detalle($liquidacion, $request), 201);
    }

    // ── GET /api/comisiones/liquidaciones/{id} ────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $liquidacion = $this->liquidacionAccesible($id);

        return response()->json($this->detalle($liquidacion, $request));
    }

    // ── POST /api/comisiones/liquidaciones/{id}/pagar ─────────────────────────

    public function pagar(Request $request, int $id): JsonResponse
    {
        $liquidacion = $this->liquidacionAccesible($id);
        $user = Auth::user();

        $data = $request->validate([
            'registrar_caja'     => ['required', 'boolean'],
            'forma_pago'         => ['required_if:registrar_caja,true', 'nullable', Rule::in(['efectivo', 'transferencia'])],
            'cuenta_bancaria_id' => [
                'required_if:forma_pago,transferencia', 'nullable', 'integer',
                Rule::exists('cuentas_bancarias', 'id')->where(fn($q) => $q->where('empresa_id', $user->empresa_id)->where('activo', true)),
            ],
        ], [
            'forma_pago.required_if'         => 'Indica cómo se paga.',
            'cuenta_bancaria_id.required_if' => 'Selecciona la cuenta de la transferencia.',
        ]);

        $caja = null;
        $corte = null;
        if ($request->boolean('registrar_caja')) {
            abort_unless($user->tienePermiso('caja.abrir'), 403, 'Para registrar la salida de caja necesitas permiso de caja.');
            $caja = [
                'forma_pago'         => $data['forma_pago'],
                'cuenta_bancaria_id' => $data['forma_pago'] === 'transferencia' ? ($data['cuenta_bancaria_id'] ?? null) : null,
            ];
            $corte = $this->corteAbierto($request);
        }

        $liquidacion = $this->liquidaciones->pagar($liquidacion, $user, $caja, $corte);

        return response()->json($this->detalle($liquidacion, $request));
    }

    // ── POST /api/comisiones/liquidaciones/{id}/cancelar ──────────────────────

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $liquidacion = $this->liquidacionAccesible($id);
        $user = Auth::user();
        $data = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:255']]);

        $corte = null;
        if ($liquidacion->movimiento_caja_id) {
            abort_unless($user->tienePermiso('caja.abrir'), 403, 'Esta liquidación salió de caja; para cancelarla necesitas permiso de caja.');
            $corte = $this->corteAbierto($request);
        }

        $liquidacion = $this->liquidaciones->cancelar($liquidacion, $user, $data['motivo'], $corte);

        return response()->json($this->detalle($liquidacion, $request));
    }

    // ── GET /api/comisiones/liquidaciones/{id}/pdf ────────────────────────────

    public function pdf(int $id, ServicioExportacion $exportacion)
    {
        $liquidacion = $this->liquidacionAccesible($id);
        $liquidacion->load(['user:id,name', 'pagador:id,name', 'creador:id,name', 'cancelador:id,name', 'movimientoCaja']);

        $empresa = Empresa::find($liquidacion->empresa_id);
        $fmt = fn($v) => ($v < 0 ? '-$' : '$') . number_format(abs((float) ($v ?? 0)), 2);

        $movimientos = $this->liquidaciones->movimientosDe($liquidacion)
            ->map(fn($m) => ComisionReporte::formatear($m))
            ->map(fn(array $m) => $m + ['calculo' => $this->textoCalculo($m, $fmt)])
            ->values()
            ->all();

        $sucursales = Sucursal::whereIn('id', $this->liquidaciones->sucursalesDe($liquidacion))->orderBy('nombre')->pluck('nombre');

        $pdf = Pdf::loadView('pdf.liquidacion-comisiones', [
            'titulo'            => 'Liquidación de comisiones #' . $liquidacion->id,
            'liquidacion'       => $liquidacion,
            'resumen'           => $this->liquidaciones->resumen($liquidacion),
            'movimientos'       => $movimientos,
            'estadoTexto'       => self::ESTADOS[$liquidacion->estado] ?? $liquidacion->estado,
            'tipos'             => ['venta' => 'Venta', 'devolucion' => 'Devolución', 'cancelacion' => 'Cancelación', 'ajuste' => 'Ajuste'],
            'fmt'               => $fmt,
            'empresaNombre'     => $empresa?->nombre ?? config('app.name'),
            'empresaLogoB64'    => $exportacion->logoBase64($empresa),
            'empresaDireccion'  => $empresa?->direccion,
            'sucursalNombre'    => null,
            'sucursalDireccion' => null,
            'sucursales'        => $sucursales->join(', '),
            'filtrosAplicados'  => [],
            'fecha'             => now('America/Mexico_City')->format('d/m/Y H:i'),
        ])->setPaper('letter', 'portrait');

        $nombre = str($liquidacion->user?->name ?? 'empleado')->slug('_');

        return $pdf->download("liquidacion_{$liquidacion->id}_{$nombre}.pdf");
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    private const ESTADOS = [
        ComisionLiquidacion::ESTADO_BORRADOR  => 'Por pagar',
        ComisionLiquidacion::ESTADO_PAGADA    => 'Pagada',
        ComisionLiquidacion::ESTADO_CANCELADA => 'Cancelada',
    ];

    /** "4% de $1,800.00" o "$15.00 × 2 pza", igual que en pantalla. */
    private function textoCalculo(array $m, callable $fmt): string
    {
        if ($m['tipo'] === 'ajuste') {
            return 'Ajuste manual';
        }
        if (! $m['regla_tipo']) {
            return '—';
        }
        $cantidad = rtrim(rtrim(number_format(abs($m['cantidad']), 3), '0'), '.');
        if ($m['regla_tipo'] === 'porcentaje') {
            $tasa = rtrim(rtrim(number_format($m['tasa'], 4), '0'), '.');
            return "{$tasa}% de " . $fmt(abs($m['base_calculo'])) . ($m['base'] === 'utilidad' ? ' de utilidad' : '');
        }
        return $fmt($m['tasa']) . " × {$cantidad} pza";
    }

    /** @return array{0: User, 1: int[]} usuario y sucursales donde puede liquidar */
    private function autorizar(): array
    {
        $user = Auth::user();
        abort_unless($user->tienePermiso('comisiones.liquidar'), 403, 'Sin permiso: comisiones.liquidar');

        return [$user, ComisionAcceso::sucursales($user, 'comisiones.liquidar')];
    }

    private function liquidacionAccesible(int $id): ComisionLiquidacion
    {
        [$user, $sucursales] = $this->autorizar();
        $liquidacion = ComisionLiquidacion::where('empresa_id', $user->empresa_id)->findOrFail($id);

        $fuera = array_diff($this->liquidaciones->sucursalesDe($liquidacion), $sucursales);
        abort_if($fuera !== [], 403, 'La liquidación incluye comisiones de sucursales a las que no tienes acceso.');

        return $liquidacion;
    }

    private function corteAbierto(Request $request): ?CorteCaja
    {
        $user = Auth::user();

        return CorteCaja::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('terminal', TerminalResolver::fromRequest($request))
            ->where('estado', 'abierto')
            ->latest('fecha_apertura')
            ->first();
    }

    private function formatear(ComisionLiquidacion $l): array
    {
        return [
            'id'            => $l->id,
            'empleado'      => $l->user?->only(['id', 'name']),
            'desde'         => $l->desde?->toDateString(),
            'hasta'         => $l->hasta?->toDateString(),
            'total'         => (float) $l->total,
            'estado'        => $l->estado,
            'notas'         => $l->notas,
            'pagado_en'     => $l->pagado_en?->toIso8601String(),
            'pagado_por'    => $l->pagador?->name,
            'salio_de_caja' => (bool) $l->movimiento_caja_id,
            'creado_en'     => $l->created_at?->toIso8601String(),
        ];
    }

    private function detalle(ComisionLiquidacion $liquidacion, Request $request): array
    {
        $liquidacion->load(['user:id,name', 'pagador:id,name', 'creador:id,name', 'cancelador:id,name', 'movimientoCaja']);
        $user = Auth::user();

        return $this->formatear($liquidacion) + [
            'creado_por'    => $liquidacion->creador?->name,
            'cancelado_por' => $liquidacion->cancelador?->name,
            'cancelado_en'  => $liquidacion->cancelado_en?->toIso8601String(),
            'forma_pago'    => $liquidacion->movimientoCaja?->forma_pago,
            'resumen'       => $this->liquidaciones->resumen($liquidacion),
            'movimientos'   => $this->liquidaciones->movimientosDe($liquidacion)->map(fn($m) => ComisionReporte::formatear($m))->values(),
            'caja'          => [
                'puede_registrar' => $user->tienePermiso('caja.abrir'),
                'abierta'         => (bool) $this->corteAbierto($request),
            ],
            'cuentas_bancarias' => CuentaBancaria::where('empresa_id', $user->empresa_id)->where('activo', true)
                ->orderBy('nombre')->get(['id', 'nombre', 'banco']),
        ];
    }
}
