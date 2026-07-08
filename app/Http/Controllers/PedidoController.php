<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteSaldoMovimiento;
use App\Models\CorteCaja;
use App\Models\Empresa;
use App\Models\Inventario;
use App\Models\InventarioReserva;
use App\Models\MovimientoCaja;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Atributo;
use App\Models\VarianteAtributo;
use App\Services\FolioService;
use App\Support\TerminalResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PedidoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.ver'), 403, 'Sin permiso: pedidos.ver');
        $user = Auth::user();
        $this->marcarPedidosVencidos((int) $user->empresa_id, (int) $user->sucursal_id);

        $pedidos = Pedido::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with([
                'cliente:id,nombre,telefono',
                'detalles.producto:id,nombre,codigo',
                'detalles.variante:id,sku',
                'detalles.compraDetalle:id,compra_id',
            ])
            ->when($request->filled('tipo'), fn($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('estado'), fn($q) => $q->where('estado', $request->estado))
            ->when($request->filled('cliente_id'), fn($q) => $q->where('cliente_id', $request->integer('cliente_id')))
            ->when($request->filled('fecha_desde'), fn($q) => $q->where('created_at', '>=', Carbon::parse($request->fecha_desde, 'America/Mexico_City')->startOfDay()->utc()))
            ->when($request->filled('fecha_hasta'), fn($q) => $q->where('created_at', '<=', Carbon::parse($request->fecha_hasta, 'America/Mexico_City')->endOfDay()->utc()))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $b = trim((string) $request->buscar);
                $q->where(function ($sq) use ($b) {
                    $sq->where('folio', 'like', "%{$b}%")
                        ->orWhereHas('cliente', fn($cq) => $cq
                            ->where('nombre', 'like', "%{$b}%")
                            ->orWhere('telefono', 'like', "%{$b}%"))
                        ->orWhereHas('detalles', fn($dq) => $dq->where('descripcion', 'like', "%{$b}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate($request->integer('por_pagina', 20));

        return response()->json($pedidos);
    }

    public function show(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.ver'), 403, 'Sin permiso: pedidos.ver');
        $user = Auth::user();
        $this->marcarPedidosVencidos((int) $user->empresa_id, (int) $user->sucursal_id);

        $pedido = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->with([
                'cliente:id,nombre,telefono',
                'detalles.producto:id,nombre,codigo',
                'detalles.variante:id,sku',
                'saldos' => fn($q) => $q->with([
                    'user:id,name',
                    'movimientoCaja.cuentaBancaria:id,nombre,banco',
                    'movimientoCaja.terminalPago:id,nombre,banco',
                ])->orderBy('created_at'),
            ])
            ->findOrFail($id);

        return response()->json($pedido);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');
        $user = Auth::user();
        $empresaId = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;
        $terminal = TerminalResolver::fromRequest($request);

        $data = $request->validate([
            'tipo' => ['required', Rule::in(['pedido', 'apartado'])],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'fecha_promesa' => ['nullable', 'date'],
            'notas' => ['nullable', 'string'],
            'anticipo' => ['nullable', 'numeric', 'min:0'],
            'forma_pago' => ['nullable', Rule::in(['efectivo', 'tarjeta', 'transferencia'])],
            'cuenta_bancaria_id' => [
                'required_if:forma_pago,transferencia', 'nullable', 'integer',
                Rule::exists('cuentas_bancarias', 'id')->where(fn($q) => $q->where('empresa_id', $empresaId)->where('activo', true)),
            ],
            'terminal_pago_id' => [
                'required_if:forma_pago,tarjeta', 'nullable', 'integer',
                Rule::exists('terminales_pago', 'id')->where(fn($q) => $q->where('empresa_id', $empresaId)->where('activo', true)),
            ],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'detalles.*.variante_id' => ['nullable', 'integer', 'exists:producto_variantes,id'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.marca_texto' => ['nullable', 'string', 'max:120'],
            'detalles.*.modelo_texto' => ['nullable', 'string', 'max:120'],
            'detalles.*.color_texto' => ['nullable', 'string', 'max:80'],
            'detalles.*.talla_texto' => ['nullable', 'string', 'max:80'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'detalles.*.precio_acordado' => ['required', 'numeric', 'min:0'],
            'detalles.*.notas' => ['nullable', 'string'],
        ]);

        $clienteValido = Cliente::where('id', $data['cliente_id'])
            ->where('empresa_id', $empresaId)
            ->exists();

        if (! $clienteValido) {
            return response()->json(['message' => 'El cliente no pertenece a esta empresa.'], 422);
        }

        if ($data['tipo'] === 'apartado') {
            foreach ($data['detalles'] as $i => $detalle) {
                if (empty($detalle['producto_id'])) {
                    return response()->json([
                        'message' => 'Un apartado requiere productos existentes en inventario.',
                        'errors' => ["detalles.{$i}.producto_id" => ['Selecciona un producto para apartar.']],
                    ], 422);
                }
            }
        }

        foreach ($data['detalles'] as $i => $detalle) {
            if (empty($detalle['producto_id'])) {
                continue;
            }

            $producto = Producto::where('empresa_id', $empresaId)
                ->select('id', 'tiene_variantes')
                ->findOrFail($detalle['producto_id']);

            if ($producto->tiene_variantes && empty($detalle['variante_id'])) {
                return response()->json([
                    'message' => 'Selecciona una variante para los productos que manejan variantes.',
                    'errors' => ["detalles.{$i}.variante_id" => ['Este producto requiere una variante.']],
                ], 422);
            }
        }

        $subtotalValidacion = collect($data['detalles'])->sum(
            fn($d) => ((float) $d['precio_acordado']) * ((int) $d['cantidad'])
        );

        $anticipo = (float) ($data['anticipo'] ?? 0);
        if ($anticipo > 0 && empty($data['forma_pago'])) {
            return response()->json(['message' => 'Selecciona forma de pago para el anticipo.'], 422);
        }

        if ($anticipo > $subtotalValidacion) {
            return response()->json(['message' => 'El anticipo no puede ser mayor al total del pedido.'], 422);
        }

        $corte = null;
        if ($anticipo > 0) {
            $corte = $this->corteAbierto($empresaId, $sucursalId, $terminal);
            if (! $corte) {
                return response()->json(['message' => 'No hay caja abierta para registrar el anticipo.'], 422);
            }
        }

        DB::beginTransaction();

        try {
            $subtotal = collect($data['detalles'])->sum(
                fn($d) => ((float) $d['precio_acordado']) * ((int) $d['cantidad'])
            );

            $fechaPromesa = $data['fecha_promesa'] ?? null;
            if (! $fechaPromesa && $data['tipo'] === 'apartado') {
                $diasVigencia = (int) (Empresa::find($empresaId)?->config_pedidos['dias_vigencia_apartado'] ?? 0);
                if ($diasVigencia > 0) {
                    $fechaPromesa = now('America/Mexico_City')->addDays($diasVigencia)->toDateString();
                }
            }

            $pedido = Pedido::create([
                'empresa_id' => $empresaId,
                'sucursal_id' => $sucursalId,
                'user_id' => $user->id,
                'cliente_id' => $data['cliente_id'],
                'folio' => FolioService::siguienteTicket($empresaId, $sucursalId, $data['tipo'] === 'apartado' ? 'APA' : 'PED'),
                'tipo' => $data['tipo'],
                'estado' => $data['tipo'] === 'apartado' ? 'disponible' : 'pendiente',
                'estado_pago' => $this->estadoPago($subtotal, $anticipo),
                'fecha_promesa' => $fechaPromesa,
                'subtotal' => $subtotal,
                'anticipo' => $anticipo,
                'saldo_pendiente' => max(0, $subtotal - $anticipo),
                'notas' => $data['notas'] ?? null,
            ]);

            foreach ($data['detalles'] as $detalle) {
                $productoId = $detalle['producto_id'] ?? null;
                $varianteId = $detalle['variante_id'] ?? null;
                $cantidad = (int) $detalle['cantidad'];
                $lineaSubtotal = $cantidad * (float) $detalle['precio_acordado'];

                if ($productoId) {
                    $this->validarProductoTenant($empresaId, $productoId, $varianteId);
                }

                $pedidoDetalle = PedidoDetalle::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $productoId,
                    'variante_id' => $varianteId,
                    'descripcion' => $detalle['descripcion'],
                    'marca_texto' => $detalle['marca_texto'] ?? null,
                    'modelo_texto' => $detalle['modelo_texto'] ?? null,
                    'color_texto' => $detalle['color_texto'] ?? null,
                    'talla_texto' => $detalle['talla_texto'] ?? null,
                    'cantidad' => $cantidad,
                    'precio_acordado' => $detalle['precio_acordado'],
                    'subtotal' => $lineaSubtotal,
                    'estado' => $data['tipo'] === 'apartado' ? 'reservado' : 'pendiente',
                    'notas' => $detalle['notas'] ?? null,
                ]);

                if ($data['tipo'] === 'apartado') {
                    $this->reservarInventario($pedido, $pedidoDetalle, $productoId, $varianteId, $cantidad);
                }
            }

            if ($anticipo > 0) {
                $this->registrarAnticipo(
                    $pedido,
                    $corte,
                    $anticipo,
                    $data['forma_pago'],
                    $data['cuenta_bancaria_id'] ?? null,
                    $data['terminal_pago_id'] ?? null
                );
            }

            DB::commit();

            return response()->json($pedido->load([
                'cliente', 'detalles.producto', 'detalles.variante',
                'saldos.movimientoCaja.cuentaBancaria:id,nombre,banco',
                'saldos.movimientoCaja.terminalPago:id,nombre,banco',
            ]), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function buscarCatalogo(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.ver'), 403, 'Sin permiso: pedidos.ver');
        $empresaId = (int) Auth::user()->empresa_id;
        $q = trim((string) $request->q);

        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $productos = Producto::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where(fn($pq) => $pq
                ->where('nombre', 'like', "%{$q}%")
                ->orWhere('codigo', 'like', "%{$q}%"))
            ->orderBy('nombre')
            ->limit(20)
            ->get()
            ->map(fn($p) => [
                'tipo_resultado' => 'producto',
                'id' => null,
                'producto_id' => $p->id,
                'nombre' => $p->nombre,
                'codigo' => $p->codigo,
                'sku' => null,
                'codigo_barras' => null,
                'nombre_variante' => null,
                'precio_venta' => (float) ($p->precio_venta ?? 0),
                'tiene_variantes' => (bool) $p->tiene_variantes,
            ]);

        return response()->json($productos->values());
    }

    public function variantesProducto(int $productoId): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');

        $user = Auth::user();
        $producto = Producto::where('empresa_id', $user->empresa_id)
            ->where('activo', true)
            ->findOrFail($productoId);

        $variantes = ProductoVariante::where('empresa_id', $user->empresa_id)
            ->where('producto_id', $producto->id)
            ->where('activo', true)
            ->with(['atributos.tipoAtributo:id,nombre', 'atributos.atributo:id,valor'])
            ->orderBy('id')
            ->get()
            ->map(fn($v) => [
                'tipo_resultado' => 'variante',
                'id' => $v->id,
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $producto->codigo,
                'sku' => $v->sku,
                'codigo_barras' => $v->codigo_barras,
                'nombre_variante' => $v->nombreVariante(),
                'precio_venta' => (float) ($v->precio_venta ?? $producto->precio_venta ?? 0),
                'tiene_variantes' => true,
            ]);

        return response()->json([
            'producto' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $producto->codigo,
                'precio_venta' => (float) ($producto->precio_venta ?? 0),
            ],
            'variantes' => $variantes,
        ]);
    }

    public function varianteRapida(Request $request, int $productoId): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');

        $user = Auth::user();
        $empresaId = (int) $user->empresa_id;
        $producto = Producto::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->findOrFail($productoId);

        $data = $request->validate([
            'atributos' => ['required', 'array', 'min:1'],
            'atributos.*' => ['required', 'integer', 'exists:atributos,id'],
        ]);

        $atributos = collect($data['atributos'])
            ->mapWithKeys(fn($atributoId, $tipoId) => [(int) $tipoId => (int) $atributoId])
            ->filter();

        foreach ($atributos as $tipoId => $atributoId) {
            $valido = Atributo::where('id', $atributoId)
                ->where('empresa_id', $empresaId)
                ->where('tipo_atributo_id', $tipoId)
                ->where('activo', true)
                ->exists();

            if (! $valido) {
                return response()->json(['message' => 'Selecciona atributos validos para la variante.'], 422);
            }
        }

        $atributosNuevos = $atributos->values()->sort()->values()->toArray();
        $duplicado = ProductoVariante::where('empresa_id', $empresaId)
            ->where('producto_id', $producto->id)
            ->whereNull('deleted_at')
            ->with('atributos')
            ->get()
            ->first(function ($v) use ($atributosNuevos) {
                $existentes = $v->atributos->pluck('atributo_id')->sort()->values()->toArray();
                return $existentes === $atributosNuevos;
            });

        if ($duplicado) {
            return response()->json([
                'message' => 'Ya existe una variante con esos atributos.',
            ], 422);
        }

        $variante = DB::transaction(function () use ($producto, $empresaId, $atributos) {
            $variante = ProductoVariante::create([
                'producto_id' => $producto->id,
                'empresa_id' => $empresaId,
                'sku' => ProductoVariante::generarSku($producto->id, $empresaId),
                'codigo_barras' => null,
                'precio_costo' => null,
                'precio_venta' => null,
                'stock_minimo' => null,
                'activo' => true,
            ]);

            foreach ($atributos as $tipoId => $atributoId) {
                VarianteAtributo::create([
                    'variante_id' => $variante->id,
                    'tipo_atributo_id' => $tipoId,
                    'atributo_id' => $atributoId,
                ]);
            }

            if (! $producto->tiene_variantes) {
                $producto->update(['tiene_variantes' => true]);
            }

            return $variante;
        });

        $variante->load(['atributos.tipoAtributo:id,nombre', 'atributos.atributo:id,valor']);

        return response()->json([
            'message' => 'Variante creada.',
            'data' => [
                'tipo_resultado' => 'variante',
                'id' => $variante->id,
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $producto->codigo,
                'sku' => $variante->sku,
                'codigo_barras' => null,
                'nombre_variante' => $variante->nombreVariante(),
                'precio_venta' => (float) ($producto->precio_venta ?? 0),
                'tiene_variantes' => true,
            ],
        ], 201);
    }

    public function productoRapido(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');

        $user = Auth::user();
        $empresaId = (int) $user->empresa_id;

        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:200'],
            'codigo' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('productos', 'codigo')
                    ->where(fn($q) => $q->where('empresa_id', $empresaId)->whereNull('deleted_at')),
            ],
            'precio_costo' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'atributos' => ['required', 'array', 'min:1'],
            'atributos.*' => ['required', 'integer', 'exists:atributos,id'],
        ]);

        $atributos = collect($data['atributos'])
            ->mapWithKeys(fn($atributoId, $tipoId) => [(int) $tipoId => (int) $atributoId])
            ->filter();

        foreach ($atributos as $tipoId => $atributoId) {
            $valido = Atributo::where('id', $atributoId)
                ->where('empresa_id', $empresaId)
                ->where('tipo_atributo_id', $tipoId)
                ->where('activo', true)
                ->exists();

            if (! $valido) {
                return response()->json(['message' => 'Selecciona atributos validos para la variante.'], 422);
            }
        }

        $producto = null;
        $variante = null;

        DB::transaction(function () use (&$producto, &$variante, $data, $user, $empresaId, $atributos) {
            $producto = Producto::create([
                'empresa_id' => $empresaId,
                'sucursal_id' => (int) $user->sucursal_id,
                'user_id' => $user->id,
                'nombre' => $data['nombre'],
                'codigo' => ($data['codigo'] ?? null) ?: Producto::generarCodigo($empresaId),
                'descripcion' => null,
                'precio_costo' => (float) ($data['precio_costo'] ?? 0),
                'precio_venta' => (float) $data['precio_venta'],
                'stock_minimo' => 0,
                'tiene_variantes' => true,
                'tiene_series' => false,
                'pedido_generico' => false,
                'activo' => true,
            ]);

            $variante = ProductoVariante::create([
                'producto_id' => $producto->id,
                'empresa_id' => $empresaId,
                'sku' => ProductoVariante::generarSku($producto->id, $empresaId),
                'codigo_barras' => null,
                'precio_costo' => (float) ($data['precio_costo'] ?? 0),
                'precio_venta' => (float) $data['precio_venta'],
                'stock_minimo' => 0,
                'activo' => true,
            ]);

            foreach ($atributos as $tipoId => $atributoId) {
                VarianteAtributo::create([
                    'variante_id' => $variante->id,
                    'tipo_atributo_id' => $tipoId,
                    'atributo_id' => $atributoId,
                ]);
            }
        });

        $variante->load(['atributos.tipoAtributo', 'atributos.atributo']);

        return response()->json([
            'message' => 'Producto rapido creado.',
            'data' => [
                'tipo_resultado' => 'variante',
                'id' => $variante->id,
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $producto->codigo,
                'sku' => $variante->sku,
                'codigo_barras' => $variante->codigo_barras,
                'nombre_variante' => $variante->nombreVariante(),
                'precio_venta' => (float) ($variante->precio_venta ?? $producto->precio_venta),
                'tiene_variantes' => true,
                'pedido_generico' => false,
            ],
        ], 201);
    }

    public function pendientesCompra(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');
        $user = Auth::user();
        $buscar = trim((string) $request->input('buscar', ''));

        $detalles = PedidoDetalle::query()
            ->where('estado', 'pendiente')
            ->whereNull('compra_detalle_id')
            ->whereNotNull('producto_id')
            ->whereHas('pedido', fn($query) => $query
                ->where('empresa_id', $user->empresa_id)
                ->where('sucursal_id', $user->sucursal_id)
                ->where('tipo', 'pedido')
                ->whereIn('estado', ['pendiente', 'en_proceso', 'parcial']))
            ->when($buscar !== '', fn($query) => $query->where(function ($subquery) use ($buscar) {
                $subquery
                    ->where('descripcion', 'like', "%{$buscar}%")
                    ->orWhereHas('pedido', fn($pedido) => $pedido
                        ->where('folio', 'like', "%{$buscar}%")
                        ->orWhereHas('cliente', fn($cliente) => $cliente->where('nombre', 'like', "%{$buscar}%")));
            }))
            ->with([
                'pedido:id,cliente_id,folio,created_at',
                'pedido.cliente:id,nombre',
                'producto:id,nombre,codigo,precio_costo,precio_venta,pedido_generico,imagen',
                'variante:id,producto_id,sku,precio_costo,precio_venta,imagen',
            ])
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn($detalle) => [
                'id' => $detalle->id,
                'pedido_id' => $detalle->pedido_id,
                'folio' => $detalle->pedido?->folio,
                'cliente' => $detalle->pedido?->cliente?->nombre,
                'producto_id' => $detalle->producto_id,
                'variante_id' => $detalle->variante_id,
                'producto' => $detalle->producto?->nombre,
                'codigo' => $detalle->producto?->codigo,
                'sku' => $detalle->variante?->sku,
                'descripcion' => $detalle->descripcion,
                'cantidad' => (float) $detalle->cantidad,
                'precio_acordado' => (float) $detalle->precio_acordado,
                'precio_compra' => (float) ($detalle->variante?->precio_costo ?? $detalle->producto?->precio_costo ?? 0),
                'precio_venta' => (float) $detalle->precio_acordado,
                'imagen_url' => $detalle->variante?->imagen_url ?? $detalle->producto?->imagen_url,
            ]);

        return response()->json($detalles);
    }

    public function abonar(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');
        $user = Auth::user();
        $terminal = TerminalResolver::fromRequest($request);
        $pedido = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($id);

        if (in_array($pedido->estado, ['entregado', 'devuelto', 'cancelado', 'vencido'])) {
            return response()->json(['message' => 'Este pedido ya está cerrado.'], 422);
        }

        $data = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'forma_pago' => ['required', Rule::in(['efectivo', 'tarjeta', 'transferencia'])],
            'cuenta_bancaria_id' => [
                'required_if:forma_pago,transferencia', 'nullable', 'integer',
                Rule::exists('cuentas_bancarias', 'id')->where(fn($q) => $q->where('empresa_id', $user->empresa_id)->where('activo', true)),
            ],
            'terminal_pago_id' => [
                'required_if:forma_pago,tarjeta', 'nullable', 'integer',
                Rule::exists('terminales_pago', 'id')->where(fn($q) => $q->where('empresa_id', $user->empresa_id)->where('activo', true)),
            ],
        ]);

        $monto = (float) $data['monto'];
        if ($monto > (float) $pedido->saldo_pendiente) {
            return response()->json(['message' => 'El abono supera el saldo pendiente del pedido.'], 422);
        }

        $corte = $this->corteAbierto((int) $user->empresa_id, (int) $user->sucursal_id, $terminal);
        if (! $corte) {
            return response()->json(['message' => 'No hay caja abierta para registrar el abono.'], 422);
        }

        DB::beginTransaction();
        try {
            $pedido->anticipo = (float) $pedido->anticipo + $monto;
            $pedido->recalcularSaldoPendiente();

            $this->registrarAnticipo(
                $pedido,
                $corte,
                $monto,
                $data['forma_pago'],
                $data['cuenta_bancaria_id'] ?? null,
                $data['terminal_pago_id'] ?? null
            );

            DB::commit();
            return response()->json($pedido->fresh()->load([
                'cliente', 'detalles',
                'saldos.movimientoCaja.cuentaBancaria:id,nombre,banco',
                'saldos.movimientoCaja.terminalPago:id,nombre,banco',
            ]));
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function eliminarAbono(Request $request, int $pedidoId, int $abonoId): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.crear'), 403, 'Sin permiso: pedidos.crear');
        $user = Auth::user();
        $terminal = TerminalResolver::fromRequest($request);

        $pedido = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($pedidoId);

        if (in_array($pedido->estado, ['entregado', 'devuelto', 'cancelado', 'vencido'])) {
            return response()->json(['message' => 'No se puede modificar un pedido cerrado.'], 422);
        }

        $abono = ClienteSaldoMovimiento::where('pedido_id', $pedido->id)
            ->where('tipo', 'abono')
            ->with('movimientoCaja')
            ->findOrFail($abonoId);

        $corte = $this->corteAbierto((int) $user->empresa_id, (int) $user->sucursal_id, $terminal);
        if (! $corte) {
            return response()->json(['message' => 'No hay caja abierta para registrar el egreso del abono eliminado.'], 422);
        }

        DB::beginTransaction();
        try {
            $monto = (float) $abono->monto;

            // El ingreso original nunca se borra: queda como historial de que el abono se
            // cobró. En vez de eso se registra un egreso en la caja abierta de HOY, para que
            // el corte actual refleje que salió ese dinero, sin importar si el abono se cobró
            // en este mismo corte o en uno anterior ya cerrado.
            MovimientoCaja::create([
                'corte_id' => $corte->id,
                'user_id' => Auth::id(),
                'tipo' => 'egreso',
                'forma_pago' => $abono->forma_pago ?? 'efectivo',
                'cuenta_bancaria_id' => $abono->movimientoCaja?->cuenta_bancaria_id,
                'terminal_pago_id' => $abono->movimientoCaja?->terminal_pago_id,
                'monto' => $monto,
                'concepto' => "Eliminacion de abono {$pedido->tipo} {$pedido->folio}",
            ]);
            $corte->recalcularMovimientos();

            $pedido->anticipo = max(0, (float) $pedido->anticipo - $monto);
            $pedido->recalcularSaldoPendiente();

            $abono->delete();

            DB::commit();
            return response()->json([
                'message' => 'Abono eliminado. Se registró un egreso de $' . number_format($monto, 2) . ' en la caja actual.',
                'pedido' => $pedido->fresh()->load([
                'cliente', 'detalles',
                'saldos.movimientoCaja.cuentaBancaria:id,nombre,banco',
                'saldos.movimientoCaja.terminalPago:id,nombre,banco',
            ]),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function clienteResumen(int $clienteId): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('ventas.crear'), 403, 'Sin permiso: ventas.crear');
        $user = Auth::user();
        $this->marcarPedidosVencidos((int) $user->empresa_id, (int) $user->sucursal_id);

        $saldo = ClienteSaldoMovimiento::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('cliente_id', $clienteId)
            ->sum(DB::raw("CASE WHEN tipo IN ('abono','devolucion','ajuste') THEN monto ELSE -monto END"));

        $pedidos = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('cliente_id', $clienteId)
            ->whereIn('estado', ['disponible', 'parcial'])
            ->with([
                'detalles.producto:id,nombre,codigo',
                'detalles.variante:id,sku',
                'detalles.compraDetalle:id,compra_id',
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $tieneProductosPendientes = PedidoDetalle::whereHas('pedido', fn($query) => $query
            ->where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado', 'vencido']))
            ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado'])
            ->exists();

        return response()->json([
            'saldo_favor' => round((float) $saldo, 2),
            'pedidos_disponibles' => $pedidos,
            'tiene_productos_pendientes' => $tieneProductosPendientes,
        ]);
    }

    public function saldoCancelacion(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.cancelar'), 403, 'Sin permiso: pedidos.cancelar');
        $user = Auth::user();

        $pedido = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($id);

        $anticipo = round((float) $pedido->anticipo, 2);
        $saldoDisponibleCliente = $this->saldoDisponibleCliente(
            (int) $pedido->empresa_id,
            (int) $pedido->sucursal_id,
            (int) $pedido->cliente_id
        );

        return response()->json([
            'anticipo' => $anticipo,
            'saldo_disponible_cliente' => $saldoDisponibleCliente,
            // Lo mismo que valida cancelar(): no se puede devolver mas del anticipo de
            // este pedido ni mas de lo que el cliente realmente tiene disponible (parte
            // pudo ya haberse gastado como saldo_aplicado en una venta).
            'maximo_devolucion' => max(0, round(min($anticipo, $saldoDisponibleCliente), 2)),
        ]);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('pedidos.cancelar'), 403, 'Sin permiso: pedidos.cancelar');
        $user = Auth::user();
        $terminal = TerminalResolver::fromRequest($request);

        $data = $request->validate([
            'destino_saldo' => ['nullable', Rule::in(['mantener_saldo', 'efectivo', 'transferencia'])],
            'monto_devolucion' => ['nullable', 'numeric', 'min:0'],
            'cuenta_bancaria_id' => [
                'required_if:destino_saldo,transferencia', 'nullable', 'integer',
                Rule::exists('cuentas_bancarias', 'id')->where(fn($q) => $q->where('empresa_id', $user->empresa_id)->where('activo', true)),
            ],
        ]);

        $destinoSaldo = $data['destino_saldo'] ?? 'mantener_saldo';
        $montoDevolucionSolicitado = round((float) ($data['monto_devolucion'] ?? 0), 2);
        $corte = null;

        $pedido = Pedido::where('empresa_id', $user->empresa_id)
            ->where('sucursal_id', $user->sucursal_id)
            ->findOrFail($id);

        if (in_array($destinoSaldo, ['efectivo', 'transferencia'], true)) {
            $corte = CorteCaja::where('empresa_id', $user->empresa_id)
                ->where('sucursal_id', $user->sucursal_id)
                ->where('terminal', $terminal)
                ->where('estado', 'abierto')
                ->latest('fecha_apertura')
                ->first();

            if (!$corte) {
                return response()->json(['message' => 'No hay caja abierta para registrar la devolucion.'], 422);
            }

            if ($montoDevolucionSolicitado <= 0) {
                return response()->json(['message' => 'Captura el monto a devolver.'], 422);
            }
        }

        // 'vencido' se excluye a propósito: un pedido vencido debe poder cancelarse para
        // decidir qué pasa con su anticipo (mantenerlo o devolverlo), no queda "cerrado".
        if (in_array($pedido->estado, ['entregado', 'devuelto', 'cancelado'])) {
            return response()->json(['message' => 'Este pedido ya está cerrado y no se puede cancelar.'], 422);
        }

        DB::beginTransaction();
        try {
            // Liberar reservas de inventario activas
            InventarioReserva::where('pedido_id', $pedido->id)
                ->where('estado', 'activa')
                ->update(['estado' => 'liberada']);

            // Cancelar todos los detalles y limpiar vínculo con compra
            PedidoDetalle::where('pedido_id', $pedido->id)
                ->whereNotIn('estado', ['entregado', 'devuelto', 'cancelado'])
                ->update([
                    'estado' => 'cancelado',
                    'compra_detalle_id' => null,
                    'disponible_desde' => null,
                ]);

            // El anticipo ya quedó registrado como 'abono' en cliente_saldo_movimientos desde que se
            // capturó (registrarAnticipo/agregarAbono), y cualquier parte ya usada en una venta se
            // descontó ahí mismo como 'aplicacion'. El saldo del cliente (SUM abono+devolucion+ajuste
            // - aplicacion) ya refleja correctamente lo que queda disponible del anticipo: no hay que
            // volver a acreditarlo aquí, o se duplica (ver bug: cancelar pedido parcial + cancelar la
            // venta que ya usó el anticipo dejaba doble saldo a favor).
            $anticipo = (float) $pedido->anticipo;
            $montoDevuelto = 0.0;

            if (in_array($destinoSaldo, ['efectivo', 'transferencia'], true)) {
                $saldoDisponible = $this->saldoDisponibleCliente((int) $pedido->empresa_id, (int) $pedido->sucursal_id, (int) $pedido->cliente_id);
                $maximoDevolucion = round(min($anticipo, $saldoDisponible), 2);

                if ($montoDevolucionSolicitado > $maximoDevolucion) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'El monto a devolver supera el saldo disponible del anticipo. Disponible: $' . number_format($maximoDevolucion, 2),
                    ], 422);
                }

                $this->registrarDevolucionSaldoPedido(
                    $pedido,
                    $corte,
                    $destinoSaldo,
                    $montoDevolucionSolicitado,
                    $data['cuenta_bancaria_id'] ?? null
                );
                $montoDevuelto = $montoDevolucionSolicitado;
            }

            // El estado de cabecera se recalcula a partir de los renglones: si alguno ya
            // se entregó (se vendió), el pedido queda 'parcial' en vez de 'cancelado', para
            // no perder el rastro de que parte sí se vendió.
            $pedido->actualizarEstadoPorDetalles();
            $pedido->recalcularSaldoPendiente();
            $pedido->refresh();

            DB::commit();

            $huboEntrega = $pedido->estado !== 'cancelado';
            $mensaje = $huboEntrega
                ? 'Se canceló la parte pendiente del pedido. Los renglones ya entregados no se modificaron.'
                : 'Pedido cancelado.';

            if ($anticipo > 0) {
                if ($montoDevuelto > 0) {
                    $mensaje .= ' Se devolvieron $' . number_format($montoDevuelto, 2) . ' por ' . ($destinoSaldo === 'efectivo' ? 'efectivo' : 'transferencia') . '.';
                } else {
                    $mensaje .= ' El saldo no usado del anticipo queda disponible como saldo a favor del cliente.';
                }
            }

            return response()->json([
                'message' => $mensaje,
                'pedido' => $pedido->load(['cliente', 'detalles']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function saldoDisponibleCliente(int $empresaId, int $sucursalId, int $clienteId): float
    {
        return round((float) ClienteSaldoMovimiento::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('cliente_id', $clienteId)
            ->sum(DB::raw("CASE WHEN tipo IN ('abono','devolucion','ajuste') THEN monto ELSE -monto END")), 2);
    }

    private function registrarDevolucionSaldoPedido(
        Pedido $pedido,
        CorteCaja $corte,
        string $formaPago,
        float $monto,
        ?int $cuentaBancariaId = null
    ): void {
        $movimientoCaja = MovimientoCaja::create([
            'corte_id' => $corte->id,
            'user_id' => Auth::id(),
            'tipo' => 'egreso',
            'forma_pago' => $formaPago,
            'cuenta_bancaria_id' => $formaPago === 'transferencia' ? $cuentaBancariaId : null,
            'terminal_pago_id' => null,
            'monto' => $monto,
            'concepto' => "Devolucion saldo {$pedido->tipo} {$pedido->folio}",
        ]);

        $saldoAnterior = $this->saldoDisponibleCliente(
            (int) $pedido->empresa_id,
            (int) $pedido->sucursal_id,
            (int) $pedido->cliente_id
        );

        ClienteSaldoMovimiento::create([
            'empresa_id' => $pedido->empresa_id,
            'sucursal_id' => $pedido->sucursal_id,
            'cliente_id' => $pedido->cliente_id,
            'pedido_id' => $pedido->id,
            'corte_id' => $corte->id,
            'movimiento_caja_id' => $movimientoCaja->id,
            'user_id' => Auth::id(),
            'tipo' => 'aplicacion',
            'forma_pago' => $formaPago,
            'monto' => $monto,
            'saldo_resultante' => max(0, $saldoAnterior - $monto),
            'concepto' => "Devolucion de saldo por cancelacion {$pedido->folio}",
        ]);

        $corte->recalcularMovimientos();
    }

    private function registrarAnticipo(
        Pedido $pedido,
        CorteCaja $corte,
        float $monto,
        string $formaPago,
        ?int $cuentaBancariaId = null,
        ?int $terminalPagoId = null
    ): void {
        $movimientoCaja = MovimientoCaja::create([
            'corte_id' => $corte->id,
            'user_id' => Auth::id(),
            'tipo' => 'ingreso',
            'forma_pago' => $formaPago,
            'cuenta_bancaria_id' => $cuentaBancariaId,
            'terminal_pago_id' => $terminalPagoId,
            'monto' => $monto,
            'concepto' => "Anticipo {$pedido->tipo} {$pedido->folio}",
        ]);

        $saldoAnterior = ClienteSaldoMovimiento::where('empresa_id', $pedido->empresa_id)
            ->where('sucursal_id', $pedido->sucursal_id)
            ->where('cliente_id', $pedido->cliente_id)
            ->sum(DB::raw("CASE WHEN tipo IN ('abono','devolucion','ajuste') THEN monto ELSE -monto END"));

        ClienteSaldoMovimiento::create([
            'empresa_id'         => $pedido->empresa_id,
            'sucursal_id'        => $pedido->sucursal_id,
            'cliente_id'         => $pedido->cliente_id,
            'pedido_id'          => $pedido->id,
            'corte_id'           => $corte->id,
            'movimiento_caja_id' => $movimientoCaja->id,
            'user_id'            => Auth::id(),
            'tipo'               => 'abono',
            'forma_pago'         => $formaPago,
            'monto'              => $monto,
            'saldo_resultante'   => (float) $saldoAnterior + $monto,
            'concepto'           => "Anticipo {$pedido->folio}",
        ]);

        $corte->recalcularMovimientos();
    }

    private function reservarInventario(Pedido $pedido, PedidoDetalle $detalle, int $productoId, ?int $varianteId, int $cantidad): void
    {
        $inv = Inventario::where([
            'empresa_id' => $pedido->empresa_id,
            'sucursal_id' => $pedido->sucursal_id,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
        ])->lockForUpdate()->first();

        $reservado = InventarioReserva::where([
            'empresa_id' => $pedido->empresa_id,
            'sucursal_id' => $pedido->sucursal_id,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
            'estado' => 'activa',
        ])->sum('cantidad');

        $disponible = (float) ($inv?->stock ?? 0) - (float) $reservado;
        if ($disponible < $cantidad) {
            throw new \RuntimeException("Stock disponible insuficiente para apartar {$detalle->descripcion}. Disponible: {$disponible}.");
        }

        InventarioReserva::create([
            'empresa_id' => $pedido->empresa_id,
            'sucursal_id' => $pedido->sucursal_id,
            'pedido_id' => $pedido->id,
            'pedido_detalle_id' => $detalle->id,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
            'cantidad' => $cantidad,
            'estado' => 'activa',
        ]);
    }

    private function validarProductoTenant(int $empresaId, int $productoId, ?int $varianteId): void
    {
        $producto = Producto::where('empresa_id', $empresaId)->findOrFail($productoId);

        if ($varianteId) {
            ProductoVariante::where('empresa_id', $empresaId)
                ->where('producto_id', $producto->id)
                ->findOrFail($varianteId);
        }
    }

    private function corteAbierto(int $empresaId, int $sucursalId, string $terminal): ?CorteCaja
    {
        return CorteCaja::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('terminal', $terminal)
            ->where('estado', 'abierto')
            ->latest('fecha_apertura')
            ->first();
    }

    private function estadoPago(float $subtotal, float $anticipo): string
    {
        if ($anticipo <= 0) return 'sin_anticipo';
        if ($anticipo >= $subtotal && $subtotal > 0) return 'pagado';
        return 'con_anticipo';
    }

    /**
     * Marca como 'vencido' los pedidos/apartados que ya no deberian seguir reservando
     * inventario, y libera esa reserva. Se hace de forma perezosa (al consultar) en vez
     * de con un job programado, para no depender de que el servidor tenga el scheduler
     * de Laravel corriendo.
     *
     * Apartados: la reserva existe desde que se crea el apartado, asi que vencen por
     * fecha_promesa completa (capturada a mano o generada con dias_vigencia_apartado).
     *
     * Pedidos: no hay nada reservado hasta que el renglon llega via una compra
     * (PedidoDetalle.estado = 'disponible'). Por eso vencen por renglon, contando los
     * dias desde que CADA renglon llego (disponible_desde), no desde fecha_promesa (que
     * para un pedido es solo el estimado informativo de llegada). Es opt-in: sin
     * dias_vigencia_pedido configurado en la empresa, ningun pedido vence por esta via.
     */
    private function marcarPedidosVencidos(int $empresaId, int $sucursalId): void
    {
        $hoy = now('America/Mexico_City')->startOfDay();

        $apartadosVencidosIds = Pedido::where('empresa_id', $empresaId)
            ->where('sucursal_id', $sucursalId)
            ->where('tipo', 'apartado')
            ->whereIn('estado', ['disponible', 'parcial'])
            ->whereNotNull('fecha_promesa')
            ->where('fecha_promesa', '<', $hoy)
            ->pluck('id');

        if ($apartadosVencidosIds->isNotEmpty()) {
            Pedido::whereIn('id', $apartadosVencidosIds)->update(['estado' => 'vencido']);

            InventarioReserva::whereIn('pedido_id', $apartadosVencidosIds)
                ->where('estado', 'activa')
                ->update(['estado' => 'liberada']);
        }

        $diasVigenciaPedido = (int) (Empresa::find($empresaId)?->config_pedidos['dias_vigencia_pedido'] ?? 0);

        if ($diasVigenciaPedido > 0) {
            $limite = now('America/Mexico_City')->subDays($diasVigenciaPedido);

            $detallesVencidos = PedidoDetalle::where('estado', 'disponible')
                ->whereNotNull('disponible_desde')
                ->where('disponible_desde', '<', $limite)
                ->whereHas('pedido', fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->where('sucursal_id', $sucursalId)
                    ->where('tipo', 'pedido')
                    ->whereIn('estado', ['pendiente', 'en_proceso', 'disponible', 'parcial']))
                ->get(['id', 'pedido_id']);

            if ($detallesVencidos->isNotEmpty()) {
                Pedido::whereIn('id', $detallesVencidos->pluck('pedido_id')->unique())
                    ->update(['estado' => 'vencido']);

                InventarioReserva::whereIn('pedido_detalle_id', $detallesVencidos->pluck('id'))
                    ->where('estado', 'activa')
                    ->update(['estado' => 'liberada']);
            }
        }
    }
}
