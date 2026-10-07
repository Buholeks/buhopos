<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\ComisionEsquema;
use App\Models\ComisionMovimiento;
use App\Models\ComisionRegla;
use App\Models\ComisionReglaPrecio;
use App\Models\Devolucion;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calcula y registra comisiones de vendedor y cajero.
 *
 * Resolución por renglón (dentro del esquema asignado al usuario):
 *   producto → categoría del producto → categorías padre → general.
 * En cada nivel:
 *   - si alguna regla cubre el precio, se usa (su tabla de precios decide tipo/valor; precio apagado = $0);
 *   - si ninguna lo cubre: sin_comision ⇒ $0, usar_siguiente ⇒ se baja al siguiente nivel.
 * Si no hay ninguna regla, la comisión es $0.
 *
 * Las comisiones se guardan como movimientos (snapshot); cancelaciones y devoluciones
 * generan movimientos negativos. Los reversos de $0 conservan la cantidad devuelta.
 */
class ComisionService
{
    /** @var array<int, Collection> reglas activas por esquema_id */
    private array $reglasCache = [];

    /** @var array<int, int[]> cadena [categoria, padre, abuelo...] por categoria_id */
    private array $categoriasCache = [];

    // ── Registro ──────────────────────────────────────────────────────────────

    /**
     * La comisión forma parte de la transacción de venta/cancelación/devolución.
     * Un error se propaga para impedir confirmar una operación con saldo incompleto.
     */
    public function registrarAtomico(callable $registro): void
    {
        DB::transaction(fn() => $registro($this));
    }

    /**
     * Genera los movimientos de comisión de una venta confirmada.
     * Es idempotente: si la venta ya tiene movimientos de tipo venta, no hace nada.
     */
    public function registrarVenta(Venta $venta): Collection
    {
        if ($venta->estado !== 'confirmada') {
            return collect();
        }

        $yaRegistrada = ComisionMovimiento::where('venta_id', $venta->id)
            ->where('tipo', ComisionMovimiento::TIPO_VENTA)
            ->exists();
        if ($yaRegistrada) {
            return collect();
        }

        $beneficiarios = $this->beneficiariosDeVenta($venta);
        if (empty($beneficiarios)) {
            return collect();
        }

        $detalles = $venta->detalles()
            ->with(['producto' => fn($q) => $q->withTrashed()->select('id', 'categoria_id')])
            ->get();
        $netos = $this->netosPorRenglon($venta, $detalles);
        $fecha = $venta->fecha ?? now();

        $movimientos = collect();
        foreach ($beneficiarios as $beneficiario => [$userId, $esquema]) {
            foreach ($detalles as $detalle) {
                $calculo = $this->calcularRenglon(
                    $esquema,
                    (int) $detalle->producto_id,
                    $detalle->producto?->categoria_id ? (int) $detalle->producto->categoria_id : null,
                    (float) $detalle->precio_venta,
                    $detalle->lista_precio_usada,
                    (float) $detalle->cantidad,
                    $netos[$detalle->id] ?? 0.0,
                    $detalle->precio_costo !== null ? (float) $detalle->precio_costo : null,
                );

                if ($calculo['monto'] == 0.0) {
                    continue;
                }

                $movimientos->push(ComisionMovimiento::create([
                    'empresa_id'       => $venta->empresa_id,
                    'sucursal_id'      => $venta->sucursal_id,
                    'user_id'          => $userId,
                    'beneficiario'     => $beneficiario,
                    'tipo'             => ComisionMovimiento::TIPO_VENTA,
                    'fecha'            => $fecha,
                    'venta_id'         => $venta->id,
                    'venta_detalle_id' => $detalle->id,
                    'esquema_id'       => $esquema->id,
                    'regla_id'         => $calculo['regla_id'],
                    'lista_precio'     => $calculo['lista'],
                    'base'             => $calculo['base'],
                    'regla_tipo'       => $calculo['tipo'],
                    'cantidad'         => $detalle->cantidad,
                    'base_calculo'     => $calculo['base_calculo'],
                    'tasa'             => $calculo['tasa'],
                    'monto'            => $calculo['monto'],
                ]));
            }
        }

        return $movimientos;
    }

