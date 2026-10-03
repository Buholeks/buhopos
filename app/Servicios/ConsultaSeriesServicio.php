<?php

namespace App\Servicios;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ConsultaSeriesServicio
{
    public function series(int $empresa, array $sucursales): Builder
    {
        return DB::table('series as s')
            ->join('productos as p', 'p.id', '=', 's.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('producto_variantes as pv', 'pv.id', '=', 's.variante_id')
            ->leftJoin('sucursales as su', 'su.id', '=', 's.sucursal_id')
            ->where('s.empresa_id', $empresa)->where('p.empresa_id', $empresa)
            ->where(function ($q) use ($empresa, $sucursales) {
                $q->whereIn('s.sucursal_id', $sucursales)
                    ->orWhereExists(fn ($v) => $v->selectRaw('1')->from('venta_detalles as vd')
                        ->join('ventas as v', 'v.id', '=', 'vd.venta_id')->whereColumn('vd.serie_id', 's.id')
                        ->where('v.empresa_id', $empresa)->whereIn('v.sucursal_id', $sucursales))
                    ->orWhereExists(fn ($t) => $t->selectRaw('1')->from('traspaso_detalles as td')
                        ->join('traspasos as tr', 'tr.id', '=', 'td.traspaso_id')->whereColumn('td.serie_id', 's.id')
                        ->where('tr.empresa_id', $empresa)->where(fn ($b) => $b->whereIn('tr.origen_sucursal_id', $sucursales)->orWhereIn('tr.destino_sucursal_id', $sucursales)));
            });
    }

    public function buscar(int $empresa, array $sucursales, string $texto)
    {
        // Los comodines introducidos por el usuario se interpretan como texto.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto).'%';
        return $this->series($empresa, $sucursales)
            ->where(fn ($q) => $q->whereRaw("s.serie LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("s.imei LIKE ? ESCAPE '!'", [$like])->orWhereRaw("s.imei2 LIKE ? ESCAPE '!'", [$like]))
            ->select('s.id', 's.serie', 's.imei', 's.imei2', 's.estado', 's.sucursal_id', 'p.nombre as producto', 'pv.sku', 'c.nombre as categoria', 'su.nombre as sucursal')
            ->orderByRaw('CASE WHEN s.serie = ? OR s.imei = ? OR s.imei2 = ? THEN 0 ELSE 1 END', [$texto, $texto, $texto])
            ->orderBy('s.id')->paginate(20)
            ->through(function ($r) use ($sucursales) {
                if (! in_array((int) $r->sucursal_id, $sucursales, true)) $r->sucursal = 'Otra sucursal';
                return $r;
            });
    }

    public function ventas(int $empresa, array $sucursales, array $filtros = []): Builder
    {
        $devoluciones = DB::table('devolucion_detalles as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->where('d.empresa_id', $empresa)->whereIn('d.sucursal_id', $sucursales)->where('d.estado', 'confirmada')
            ->groupBy('dd.venta_detalle_id')->selectRaw('dd.venta_detalle_id, SUM(dd.importe) as devuelto, SUM(dd.cantidad) as cantidad_devuelta');
        $base = DB::table('venta_detalles as vd')->join('ventas as v', 'v.id', '=', 'vd.venta_id')
            ->join('series as s', 's.id', '=', 'vd.serie_id')->join('productos as p', 'p.id', '=', 's.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->leftJoin('producto_variantes as pv', 'pv.id', '=', 's.variante_id')
            ->leftJoin('compras as co', function ($j) use ($empresa, $sucursales) {
                $j->on('co.id', '=', 's.compra_id')->where('co.empresa_id', $empresa)->whereIn('co.sucursal_id', $sucursales);
            })
            ->leftJoin('proveedores as pr', 'pr.id', '=', 'co.proveedor_id')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'v.cliente_id')
            ->leftJoin('sucursales as su', 'su.id', '=', 'v.sucursal_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.vendedor_id')
            ->leftJoinSub($devoluciones, 'dev', 'dev.venta_detalle_id', '=', 'vd.id')
            ->where('v.empresa_id', $empresa)->where('s.empresa_id', $empresa)->where('p.empresa_id', $empresa)
            ->whereIn('v.sucursal_id', $sucursales);
        if (! empty($filtros['fecha_desde'])) $base->where('v.fecha', '>=', $filtros['fecha_desde']);
        if (! empty($filtros['fecha_hasta'])) $base->where('v.fecha', '<', \Carbon\Carbon::parse($filtros['fecha_hasta'])->addDay()->toDateString());
        if (! empty($filtros['sucursal_id'])) $base->where('v.sucursal_id', $filtros['sucursal_id']);
        if (! empty($filtros['categoria_ids'])) {
            $ids = $filtros['categoria_ids'];
            $base->where(function ($q) use ($ids) {
                $q->whereIn('p.categoria_id', array_filter($ids));
                if (in_array(0, $ids)) $q->orWhereNull('p.categoria_id');
            });
        }
        foreach (['producto' => 'p.nombre', 'proveedor' => 'pr.nombre_comercial'] as $f => $col) {
            if (! empty($filtros[$f])) $base->where($col, 'like', '%'.$filtros[$f].'%');
        }
        // El descuento global se reparte proporcionalmente al subtotal de la partida.
        $importe = 'ROUND(vd.subtotal * CASE WHEN v.subtotal > 0 THEN (v.subtotal - COALESCE(v.descuento, 0)) * 1.0 / v.subtotal ELSE 0 END, 2)';
        $estado = "CASE WHEN v.estado = 'cancelada' THEN 'cancelada' WHEN COALESCE(dev.cantidad_devuelta, 0) > 0 THEN 'devuelta' ELSE 'vigente' END";
        $base->select('vd.id', 's.id as serie_id', 's.serie', 's.imei', 's.imei2', 'p.nombre as producto', 'pv.sku', 'c.nombre as categoria',
            'co.id as compra_id', 'co.fecha as fecha_compra', 'co.folio as folio_compra', 'pr.nombre_comercial as proveedor',
            'v.id as venta_id', 'v.fecha as fecha_venta', 'v.folio as folio_venta', 'v.sucursal_id', 'su.nombre as sucursal', 'cl.nombre as cliente', 'u.name as vendedor',
            'vd.precio_costo as costo', 'v.cancelado_en', 'v.motivo_cancelacion', 'v.cancelado_por', 'v.user_id', 'v.deleted_at', 'vd.cantidad')
            ->selectRaw('CASE WHEN co.id IS NOT NULL THEN s.precio_costo ELSE NULL END as costo_compra')
            ->selectRaw("$importe as importe_venta, COALESCE(dev.devuelto, 0) as importe_devuelto, $estado as estado_operacion")
            ->selectRaw("CASE WHEN v.estado = 'cancelada' THEN 0 ELSE $importe - COALESCE(dev.devuelto, 0) END as importe_neto");
        // Una fila por partida y serie, incluso si la serie ya volvió a venderse.
        $query = DB::query()->fromSub($base, 'operaciones');
        if (($filtros['estado'] ?? 'todas') !== 'todas') $query->where('estado_operacion', $filtros['estado']);
        return $query;
    }

    public function expediente(int $empresa, array $sucursales, int $id, bool $costos): array
    {
        $s = $this->series($empresa, $sucursales)->where('s.id', $id)
            ->select('s.*', 'p.nombre as producto', 'p.codigo', 'pv.sku', 'c.nombre as categoria', 'su.nombre as sucursal')->first();
        abort_unless($s, 404, 'Serie no encontrada.');
        $compra = DB::table('compras as co')->leftJoin('proveedores as pr', 'pr.id', '=', 'co.proveedor_id')
            ->leftJoin('users as u', 'u.id', '=', 'co.user_id')->where('co.empresa_id', $empresa)->whereIn('co.sucursal_id', $sucursales)
            ->where('co.id', $s->compra_id)->select('co.id', 'co.folio', 'co.fecha', 'co.estado', 'co.sucursal_id', 'co.notas', 'u.name as usuario', 'pr.nombre_comercial as proveedor')->first();
        $ventas = $this->ventas($empresa, $sucursales)->where('serie_id', $id)->orderByDesc('fecha_venta')->orderByDesc('id')->get();
        $movimientos = DB::table('kardex_movimientos as k')->leftJoin('users as u', 'u.id', '=', 'k.user_id')
            ->leftJoin('sucursales as su', 'su.id', '=', 'k.sucursal_id')
            ->where('k.empresa_id', $empresa)->whereIn('k.sucursal_id', $sucursales)
            ->where(function ($q) use ($id, $s) {
                $q->where('k.serie_id', $id)
                    ->orWhere(fn ($a) => $a->where('k.referencia_tipo', 'inventario_ajuste_rapido')
                        ->where(fn ($j) => $j->whereJsonContains('k.metadata->serie_ids', $id)->orWhereJsonContains('k.metadata->serie_ids', (string) $id)))
                    ->when($s->compra_id, fn ($a) => $a->orWhere(fn ($c) => $c->where('k.referencia_tipo', 'compra')->where('k.referencia_id', $s->compra_id)->where('k.tipo', 'cancelacion_compra')))
                    ->orWhere(fn ($d) => $d->where('k.referencia_tipo', 'devolucion_proveedor')->whereExists(fn ($x) => $x->selectRaw('1')
                        ->from('devolucion_proveedor_series as dps')->join('devolucion_proveedor_detalles as dpd', 'dpd.id', '=', 'dps.devolucion_proveedor_detalle_id')
                        ->where('dps.serie_id', $id)->whereColumn('dpd.devolucion_proveedor_id', 'k.referencia_id')->whereColumn('dpd.id', 'k.referencia_detalle_id')));
            })
            ->select('k.id', 'k.tipo', 'k.fecha', 'k.folio', 'k.referencia_tipo', 'k.referencia_id', 'k.referencia_detalle_id', 'k.motivo', 'k.notas', 'su.nombre as sucursal', 'u.name as usuario')->get();
        $eventos = $movimientos->unique(fn ($m) => $m->tipo === 'cancelacion_compra' ? $m->tipo.':'.$m->referencia_tipo.':'.$m->referencia_id : 'k:'.$m->id)
            ->map(fn ($m) => (array) $m + ['fuente' => 'Kardex', 'precision' => 'fecha_hora']);
        $agregar = function (array $e) use (&$eventos) {
            $existe = $eventos->contains(fn ($m) => $m['tipo'] === $e['tipo'] && $m['referencia_tipo'] === $e['referencia_tipo'] && (int) $m['referencia_id'] === (int) $e['referencia_id']);
            if (! $existe) $eventos->push($e + ['fuente' => 'Documento', 'precision' => 'fecha', 'motivo' => null, 'notas' => null, 'usuario' => null, 'sucursal' => null]);
        };
        if ($compra) $agregar(['tipo' => 'compra', 'fecha' => $compra->fecha, 'folio' => $compra->folio, 'referencia_tipo' => 'compra', 'referencia_id' => $compra->id, 'usuario' => $compra->usuario, 'notas' => $compra->notas]);
        foreach ($ventas as $v) {
            $agregar(['tipo' => 'venta', 'fecha' => $v->fecha_venta, 'folio' => $v->folio_venta, 'referencia_tipo' => 'venta', 'referencia_id' => $v->venta_id, 'sucursal' => $v->sucursal, 'usuario' => $v->vendedor]);
            if ($v->estado_operacion === 'cancelada') $agregar(['tipo' => 'cancelacion_venta', 'fecha' => $v->cancelado_en ? $this->fechaLocal($v->cancelado_en) : null, 'precision' => 'fecha_hora', 'folio' => $v->folio_venta, 'referencia_tipo' => 'venta', 'referencia_id' => $v->venta_id, 'motivo' => $v->motivo_cancelacion, 'sucursal' => $v->sucursal]);
        }
        $traspasos = DB::table('traspaso_detalles as td')->join('traspasos as t', 't.id', '=', 'td.traspaso_id')
            ->leftJoin('sucursales as so', 'so.id', '=', 't.origen_sucursal_id')->leftJoin('sucursales as sd', 'sd.id', '=', 't.destino_sucursal_id')
            ->where('t.empresa_id', $empresa)->where('td.serie_id', $id)
            ->where(fn ($q) => $q->whereIn('t.origen_sucursal_id', $sucursales)->orWhereIn('t.destino_sucursal_id', $sucursales))
            ->select('t.*', 'td.estado as detalle_estado', 'so.nombre as origen', 'sd.nombre as destino')->get();
        foreach ($traspasos as $t) {
            foreach (['traspaso_salida' => 'created_at', 'traspaso_entrada' => 'recibido_at', 'cancelacion_traspaso' => 'cancelado_at', 'rechazo_traspaso' => 'rechazado_at'] as $tipo => $campo) {
                if (! $t->$campo) continue;
                if ($tipo === 'traspaso_entrada' && $t->detalle_estado !== 'recibido') continue;
                $agregar(['tipo' => $tipo, 'fecha' => $this->fechaLocal($t->$campo), 'precision' => 'fecha_hora', 'folio' => $t->folio, 'referencia_tipo' => 'traspaso', 'referencia_id' => $t->id, 'sucursal' => $t->origen.' → '.$t->destino, 'motivo' => $t->motivo_cancelacion ?: $t->motivo_rechazo]);
            }
        }
        $devoluciones = DB::table('devolucion_detalles as dd')->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->where('d.empresa_id', $empresa)->whereIn('d.sucursal_id', $sucursales)->where('dd.serie_id', $id)->select('d.*')->distinct()->get();
        foreach ($devoluciones as $d) $agregar(['tipo' => 'devolucion_cliente', 'fecha' => $this->fechaLocal($d->fecha), 'precision' => 'fecha_hora', 'folio' => $d->folio, 'referencia_tipo' => 'devolucion_cliente', 'referencia_id' => $d->id, 'motivo' => $d->motivo, 'notas' => 'Estado del documento: '.$d->estado]);
        $proveedor = DB::table('devolucion_proveedor_series as ds')->join('devolucion_proveedor_detalles as dd', 'dd.id', '=', 'ds.devolucion_proveedor_detalle_id')
            ->join('devoluciones_proveedor as d', 'd.id', '=', 'dd.devolucion_proveedor_id')
            ->where('d.empresa_id', $empresa)->whereIn('d.sucursal_id', $sucursales)->where('ds.serie_id', $id)->select('d.*')->distinct()->get();
        foreach ($proveedor as $d) {
            if (! $eventos->contains(fn ($e) => $e['referencia_tipo'] === 'devolucion_proveedor' && (int) $e['referencia_id'] === (int) $d->id && in_array($e['tipo'], ['devolucion_proveedor', 'cancelacion_compra']))) {
                $agregar(['tipo' => 'devolucion_proveedor', 'fecha' => $d->fecha, 'folio' => $d->referencia ?: 'DEV-'.$d->id, 'referencia_tipo' => 'devolucion_proveedor', 'referencia_id' => $d->id, 'motivo' => $d->motivo, 'notas' => 'Estado del documento: '.$d->estado]);
            }
        }
        $avisos = ['El historial muestra los documentos y movimientos conservados en las sucursales autorizadas. Los eventos sin registro histórico no se pueden reconstruir.'];
        if ($compra?->estado === 'cancelada') {
            $avisos[] = 'La compra de origen está cancelada. Si no existe un movimiento de cancelación, su fecha exacta no está disponible.';
            if (! $eventos->contains('tipo', 'cancelacion_compra')) $agregar(['tipo' => 'cancelacion_compra', 'fecha' => null, 'folio' => $compra->folio, 'referencia_tipo' => 'compra', 'referencia_id' => $compra->id]);
        }
        if (! $compra) $avisos[] = 'No hay compra de origen disponible dentro del alcance autorizado.';
        if (! in_array((int) $s->sucursal_id, $sucursales, true)) { $s->sucursal = 'Otra sucursal'; $s->notas = null; }
        // Nunca enviar el precio de lista histórico como precio real de venta.
        unset($s->precio_venta);
        if (! $costos || ! $compra) unset($s->precio_costo);
        foreach ($ventas as $v) if (! $costos) unset($v->costo, $v->costo_compra);
        $documentos = $traspasos->map(fn ($t) => ['tipo' => 'traspaso', 'id' => $t->id, 'folio' => $t->folio, 'campos' => ['Origen' => $t->origen, 'Destino' => $t->destino, 'Estado del documento' => $t->estado, 'Estado de esta unidad' => $t->detalle_estado, 'Notas' => $t->notas, 'Motivo de cancelación' => $t->motivo_cancelacion, 'Motivo de rechazo' => $t->motivo_rechazo]])
            ->concat($devoluciones->map(fn ($d) => ['tipo' => 'devolucion_cliente', 'id' => $d->id, 'folio' => $d->folio, 'campos' => ['Fecha' => $this->fechaLocal($d->fecha), 'Estado' => $d->estado, 'Motivo' => $d->motivo, 'Regresa a inventario' => $d->regresa_inventario ? 'Sí' : 'No']]))
            ->concat($proveedor->map(fn ($d) => ['tipo' => 'devolucion_proveedor', 'id' => $d->id, 'folio' => $d->referencia, 'campos' => ['Fecha' => $d->fecha, 'Estado' => $d->estado, 'Motivo' => $d->motivo]]))->values();
        return ['serie' => $s, 'compra' => $compra, 'ventas' => $ventas, 'documentos' => $documentos, 'en_transito' => $traspasos->contains(fn ($t) => $t->estado === 'pendiente' && $t->detalle_estado === 'pendiente'),
            'eventos' => $eventos->sortBy(fn ($e) => ($e['fecha'] ?? '9999').sprintf('%012d', $e['id'] ?? 0))->values(), 'avisos' => $avisos, 'ver_costos' => $costos];
    }

    private function fechaLocal(string $fecha): string
    {
        return \Carbon\Carbon::parse($fecha, config('app.timezone'))->timezone('America/Mexico_City')->format('Y-m-d H:i:s');
    }
}
