<?php

namespace App\Http\Controllers;

use App\Models\TipoAtributo;
use App\Support\PublicImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogoPreciosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'buscar' => ['nullable', 'string', 'max:120'],
            'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'modelo_id' => ['nullable', 'integer', 'exists:modelos,id'],
            'atributo_ids' => ['nullable', 'array'],
            'atributo_ids.*' => ['integer', 'exists:atributos,id'],
            'con_stock' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:8', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $empresaId = (int) $user->empresa_id;
        $sucursalId = (int) $user->sucursal_id;
        $conStock = $request->boolean('con_stock', true);
        $atributoIds = collect($data['atributo_ids'] ?? [])
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $query = DB::table('productos as p')
            ->leftJoin('producto_variantes as v', function ($join) {
                $join->on('v.producto_id', '=', 'p.id')
                    ->whereNull('v.deleted_at')
                    ->where('v.activo', true);
            })
            ->leftJoin('inventario as i', function ($join) use ($empresaId, $sucursalId) {
                $join->on('i.producto_id', '=', 'p.id')
                    ->where('i.empresa_id', $empresaId)
                    ->where('i.sucursal_id', $sucursalId)
                    ->where(function ($q) {
                        $q->whereColumn('i.variante_id', 'v.id')
                            ->orWhere(function ($q2) {
                                $q2->whereNull('v.id')->whereNull('i.variante_id');
                            });
                    });
            })
            ->leftJoin('marcas as ma', 'ma.id', '=', 'p.marca_id')
            ->leftJoin('modelos as mo', 'mo.id', '=', 'p.modelo_id')
            ->where('p.empresa_id', $empresaId)
            ->whereNull('p.deleted_at')
            ->where('p.activo', true)
            ->when($conStock, fn($q) => $q->whereRaw('COALESCE(i.stock, 0) > 0'))
            ->when($data['marca_id'] ?? null, fn($q, $v) => $q->where('p.marca_id', $v))
            ->when($data['modelo_id'] ?? null, fn($q, $v) => $q->where('p.modelo_id', $v));

        if (! empty($data['buscar'])) {
            $buscar = trim((string) $data['buscar']);
            $query->where(function ($q) use ($buscar) {
                $q->where('p.nombre', 'like', "%{$buscar}%")
                    ->orWhere('p.codigo', 'like', "%{$buscar}%")
                    ->orWhere('v.sku', 'like', "%{$buscar}%")
                    ->orWhere('v.codigo_barras', 'like', "%{$buscar}%");
            });
        }

        foreach ($atributoIds as $atributoId) {
            $query->whereExists(function ($sub) use ($atributoId) {
                $sub->from('variante_atributos as va')
                    ->whereColumn('va.variante_id', 'v.id')
                    ->where('va.atributo_id', $atributoId);
            });
        }

        $catalogo = $query
            ->select([
                'p.id as producto_id',
                'p.nombre',
                'p.codigo',
                'p.imagen as producto_imagen',
                'p.precio_venta as producto_precio_venta',
                'p.precio1 as producto_precio1',
                'p.precio2 as producto_precio2',
                'p.precio3 as producto_precio3',
                'p.precio4 as producto_precio4',
                'p.precio5 as producto_precio5',
                'v.id as variante_id',
                'v.sku',
                'v.codigo_barras',
                'v.imagen as variante_imagen',
                'v.precio_venta',
                'v.precio1',
                'v.precio2',
                'v.precio3',
                'v.precio4',
                'v.precio5',
                'v.precio_oferta',
                'v.oferta_activa',
                'v.oferta_hasta',
                DB::raw('COALESCE(i.stock, 0) as stock'),
                'ma.nombre as marca',
                'mo.nombre as modelo',
            ])
            ->orderBy('p.nombre')
            ->orderBy('v.id')
            ->paginate($data['per_page'] ?? 24);

        $varianteIds = $catalogo->getCollection()
            ->pluck('variante_id')
            ->filter()
            ->values();

        $atributosPorVariante = DB::table('variante_atributos as va')
            ->join('tipo_atributos as ta', 'ta.id', '=', 'va.tipo_atributo_id')
            ->join('atributos as a', 'a.id', '=', 'va.atributo_id')
            ->whereIn('va.variante_id', $varianteIds)
            ->select('va.variante_id', 'ta.nombre as tipo', 'a.valor')
            ->orderBy('ta.nombre')
            ->get()
            ->groupBy('variante_id');

        $catalogo->getCollection()->transform(function ($row) use ($atributosPorVariante) {
            $precios = $this->mapPrecios($row);
            $atributos = ($atributosPorVariante[$row->variante_id] ?? collect())
                ->map(fn($a) => ['tipo' => $a->tipo, 'valor' => $a->valor])
                ->values();

            return [
                'producto_id' => $row->producto_id,
                'variante_id' => $row->variante_id,
                'nombre' => $row->nombre,
                'codigo' => $row->codigo,
                'sku' => $row->sku,
                'codigo_barras' => $row->codigo_barras,
                'marca' => $row->marca,
                'modelo' => $row->modelo,
                'stock' => (float) $row->stock,
                'imagen_url' => $this->imagenUrl($row),
                'atributos' => $atributos,
                'variante' => $atributos->pluck('valor')->join(' / '),
                'precios' => $precios,
                'precio_venta' => (float) $row->producto_precio_venta,
                'precio_minimo' => collect($precios)->pluck('valor')->filter(fn($v) => $v > 0)->min(),
            ];
        });

        return response()->json([
            'catalogo' => $catalogo,
            'filtros' => $this->filtros($empresaId, $data['marca_id'] ?? null),
        ]);
    }

    private function filtros(int $empresaId, ?int $marcaId): array
    {
        return [
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
            'atributos' => TipoAtributo::deEmpresa($empresaId)
                ->activos()
                ->with(['atributos' => fn($q) => $q->activos()->orderBy('valor')])
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
        ];
    }

    private function mapPrecios(object $row): array
    {
        $precio = fn(string $campo) => (float) ($row->{$campo} ?? $row->{"producto_{$campo}"} ?? 0);

        $precios = [
            ['key' => 'precio_venta', 'label' => 'Lista', 'valor' => $precio('precio_venta')],
            ['key' => 'precio1', 'label' => 'Precio 1', 'valor' => $precio('precio1')],
            ['key' => 'precio2', 'label' => 'Precio 2', 'valor' => $precio('precio2')],
            ['key' => 'precio3', 'label' => 'Precio 3', 'valor' => $precio('precio3')],
            ['key' => 'precio4', 'label' => 'Precio 4', 'valor' => $precio('precio4')],
            ['key' => 'precio5', 'label' => 'Precio 5', 'valor' => $precio('precio5')],
        ];

        $ofertaVigente = $row->oferta_activa
            && (float) ($row->precio_oferta ?? 0) > 0
            && (empty($row->oferta_hasta) || substr((string) $row->oferta_hasta, 0, 10) >= now()->toDateString());

        if ($ofertaVigente) {
            array_unshift($precios, [
                'key' => 'precio_oferta',
                'label' => 'Oferta',
                'valor' => (float) $row->precio_oferta,
            ]);
        }

        return collect($precios)
            ->filter(fn($p) => (float) $p['valor'] > 0)
            ->values()
            ->all();
    }

    private function imagenUrl(object $row): ?string
    {
        $imagen = $row->variante_imagen ?: $row->producto_imagen;

        return PublicImageStorage::url($imagen);
    }
}