    /**
     * Revierte todo lo que quede vigente de una venta (comisión menos devoluciones previas).
     * Idempotente: si ya hay movimientos de cancelación para la venta, no hace nada.
     */
    public function revertirCancelacion(Venta $venta): Collection
    {
        $yaRevertida = ComisionMovimiento::where('venta_id', $venta->id)
            ->where('tipo', ComisionMovimiento::TIPO_CANCELACION)
            ->exists();
        if ($yaRevertida) {
            return collect();
        }

        $vigentes = ComisionMovimiento::where('venta_id', $venta->id)
            ->whereIn('tipo', [ComisionMovimiento::TIPO_VENTA, ComisionMovimiento::TIPO_DEVOLUCION])
            ->get()
            ->groupBy(fn($m) => $m->user_id . '|' . $m->beneficiario . '|' . $m->venta_detalle_id);

        $movimientos = collect();
        foreach ($vigentes as $grupo) {
            $neto = round((float) $grupo->sum('monto'), 2);
            if ($neto == 0.0) {
                continue;
            }

            $original = $grupo->firstWhere('tipo', ComisionMovimiento::TIPO_VENTA) ?? $grupo->first();
            $movimientos->push($this->reverso($original, ComisionMovimiento::TIPO_CANCELACION, -$neto, [
                'cantidad'     => round(-(float) $grupo->sum('cantidad'), 3),
                'base_calculo' => round(-(float) $grupo->sum('base_calculo'), 2),
            ]));
        }

        return $movimientos;
    }

    /**
     * Descuenta la comisión proporcional a lo devuelto de cada renglón.
     * Idempotente por devolución.
     */
    public function registrarDevolucion(Devolucion $devolucion): Collection
    {
        $yaRegistrada = ComisionMovimiento::where('devolucion_id', $devolucion->id)->exists();
        if ($yaRegistrada) {
            return collect();
        }

        $devolucion->loadMissing('detalles.ventaDetalle');

        $movimientos = collect();
        foreach ($devolucion->detalles as $detDev) {
            $ventaDetalle = $detDev->ventaDetalle;
            if (! $ventaDetalle || (float) $ventaDetalle->cantidad <= 0) {
                continue;
            }

            $originales = ComisionMovimiento::where('venta_detalle_id', $ventaDetalle->id)
                ->where('tipo', ComisionMovimiento::TIPO_VENTA)
                ->get();

            foreach ($originales as $original) {
                $previos = ComisionMovimiento::where('venta_detalle_id', $ventaDetalle->id)
                    ->where('user_id', $original->user_id)
                    ->where('beneficiario', $original->beneficiario)
                    ->where('tipo', ComisionMovimiento::TIPO_DEVOLUCION)
                    ->get();
                $cantidadAcumulada = min((float) $original->cantidad,
                    abs((float) $previos->sum('cantidad')) + (float) $detDev->cantidad);
                $fraccion = $cantidadAcumulada / (float) $original->cantidad;
                $monto = round(-round((float) $original->monto * $fraccion, 2) - (float) $previos->sum('monto'), 2);
                $baseRevertida = round(-round((float) $original->base_calculo * $fraccion, 2) - (float) $previos->sum('base_calculo'), 2);
                // Se conserva también el reverso de $0 para contabilizar la cantidad
                // y compensar el redondeo en devoluciones posteriores.

                $movimientos->push($this->reverso($original, ComisionMovimiento::TIPO_DEVOLUCION, $monto, [
                    'devolucion_id' => $devolucion->id,
                    'cantidad'      => -(float) $detDev->cantidad,
                    'base_calculo'  => $baseRevertida,
                ]));
            }
        }

        return $movimientos;
    }

    // ── Cálculo ───────────────────────────────────────────────────────────────

