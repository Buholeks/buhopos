<?php

namespace App\Http\Controllers;

use App\Exportaciones\SeriesExportacion;
use App\Exportaciones\SeriesPdfExportacion;
use App\Exportaciones\ServicioExportacion;
use App\Models\Categoria;
use App\Servicios\ConsultaSeriesServicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ConsultaSeriesController extends Controller
{
    private function alcance(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->tienePermiso('reportes.ver'), 403, 'Sin permiso: reportes.ver');
        $empresa = (int) $user->empresa_id;
        $sucursales = DB::table('sucursales')->where('empresa_id', $empresa)
            ->when(! $user->es_super_admin, fn ($q) => $q->where(fn ($s) => $s->where('id', $user->sucursal_id)
                ->orWhereIn('id', DB::table('sucursal_user')->where('user_id', $user->id)->select('sucursal_id'))))
            ->orderBy('nombre')->get(['id', 'nombre']);
        $sucursales = $sucursales->filter(function ($s) use ($user) {
            $rol = $user->es_super_admin ? null : $user->rolEnSucursal((int) $s->id);
            return ! $rol || $rol->permisos->contains('clave', 'reportes.ver');
        })->values();
        // Una respuesta nunca expone costos de una sucursal sin permiso de utilidades.
        $costos = $sucursales->isNotEmpty() && $sucursales->every(function ($s) use ($user) {
            $rol = $user->es_super_admin ? null : $user->rolEnSucursal((int) $s->id);
            return ! $rol || $rol->permisos->contains('clave', 'reportes.utilidades');
        });
        return [$empresa, $sucursales->pluck('id')->map(fn ($id) => (int) $id)->all(), $costos, $sucursales];
    }

    public function catalogos(Request $request)
    {
        [$empresa, , $costos, $sucursales] = $this->alcance($request);
        return response()->json(['sucursales' => $sucursales, 'ver_costos' => $costos,
            'categorias' => Categoria::nombresConRuta($empresa, DB::table('categorias')->where('empresa_id', $empresa)->get(['id', 'nombre']))]);
    }

    public function buscar(Request $request, ConsultaSeriesServicio $servicio)
    {
        [$empresa, $sucursales] = $this->alcance($request);
        if (is_string($request->input('q'))) $request->merge(['q' => trim($request->input('q'))]);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        return response()->json($servicio->buscar($empresa, $sucursales, trim($data['q'])));
    }

    public function show(Request $request, int $id, ConsultaSeriesServicio $servicio)
    {
        [$empresa, $sucursales, $costos] = $this->alcance($request);
        return response()->json($servicio->expediente($empresa, $sucursales, $id, $costos));
    }

    private function filtros(Request $request, array $sucursales, int $empresa): array
    {
        return $request->validate([
            'fecha_desde' => ['required', 'date_format:Y-m-d'],
            'fecha_hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'sucursal_id' => ['nullable', 'integer', Rule::in($sucursales)],
            'categoria_ids' => ['nullable', 'array', 'max:100'],
            'categoria_ids.*' => ['integer', 'distinct', function ($attr, $value, $fail) use ($empresa) {
                if ((int) $value !== 0 && ! DB::table('categorias')->where('empresa_id', $empresa)->where('id', $value)->exists()) $fail('Categoría no válida.');
            }],
            'estado' => ['required', Rule::in(['vigente', 'cancelada', 'devuelta', 'todas'])],
            'producto' => ['nullable', 'string', 'max:100'],
            'proveedor' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
            'orden' => ['nullable', Rule::in(['fecha_venta', 'producto', 'folio_venta', 'importe_venta'])],
            'direccion' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
    }

    public function reporte(Request $request, ConsultaSeriesServicio $servicio)
    {
        [$empresa, $sucursales, $costos] = $this->alcance($request);
        $f = $this->filtros($request, $sucursales, $empresa);
        $base = $servicio->ventas($empresa, $sucursales, $f);
        $totales = (clone $base)->selectRaw('COUNT(*) as operaciones, COUNT(DISTINCT serie_id) as series, COALESCE(SUM(importe_venta), 0) as vendido, COALESCE(SUM(importe_devuelto), 0) as devuelto, COALESCE(SUM(importe_neto), 0) as neto')->first();
        $items = $base->orderBy($f['orden'] ?? 'fecha_venta', $f['direccion'] ?? 'desc')->orderByDesc('id')->paginate($f['per_page'] ?? 20);
        if (! $costos) $items->through(function ($r) { unset($r->costo, $r->costo_compra); return $r; });
        return response()->json(['items' => $items, 'totales' => $totales, 'ver_costos' => $costos]);
    }

    public function exportar(Request $request, ConsultaSeriesServicio $servicio, ServicioExportacion $exportador)
    {
        [$empresa, $sucursales, $costos] = $this->alcance($request);
        $f = $this->filtros($request, $sucursales, $empresa);
        $formato = $request->validate(['formato' => ['nullable', Rule::in(['excel', 'pdf'])]])['formato'] ?? 'excel';
        $columnas = array_keys(SeriesExportacion::COLUMNAS);
        if (! $costos) $columnas = array_values(array_diff($columnas, ['costo', 'costo_compra']));
        $query = $servicio->ventas($empresa, $sucursales, $f)->orderBy($f['orden'] ?? 'fecha_venta', $f['direccion'] ?? 'desc')->orderByDesc('id');
        $exportacion = new SeriesExportacion($query, $columnas);
        $nombre = 'series_'.$f['fecha_desde'].'_'.$f['fecha_hasta'];
        if ($formato === 'excel') return Excel::download($exportacion, $nombre.'.xlsx');

        $filtros = ['Venta desde' => \Carbon\Carbon::parse($f['fecha_desde'])->format('d/m/Y'),
            'Venta hasta' => \Carbon\Carbon::parse($f['fecha_hasta'])->format('d/m/Y'),
            'Estado' => ['vigente' => 'Vendido', 'cancelada' => 'Canceladas', 'devuelta' => 'Con devolución', 'todas' => 'Todas las operaciones'][$f['estado']]];
        $filtros['Sucursal de venta'] = ! empty($f['sucursal_id'])
            ? DB::table('sucursales')->where('empresa_id', $empresa)->where('id', $f['sucursal_id'])->value('nombre')
            : 'Todas las autorizadas';
        if (! empty($f['categoria_ids'])) {
            $categorias = DB::table('categorias')->where('empresa_id', $empresa)->whereIn('id', $f['categoria_ids'])->orderBy('nombre')->pluck('nombre');
            if (in_array(0, $f['categoria_ids'])) $categorias->push('Sin categoría');
            $filtros['Categorías'] = $categorias->implode(', ');
        }
        foreach (['producto' => 'Producto', 'proveedor' => 'Proveedor'] as $campo => $etiqueta) {
            if (! empty($f[$campo])) $filtros[$etiqueta] = $f[$campo];
        }
        return $exportador->exportar(new SeriesPdfExportacion(
            $empresa, ! empty($f['sucursal_id']) ? (int) $f['sucursal_id'] : null, $exportacion, $filtros,
        ), 'pdf', $nombre);
    }
}
