<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\InventarioExhibicion;
use App\Support\ProductVariantSearch;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExhibicionController extends Controller
{
    /**
     * GET /api/exhibicion
     */
    public function index(Request $request)
    {
        $user       = Auth::user();
        $empresaId  = $user->empresa_id;
        $sucursalId = $user->sucursal_id;
        $busqueda = trim((string) $request->input('busqueda', ''));
        $varianteExactaIds = $this->variantesPorSkuExacto($busqueda);
        $busquedaEsVarianteExacta = $busqueda !== '' && $varianteExactaIds->isNotEmpty();

        $query = Inventario::with([
            'producto:id,nombre,codigo,imagen,tiene_variantes,categoria_id,marca_id,modelo_id',
            'producto.categoria:id,nombre',
            'producto.marca:id,nombre',
            'producto.modelo:id,nombre,marca_id',
            'variante:id,producto_id,sku,codigo_barras,imagen',
            'variante.atributos.tipoAtributo:id,nombre',
            'variante.atributos.atributo:id,valor',
            'varianteExhibida:id,producto_id,sku',
            'varianteExhibida.atributos.tipoAtributo:id,nombre',
            'varianteExhibida.atributos.atributo:id,valor',
        ])
            ->deSucursal($empresaId, $sucursalId);

        // Filtro de estado a nivel producto padre.
        match ($request->input('filtro')) {
            'exhibidos' => $query->whereExists(fn($sub) => $this->exhibicionActivaSubquery($sub)),
            'sinExhibicion' => $query
                ->where('inventario.stock', '>', 0)
                ->whereNotExists(fn($sub) => $this->exhibicionActivaSubquery($sub)),
            default => $query->where(fn($q) => $q
                ->whereExists(fn($sub) => $this->exhibicionActivaSubquery($sub))
                ->orWhere('inventario.stock', '>', 0)),
        };

        // Búsqueda
        if ($busqueda !== '') {
            if ($busquedaEsVarianteExacta) {
                $query->whereIn('inventario.variante_id', $varianteExactaIds);
            } else {
                $query->where(function ($q) use ($busqueda) {
                    $q->whereHas(
                        'producto',
                        fn($p) =>
                        $p->where('nombre', 'like', "%{$busqueda}%")
                            ->orWhere('codigo', 'like', "%{$busqueda}%")
                            ->orWhereHas('categoria', fn($c) => $c->where('nombre', 'like', "%{$busqueda}%"))
                            ->orWhereHas('marca', fn($m) => $m->where('nombre', 'like', "%{$busqueda}%"))
                            ->orWhereHas('modelo', fn($m) => $m->where('nombre', 'like', "%{$busqueda}%"))
                    )
                        ->orWhereHas(
                            'variante',
                            fn($v) =>
                            $v->where('sku', 'like', "%{$busqueda}%")
                                ->orWhere('codigo_barras', 'like', "%{$busqueda}%")
                                ->orWhereHas(
                                    'atributos.atributo',
                                    fn($a) => $a->where('valor', 'like', "%{$busqueda}%")
                                )
                                ->orWhereHas(
                                    'atributos.tipoAtributo',
                                    fn($t) => $t->where('nombre', 'like', "%{$busqueda}%")
                                )
                        );
                });
            }
        }

        // Filtros comerciales
        if ($request->filled('categoria_id')) {
            $categoriaId = (int) $request->input('categoria_id');
            $query->whereHas('producto', fn($p) => $p->where('categoria_id', $categoriaId));
        }

        if ($request->filled('marca_id')) {
            $marcaId = (int) $request->input('marca_id');
            $query->whereHas('producto', fn($p) => $p->where('marca_id', $marcaId));
        }

        if ($request->filled('modelo_id')) {
            $modeloId = (int) $request->input('modelo_id');
            $query->whereHas('producto', fn($p) => $p->where('modelo_id', $modeloId));
        }

        // Solo mostrar ítems con stock O que estén exhibidos (el exhibido siempre aparece)
        $query->where(function ($q) {
            $q->where('inventario.stock', '>', 0)
                ->orWhereExists(fn($sub) => $this->exhibicionActivaSubquery($sub));
        });

        $query->leftJoin('productos as p_orden', 'p_orden.id', '=', 'inventario.producto_id')
            ->leftJoin('categorias as c_orden', 'c_orden.id', '=', 'p_orden.categoria_id')
            ->leftJoin('marcas as m_orden', 'm_orden.id', '=', 'p_orden.marca_id')
            ->leftJoin('modelos as mo_orden', 'mo_orden.id', '=', 'p_orden.modelo_id')
            ->select('inventario.*');

        match ($request->input('orden', 'prioridad')) {
            'categoria' => $query
                ->orderByRaw('c_orden.nombre IS NULL')
                ->orderBy('c_orden.nombre')
                ->orderByRaw('m_orden.nombre IS NULL')
                ->orderBy('m_orden.nombre')
                ->orderBy('p_orden.nombre'),
            'marca' => $query
                ->orderByRaw('m_orden.nombre IS NULL')
                ->orderBy('m_orden.nombre')
                ->orderByRaw('mo_orden.nombre IS NULL')
                ->orderBy('mo_orden.nombre')
                ->orderBy('p_orden.nombre'),
            'stock' => $query
                ->orderBy('inventario.stock', 'desc')
                ->orderBy('p_orden.nombre'),
            'nombre' => $query->orderBy('p_orden.nombre')->orderBy('inventario.variante_id'),
            default => $query
                ->orderByRaw("(select count(*) from inventario_exhibiciones ie where ie.producto_id = inventario.producto_id and ie.empresa_id = inventario.empresa_id and ie.sucursal_id = inventario.sucursal_id and ie.activo = 1 and ie.estado = 'activa')")
                ->orderByRaw('c_orden.nombre IS NULL')
                ->orderBy('c_orden.nombre')
                ->orderByRaw('m_orden.nombre IS NULL')
                ->orderBy('m_orden.nombre')
                ->orderBy('p_orden.nombre')
                ->orderBy('inventario.variante_id'),
        };

        $inventarios = $query->get();
        $this->agregarNombreVariante($inventarios);
        $detalleInventarios = $busquedaEsVarianteExacta
            ? $inventarios
            : Inventario::with([
            'producto:id,nombre,codigo,imagen,tiene_variantes,categoria_id,marca_id,modelo_id',
            'producto.categoria:id,nombre',
            'producto.marca:id,nombre',
            'producto.modelo:id,nombre,marca_id',
            'variante:id,producto_id,sku,codigo_barras,imagen',
            'variante.atributos.tipoAtributo:id,nombre',
            'variante.atributos.atributo:id,valor',
            'varianteExhibida:id,producto_id,sku',
            'varianteExhibida.atributos.tipoAtributo:id,nombre',
            'varianteExhibida.atributos.atributo:id,valor',
        ])
            ->deSucursal($empresaId, $sucursalId)
            ->whereIn('producto_id', $inventarios->pluck('producto_id')->unique())
            ->where(fn($q) => $q
                ->where('stock', '>', 0)
                ->orWhereExists(fn($sub) => $this->exhibicionActivaSubquery($sub)))
            ->get();
        $this->agregarNombreVariante($detalleInventarios);
        $exhibicionesActivas = InventarioExhibicion::with([
            'atributo:id,valor',
            'variante:id,producto_id,sku',
            'variante.atributos.tipoAtributo:id,nombre',
            'variante.atributos.atributo:id,valor',
        ])
            ->deSucursal($empresaId, $sucursalId)
            ->activas()
            ->whereIn('producto_id', $inventarios->pluck('producto_id')->unique())
            ->get()
            ->groupBy('producto_id');

        $productos = $this->agruparPorProducto($inventarios, $detalleInventarios->groupBy('producto_id'), $exhibicionesActivas, $busquedaEsVarianteExacta);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = (int) $request->input('per_page', 50);
        $items = new LengthAwarePaginator(
            $productos->forPage($page, $perPage)->values(),
            $productos->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        // Contadores (solo los relevantes para control de exhibición)
        $base  = Inventario::deSucursal($empresaId, $sucursalId);
        $stats = [
            'exhibidos' => InventarioExhibicion::deSucursal($empresaId, $sucursalId)
                ->activas()
                ->distinct('producto_id')
                ->count('producto_id'),
            'sinExhibicion' => (clone $base)
                ->where('inventario.stock', '>', 0)
                ->whereNotExists(fn($sub) => $this->exhibicionActivaSubquery($sub))
                ->distinct('producto_id')
                ->count('producto_id'),
        ];

        // Catálogo de estados para el frontend
        $estadosExhibicion = Inventario::ESTADOS_EXHIBICION;

        return response()->json([
            'items'            => $items,
            'stats'            => $stats,
            'estadosExhibicion' => $estadosExhibicion,
            'catalogos'         => $this->catalogos($empresaId, $request->integer('marca_id') ?: null),
        ]);
    }

    /**
     * GET /api/exhibicion/{inventario}/variantes
     * Devuelve las variantes con stock disponible para elegir cuál exhibir.
     * Solo aplica a productos con variantes.
     */
    public function variantes(Inventario $inventario)
    {
        $user = Auth::user();

        if (
            $inventario->empresa_id  !== $user->empresa_id ||
            $inventario->sucursal_id !== $user->sucursal_id
        ) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        if (! $inventario->producto->tiene_variantes) {
            return response()->json(['variantes' => []]);
        }

        // Obtener inventario de cada variante de este producto en esta sucursal
        $variantes = Inventario::with([
            'variante:id,producto_id,sku,codigo_barras,imagen',
            'variante.atributos.tipoAtributo:id,nombre',
            'variante.atributos.atributo:id,valor',
        ])
            ->deSucursal($inventario->empresa_id, $inventario->sucursal_id)
            ->where('producto_id', $inventario->producto_id)
            ->whereNotNull('variante_id')
            ->where('stock', '>', 0)
            ->get()
            ->map(function ($inv) {
                $color = $this->atributoColor($inv->variante);

                return [
                    'inventario_id' => $inv->id,
                    'variante_id'   => $inv->variante_id,
                    'sku'           => $inv->variante?->sku ?? "#{$inv->variante_id}",
                    'nombre_variante' => $inv->variante?->nombreVariante() ?: null,
                    'grupo_exhibicion' => $color ? 'color' : 'producto',
                    'grupo_label' => $color?->atributo?->valor,
                    'stock'         => (float) $inv->stock,
                ];
            });

        return response()->json(['variantes' => $variantes]);
    }

    /**
     * PATCH /api/exhibicion/{inventario}/exhibir
     *
     * Body esperado:
     * {
     *   "estado_exhibicion":   "perfecto" | "caja_abierta" | "con_detalles",
     *   "variante_exhibida_id": 12  (opcional, solo si tiene variantes)
     * }
     */
    public function exhibir(Request $request, Inventario $inventario)
    {
        $user = Auth::user();

        if (
            $inventario->empresa_id  !== $user->empresa_id ||
            $inventario->sucursal_id !== $user->sucursal_id
        ) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $request->validate([
            'estado_exhibicion'    => 'required|in:perfecto,caja_abierta,con_detalles',
            'tipo_cobertura'       => 'nullable|in:producto,color,variante',
            'atributo_id'          => 'nullable|integer|exists:atributos,id',
            'variante_exhibida_id' => 'nullable|integer|exists:producto_variantes,id',
        ]);

        try {
            $exhibicion = $this->crearExhibicion(
                $inventario,
                $request->input('estado_exhibicion'),
                $request->input('tipo_cobertura') ?: ($request->filled('variante_exhibida_id') ? 'variante' : 'producto'),
                $request->input('variante_exhibida_id'),
                $request->input('atributo_id')
            );
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Producto marcado como exhibido.',
            'item'    => $exhibicion,
        ]);
    }

    /**
     * PATCH /api/exhibicion/{inventario}/quitar
     */
    public function quitar(Inventario $inventario)
    {
        $user = Auth::user();

        if (
            $inventario->empresa_id  !== $user->empresa_id ||
            $inventario->sucursal_id !== $user->sucursal_id
        ) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $exhibicion = InventarioExhibicion::deSucursal($user->empresa_id, $user->sucursal_id)
            ->activas()
            ->where('producto_id', $inventario->producto_id)
            ->where(function ($q) use ($inventario) {
                $q->where('id', request()->integer('exhibicion_id'))
                    ->orWhere('variante_id', $inventario->variante_id)
                    ->orWhereNull('variante_id');
            })
            ->first();

        if ($exhibicion) {
            $exhibicion->retirar();
        }

        return response()->json([
            'message' => 'Producto quitado de exhibición.',
            'item'    => $exhibicion,
        ]);
    }

    private function agregarNombreVariante($inventarios): void
    {
        foreach ($inventarios as $inventario) {
            foreach (['variante', 'varianteExhibida'] as $relacion) {
                if ($inventario->{$relacion}) {
                    $inventario->{$relacion}->setAttribute(
                        'nombre_variante',
                        $inventario->{$relacion}->nombreVariante() ?: null
                    );
                }
            }
        }
    }

    private function crearExhibicion(Inventario $inventario, string $estado, string $tipoCobertura, ?int $varianteId, ?int $atributoId): InventarioExhibicion
    {
        if (! array_key_exists($estado, Inventario::ESTADOS_EXHIBICION)) {
            throw new \RuntimeException('Estado de exhibición no válido.');
        }

        if ($tipoCobertura === 'producto') {
            $varianteId = null;
            $atributoId = null;
        } elseif ($tipoCobertura === 'variante') {
            $atributoId = null;
        } elseif ($tipoCobertura === 'color') {
            $varianteId = null;
            if (! $atributoId || ! $this->productoTieneColor($inventario->producto_id, $atributoId)) {
                throw new \RuntimeException('Color de exhibición no válido.');
            }
        } else {
            throw new \RuntimeException('Tipo de cobertura no válido.');
        }

        $stockInventario = $varianteId
            ? Inventario::deSucursal($inventario->empresa_id, $inventario->sucursal_id)
                ->where('producto_id', $inventario->producto_id)
                ->where('variante_id', $varianteId)
                ->first()
            : $inventario;

        $stockDisponible = $tipoCobertura === 'color'
            ? Inventario::deSucursal($inventario->empresa_id, $inventario->sucursal_id)
                ->where('producto_id', $inventario->producto_id)
                ->where('stock', '>', 0)
                ->whereExists(fn($sub) => $sub
                    ->selectRaw('1')
                    ->from('variante_atributos as va_color')
                    ->whereColumn('va_color.variante_id', 'inventario.variante_id')
                    ->where('va_color.atributo_id', $atributoId))
                ->exists()
            : ($stockInventario && (float) $stockInventario->stock > 0);

        if (! $stockDisponible) {
            throw new \RuntimeException('Sin stock disponible para exhibir.');
        }

        $duplicada = InventarioExhibicion::deSucursal($inventario->empresa_id, $inventario->sucursal_id)
            ->activas()
            ->where('producto_id', $inventario->producto_id)
            ->where('tipo_cobertura', $tipoCobertura)
            ->when($tipoCobertura === 'variante', fn($q) => $q->where('variante_id', $varianteId))
            ->when($tipoCobertura === 'color', fn($q) => $q->where('atributo_id', $atributoId))
            ->when($tipoCobertura === 'producto', fn($q) => $q->whereNull('variante_id')->whereNull('atributo_id'))
            ->exists();

        if ($duplicada) {
            throw new \RuntimeException('Ese producto o variante ya está exhibido.');
        }

        $activas = InventarioExhibicion::deSucursal($inventario->empresa_id, $inventario->sucursal_id)
            ->activas()
            ->where('producto_id', $inventario->producto_id);

        $tieneCoberturaCompleta = (clone $activas)
            ->where('tipo_cobertura', 'producto')
            ->exists();

        if ($tipoCobertura !== 'producto' && $tieneCoberturaCompleta) {
            throw new \RuntimeException('El producto completo ya esta exhibido. Quita esa cobertura para marcar solo color o talla.');
        }

        if ($tipoCobertura === 'producto' && (clone $activas)->exists()) {
            throw new \RuntimeException('Ya existen coberturas activas. Quitalas antes de exhibir el producto completo.');
        }

        if ($tipoCobertura === 'variante') {
            $colorVariante = $this->atributoColorIdDeVariante($varianteId);

            if ($colorVariante && (clone $activas)->where('tipo_cobertura', 'color')->where('atributo_id', $colorVariante)->exists()) {
                throw new \RuntimeException('Ese color ya esta exhibido. Quita la cobertura de color para marcar una talla especifica.');
            }
        }

        if ($tipoCobertura === 'color') {
            $variantesDelColor = DB::table('producto_variantes as pv')
                ->join('variante_atributos as va', 'va.variante_id', '=', 'pv.id')
                ->where('pv.producto_id', $inventario->producto_id)
                ->where('va.atributo_id', $atributoId)
                ->pluck('pv.id');

            if ($variantesDelColor->isNotEmpty() && (clone $activas)->where('tipo_cobertura', 'variante')->whereIn('variante_id', $variantesDelColor)->exists()) {
                throw new \RuntimeException('Ese color ya tiene tallas exhibidas. Quitalas antes de exhibir el color completo.');
            }
        }

        $exhibicion = InventarioExhibicion::create([
            'empresa_id' => $inventario->empresa_id,
            'sucursal_id' => $inventario->sucursal_id,
            'producto_id' => $inventario->producto_id,
            'variante_id' => $varianteId,
            'atributo_id' => $atributoId,
            'user_id' => Auth::id(),
            'tipo_cobertura' => $tipoCobertura,
            'estado_exhibicion' => $estado,
            'estado' => 'activa',
            'activo' => true,
        ]);

        return $exhibicion;
    }

    private function productoTieneColor(int $productoId, int $atributoId): bool
    {
        return DB::table('producto_variantes as pv')
            ->join('variante_atributos as va', 'va.variante_id', '=', 'pv.id')
            ->join('tipo_atributos as ta', 'ta.id', '=', 'va.tipo_atributo_id')
            ->where('pv.producto_id', $productoId)
            ->where('va.atributo_id', $atributoId)
            ->whereIn(DB::raw('LOWER(ta.nombre)'), Inventario::nombresAtributoColor())
            ->exists();
    }

    private function atributoColorIdDeVariante(?int $varianteId): ?int
    {
        if (! $varianteId) {
            return null;
        }

        return DB::table('variante_atributos as va')
            ->join('tipo_atributos as ta', 'ta.id', '=', 'va.tipo_atributo_id')
            ->where('va.variante_id', $varianteId)
            ->whereIn(DB::raw('LOWER(ta.nombre)'), Inventario::nombresAtributoColor())
            ->value('va.atributo_id');
    }

    private function exhibicionActivaSubquery($sub)
    {
        return $sub->selectRaw('1')
            ->from('inventario_exhibiciones as ie')
            ->whereColumn('ie.producto_id', 'inventario.producto_id')
            ->whereColumn('ie.empresa_id', 'inventario.empresa_id')
            ->whereColumn('ie.sucursal_id', 'inventario.sucursal_id')
            ->where('ie.activo', true)
            ->where('ie.estado', 'activa');
    }

    private function variantesPorSkuExacto(string $busqueda)
    {
        if ($busqueda === '') {
            return collect();
        }

        return DB::table('producto_variantes')
            ->where('sku', $busqueda)
            ->orWhere('codigo_barras', $busqueda)
            ->pluck('id');
    }

    private function agruparPorProducto($inventarios, $detallesPorProducto, $exhibicionesPorProducto, bool $resultadoEspecifico = false)
    {
        return $inventarios
            ->groupBy('producto_id')
            ->map(function ($grupo) use ($detallesPorProducto, $exhibicionesPorProducto, $resultadoEspecifico) {
                $primero = $grupo->first();
                $detalleGrupo = $detallesPorProducto->get($primero->producto_id, $grupo);
                $exhibiciones = $exhibicionesPorProducto->get($primero->producto_id, collect());
                if ($resultadoEspecifico) {
                    $exhibiciones = $this->filtrarExhibicionesParaInventarios($exhibiciones, $detalleGrupo);
                }
                $producto = $primero->producto;
                $exhibicion = $exhibiciones->first();
                $condicionResumen = $this->condicionResumen($exhibiciones);
                $inventarioAccion = $detalleGrupo->first(fn($inv) => $inv->variante_id === null && (float) $inv->stock > 0)
                    ?? $detalleGrupo->first(fn($inv) => (float) $inv->stock > 0)
                    ?? $primero;
                $stockTotal = (float) $detalleGrupo->sum(fn($inv) => (float) $inv->stock);
                $variantes = $detalleGrupo
                    ->filter(fn($inv) => $inv->variante_id !== null)
                    ->map(fn($inv) => $this->varianteParaExhibicion($inv, $exhibiciones))
                    ->values();
                $grupos = $variantes
                    ->groupBy('grupo_label')
                    ->map(fn($items, $label) => [
                        'label' => $label ?: 'Sin color',
                        'atributo_id' => $items->first()['grupo_atributo_id'] ?? null,
                        'exhibido_color' => (bool) ($items->first()['exhibido_color'] ?? false),
                        'exhibicion_id' => $items->first()['exhibicion_color_id'] ?? null,
                        'variantes' => $items->values(),
                    ])
                    ->sortBy('label')
                    ->values();

                return [
                    'id' => "producto-{$primero->producto_id}",
                    'producto_id' => $primero->producto_id,
                    'inventario_id' => $inventarioAccion?->id,
                    'producto' => $producto,
                    'variante' => $resultadoEspecifico ? $inventarioAccion?->variante : null,
                    'resultado_especifico' => $resultadoEspecifico,
                    'stock' => $stockTotal,
                    'stock_bodega' => max(0, $stockTotal - $exhibiciones->count()),
                    'exhibido' => $exhibiciones->isNotEmpty(),
                    'exhibicion_id' => $exhibicion?->id,
                    'exhibiciones_count' => $exhibiciones->count(),
                    'exhibiciones' => $exhibiciones
                        ->map(fn($exh) => $this->exhibicionResumen($exh))
                        ->values(),
                    'estado_exhibicion' => $exhibicion?->estado_exhibicion,
                    'condicion_resumen' => $condicionResumen,
                    'variante_exhibida' => $exhibicion?->variante,
                    'cobertura_label' => $this->coberturaProducto($exhibiciones),
                    'tiene_variantes' => (bool) ($producto?->tiene_variantes ?? false),
                    'colores_count' => $variantes->pluck('grupo_label')->filter()->unique()->count(),
                    'variantes_count' => $variantes->count(),
                    'variantes_grupos' => $grupos,
                ];
            })
            ->values();
    }

    private function varianteParaExhibicion(Inventario $inventario, $exhibiciones): array
    {
        $variante = $inventario->variante;
        $exhibicion = $exhibiciones->firstWhere('variante_id', $inventario->variante_id);
        $color = $this->atributoColor($variante);
        $exhibicionColor = $color
            ? $exhibiciones->first(fn($exh) => $exh->tipo_cobertura === 'color' && (int) $exh->atributo_id === (int) $color->atributo_id)
            : null;
        $detalle = collect($variante?->atributos ?? [])
            ->reject(fn($attr) => $color && (int) $attr->atributo_id === (int) $color->atributo_id)
            ->map(fn($attr) => $attr->atributo?->valor)
            ->filter()
            ->values()
            ->join(' / ');

        return [
            'inventario_id' => $inventario->id,
            'variante_id' => $inventario->variante_id,
            'sku' => $variante?->sku,
            'nombre_variante' => $variante?->nombre_variante ?: $variante?->nombreVariante(),
            'grupo_label' => $color?->atributo?->valor ?: 'Sin color',
            'grupo_atributo_id' => $color?->atributo_id,
            'detalle_label' => $detalle ?: ($variante?->sku ?? 'Variante'),
            'stock' => (float) $inventario->stock,
            'exhibido' => (bool) ($exhibicion || $exhibicionColor),
            'exhibicion_id' => $exhibicion?->id,
            'exhibido_color' => (bool) $exhibicionColor,
            'exhibicion_color_id' => $exhibicionColor?->id,
            'estado_exhibicion' => $exhibicion?->estado_exhibicion,
        ];
    }

    private function filtrarExhibicionesParaInventarios($exhibiciones, $inventarios)
    {
        $varianteIds = $inventarios->pluck('variante_id')->filter()->map(fn($id) => (int) $id)->values();
        $colorIds = $inventarios
            ->map(fn($inv) => $this->atributoColor($inv->variante)?->atributo_id)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        return $exhibiciones
            ->filter(fn($exh) => $exh->tipo_cobertura === 'producto'
                || ($exh->tipo_cobertura === 'variante' && $varianteIds->contains((int) $exh->variante_id))
                || ($exh->tipo_cobertura === 'color' && $colorIds->contains((int) $exh->atributo_id)))
            ->values();
    }

    private function exhibicionResumen(InventarioExhibicion $exhibicion): array
    {
        if (! $exhibicion->variante_id) {
            if ($exhibicion->tipo_cobertura === 'color') {
                return [
                    'id' => $exhibicion->id,
                    'tipo' => 'color',
                    'label' => 'Color: ' . ($exhibicion->atributo?->valor ?? 'Sin color'),
                    'atributo_id' => $exhibicion->atributo_id,
                    'estado_exhibicion' => $exhibicion->estado_exhibicion,
                    'estado_label' => Inventario::ESTADOS_EXHIBICION[$exhibicion->estado_exhibicion] ?? $exhibicion->estado_exhibicion,
                ];
            }

            return [
                'id' => $exhibicion->id,
                'tipo' => 'producto',
                'label' => 'Producto completo',
                'estado_exhibicion' => $exhibicion->estado_exhibicion,
                'estado_label' => Inventario::ESTADOS_EXHIBICION[$exhibicion->estado_exhibicion] ?? $exhibicion->estado_exhibicion,
            ];
        }

        $variante = $exhibicion->variante;

        return [
            'id' => $exhibicion->id,
            'tipo' => 'variante',
            'label' => $variante?->nombreVariante() ?: ($variante?->sku ?? 'Variante especifica'),
            'sku' => $variante?->sku,
            'estado_exhibicion' => $exhibicion->estado_exhibicion,
            'estado_label' => Inventario::ESTADOS_EXHIBICION[$exhibicion->estado_exhibicion] ?? $exhibicion->estado_exhibicion,
        ];
    }

    private function condicionResumen($exhibiciones): ?array
    {
        if ($exhibiciones->isEmpty()) {
            return null;
        }

        $estados = $exhibiciones->pluck('estado_exhibicion')->filter()->unique()->values();

        if ($estados->count() === 1) {
            $estado = $estados->first();

            return [
                'key' => $estado,
                'label' => Inventario::ESTADOS_EXHIBICION[$estado] ?? $estado,
                'mixto' => false,
                'total' => $exhibiciones->count(),
            ];
        }

        return [
            'key' => 'mixto',
            'label' => 'Mixto',
            'mixto' => true,
            'total' => $exhibiciones->count(),
            'detalles' => $exhibiciones
                ->groupBy('estado_exhibicion')
                ->map(fn($items, $estado) => [
                    'estado' => $estado,
                    'label' => Inventario::ESTADOS_EXHIBICION[$estado] ?? $estado,
                    'total' => $items->count(),
                ])
                ->values(),
        ];
    }

    private function coberturaProducto($exhibiciones): string
    {
        if ($exhibiciones->isEmpty()) {
            return 'Sin exhibicion';
        }

        if ($exhibiciones->count() > 1) {
            return "{$exhibiciones->count()} coberturas activas";
        }

        $exhibicion = $exhibiciones->first();

        if ($exhibicion->tipo_cobertura === 'color') {
            return 'Cubre color: ' . ($exhibicion->atributo?->valor ?? 'Sin color');
        }

        if (! $exhibicion->variante_id) {
            return 'Cubre producto completo';
        }

        $color = $this->atributoColor($exhibicion->variante);
        if ($color?->atributo?->valor) {
            return "Variante de color: {$color->atributo->valor}";
        }

        return 'Cubre variante especifica';
    }

    private function atributoColor($variante)
    {
        if (! $variante) {
            return null;
        }

        return collect($variante->atributos ?? [])
            ->first(fn($attr) => in_array(
                $this->normalizarAtributo($attr->tipoAtributo?->nombre ?? ''),
                Inventario::nombresAtributoColor(),
                true
            ));
    }

    private function normalizarAtributo(string $valor): string
    {
        $valor = mb_strtolower(trim($valor), 'UTF-8');

        return ProductVariantSearch::quitarAcentos($valor);
    }

    private function catalogos(int $empresaId, ?int $marcaId): array
    {
        return [
            'categorias' => DB::table('categorias')
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->whereNull('deleted_at')
                ->select('id', 'nombre')
                ->orderBy('nombre')
                ->get(),
            'marcas' => DB::table('marcas')
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->whereNull('deleted_at')
                ->select('id', 'nombre')
                ->orderBy('nombre')
                ->get(),
            'modelos' => DB::table('modelos')
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->whereNull('deleted_at')
                ->when($marcaId, fn($q, $v) => $q->where('marca_id', $v))
                ->select('id', 'marca_id', 'nombre')
                ->orderBy('nombre')
                ->get(),
        ];
    }
}