    /**
     * Calcula la comisión de un renglón sin guardar nada (también lo usa "probar regla").
     *
     * @param float      $precioUnitario precio aplicado por unidad (para rangos)
     * @param float      $neto           importe del renglón ya con su parte del descuento global
     * @param float|null $costoUnitario  snapshot de costo, para base = utilidad
     * @return array{monto: float, regla_id: ?int, lista: string, base: ?string, tipo: ?string, tasa: float, base_calculo: float, motivo: string}
     */
    public function calcularRenglon(
        ComisionEsquema $esquema,
        int $productoId,
        ?int $categoriaId,
        float $precioUnitario,
        ?string $listaPrecio,
        float $cantidad,
        float $neto,
        ?float $costoUnitario,
    ): array {
        $lista = $this->normalizarLista($listaPrecio);
        $resultado = [
            'monto' => 0.0, 'regla_id' => null, 'lista' => $lista, 'base' => null,
            'tipo' => null, 'tasa' => 0.0, 'base_calculo' => 0.0, 'motivo' => 'sin_regla',
        ];

        $regla = null;
        foreach ($this->nivelesDeBusqueda($esquema, $productoId, $categoriaId) as $reglasNivel) {
            if ($reglasNivel->isEmpty()) {
                continue;
            }

            $cubren = $reglasNivel->filter(fn(ComisionRegla $r) => $r->cubrePrecio($precioUnitario));
            if ($cubren->isNotEmpty()) {
                // Una regla con rango es más específica que una sin rango.
                $regla = $cubren->sortBy(fn(ComisionRegla $r) => [$r->tieneRango() ? 0 : 1, $r->id])->first();
                break;
            }

            // Ninguna cubre el precio: si alguna dice sin_comision, gana esa (conservador).
            if ($reglasNivel->contains(fn(ComisionRegla $r) => $r->fuera_de_rango === ComisionRegla::FUERA_SIN_COMISION)) {
                $resultado['regla_id'] = $reglasNivel->first()->id;
                $resultado['motivo'] = 'fuera_de_rango';
                return $resultado;
            }
        }

        if (! $regla) {
            return $resultado;
        }

        $resultado['regla_id'] = $regla->id;
        $resultado['base'] = $regla->base;

        [$activo, $tipo, $valor] = $this->tipoYValor($regla, $lista);
        if (! $activo) {
            $resultado['motivo'] = 'precio_apagado';
            return $resultado;
        }

        $baseCalculo = $regla->base === ComisionRegla::BASE_UTILIDAD
            ? max(0, $neto - ($costoUnitario ?? 0) * $cantidad)
            : $neto;

        $monto = match ($tipo) {
            ComisionRegla::TIPO_FIJO_POR_PIEZA => $cantidad * $valor,
            default                            => $baseCalculo * $valor / 100,
        };

        $resultado['tipo'] = $tipo;
        $resultado['tasa'] = $valor;
        $resultado['base_calculo'] = round($baseCalculo, 2);
        $resultado['monto'] = round($monto, 2);
        $resultado['motivo'] = 'aplicada';

        return $resultado;
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0:int, 1:ComisionEsquema}> beneficiario => [user_id, esquema]
     */
    private function beneficiariosDeVenta(Venta $venta): array
    {
        $vendedorId = (int) $venta->vendedor_id;
        $cajeroId = (int) $venta->user_id;
        $empresaId = (int) $venta->empresa_id;

        $ids = array_filter(array_unique([$vendedorId, $cajeroId]));
        $usuarios = User::whereIn('id', $ids)
            ->get(['id', 'empresa_id', 'esquema_vendedor_id', 'esquema_cajero_id'])
            ->keyBy('id');

        $resultado = [];

        $vendedor = $usuarios[$vendedorId] ?? null;
        if ($vendedor && ($esquema = $this->esquemaValido($vendedor->esquema_vendedor_id, $empresaId, ComisionEsquema::BENEFICIARIO_VENDEDOR))) {
            $resultado[ComisionEsquema::BENEFICIARIO_VENDEDOR] = [$vendedor->id, $esquema];
        }

        // Si cajero y vendedor son la misma persona, solo cobra como vendedor.
        $cajero = $usuarios[$cajeroId] ?? null;
        if ($cajero && $cajeroId !== $vendedorId
            && ($esquema = $this->esquemaValido($cajero->esquema_cajero_id, $empresaId, ComisionEsquema::BENEFICIARIO_CAJERO))) {
            $resultado[ComisionEsquema::BENEFICIARIO_CAJERO] = [$cajero->id, $esquema];
        }

        return $resultado;
    }

    private function esquemaValido(?int $esquemaId, int $empresaId, string $beneficiario): ?ComisionEsquema
    {
        if (! $esquemaId) {
            return null;
        }

        return ComisionEsquema::where('id', $esquemaId)
            ->where('empresa_id', $empresaId)
            ->where('beneficiario', $beneficiario)
            ->where('activo', true)
            ->first();
    }

    /**
     * Importe neto por renglón: subtotal menos su parte proporcional del descuento global.
     * @return array<int, float> venta_detalle_id => neto
     */
    private function netosPorRenglon(Venta $venta, Collection $detalles): array
    {
        $sumaSubtotales = (float) $detalles->sum('subtotal');
        $descuentoGlobal = min((float) ($venta->descuento ?? 0), $sumaSubtotales);

        $netos = [];
        foreach ($detalles as $detalle) {
            $subtotal = (float) $detalle->subtotal;
            $parte = $sumaSubtotales > 0 ? $descuentoGlobal * $subtotal / $sumaSubtotales : 0;
            $netos[$detalle->id] = round($subtotal - $parte, 2);
        }

        return $netos;
    }

    /**
     * Niveles en orden de búsqueda: producto, categoría, categorías padre…, general.
     * @return iterable<Collection<int, ComisionRegla>>
     */
    private function nivelesDeBusqueda(ComisionEsquema $esquema, int $productoId, ?int $categoriaId): iterable
    {
        $reglas = $this->reglasDeEsquema($esquema->id);

        yield $reglas->filter(fn($r) => $r->aplica_a === ComisionRegla::APLICA_PRODUCTO && (int) $r->referencia_id === $productoId);

        foreach ($this->cadenaCategorias($categoriaId) as $catId) {
            yield $reglas->filter(fn($r) => $r->aplica_a === ComisionRegla::APLICA_CATEGORIA && (int) $r->referencia_id === $catId);
        }

        yield $reglas->filter(fn($r) => $r->aplica_a === ComisionRegla::APLICA_GENERAL);
    }

    private function reglasDeEsquema(int $esquemaId): Collection
    {
        return $this->reglasCache[$esquemaId] ??= ComisionRegla::with('precios')
            ->where('esquema_id', $esquemaId)
            ->where('activo', true)
            ->orderBy('id')
            ->get();
    }

    /** @return int[] */
    private function cadenaCategorias(?int $categoriaId): array
    {
        if (! $categoriaId) {
            return [];
        }

        if (! isset($this->categoriasCache[$categoriaId])) {
            $cadena = [];
            $actual = $categoriaId;
            // Límite de profundidad para protegerse de ciclos en datos corruptos.
            while ($actual && count($cadena) < 20 && ! in_array($actual, $cadena, true)) {
                $cadena[] = $actual;
                $actual = Categoria::withTrashed()->whereKey($actual)->value('categoria_padre_id');
            }
            $this->categoriasCache[$categoriaId] = $cadena;
        }

        return $this->categoriasCache[$categoriaId];
    }

    /** @return array{0: bool, 1: string, 2: float} [activo, tipo, valor] */
    private function tipoYValor(ComisionRegla $regla, string $lista): array
    {
        $fila = $regla->precios->firstWhere('lista', $lista);

        if ($fila) {
            return [(bool) $fila->activo, $fila->tipo, (float) $fila->valor];
        }

        return [true, $regla->tipo, (float) $regla->valor];
    }

    private function normalizarLista(?string $lista): string
    {
        return in_array($lista, ComisionReglaPrecio::LISTAS, true)
            ? $lista
            : ComisionReglaPrecio::LISTA_MANUAL;
    }

    private function reverso(ComisionMovimiento $original, string $tipo, float $monto, array $extra = []): ComisionMovimiento
    {
        return ComisionMovimiento::create(array_merge([
            'empresa_id'       => $original->empresa_id,
            'sucursal_id'      => $original->sucursal_id,
            'user_id'          => $original->user_id,
            'beneficiario'     => $original->beneficiario,
            'tipo'             => $tipo,
            // Fecha local: el periodo de comisiones se consulta por día en hora de México.
            'fecha'            => now('America/Mexico_City')->toDateString(),
            'venta_id'         => $original->venta_id,
            'venta_detalle_id' => $original->venta_detalle_id,
            'esquema_id'       => $original->esquema_id,
            'regla_id'         => $original->regla_id,
            'lista_precio'     => $original->lista_precio,
            'base'             => $original->base,
            'regla_tipo'       => $original->regla_tipo,
            'tasa'             => $original->tasa,
            'monto'            => $monto,
        ], $extra));
    }
}
