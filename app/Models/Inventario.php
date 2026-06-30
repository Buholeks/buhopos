<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Inventario extends Model
{
    protected $table = 'inventario';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'producto_id',
        'variante_id',
        'stock',
        'stock_minimo',
        'exhibido',
        'variante_exhibida_id',
        'estado_exhibicion',
    ];

    protected $casts = [
        'stock'               => 'decimal:3',
        'stock_minimo'        => 'decimal:3',
        'exhibido'            => 'boolean',
        'variante_exhibida_id'=> 'integer',
    ];

    // ── Constantes de estado ──────────────────────────────────────────────────
    const ESTADOS_EXHIBICION = [
        'perfecto'     => 'Perfecto',
        'caja_abierta' => 'Caja abierta',
        'con_detalles' => 'Con detalles',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function variante()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_id');
    }

    public function varianteExhibida()
    {
        return $this->belongsTo(ProductoVariante::class, 'variante_exhibida_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeExhibidos($query)
    {
        return $query->where('exhibido', true);
    }

    public function scopeSinExhibicion($query)
    {
        /*
         * Reglas:
         *
         * 1. Sin variantes (variante_id IS NULL):
         *    Excluir si ese producto ya está exhibido.
         *
         * 2. Con variantes (variante_id IS NOT NULL):
         *    a) Exhibido CON variante_exhibida_id específica
         *       → solo esa variante desaparece, las demás siguen aquí.
         *    b) Exhibido SIN variante_exhibida_id (general/sin variante específica)
         *       → todas las variantes desaparecen.
         */
        return $query->where('exhibido', false)
                     ->where('stock', '>', 0)
                     ->where(function ($q) {

                         // ── Caso 1: producto sin variante ──────────────────
                         $q->where(function ($q2) {
                             $q2->whereNull('variante_id')
                                ->whereNotExists(function ($sub) {
                                    $sub->from('inventario as inv2')
                                        ->whereColumn('inv2.producto_id', 'inventario.producto_id')
                                        ->whereColumn('inv2.empresa_id',  'inventario.empresa_id')
                                        ->whereColumn('inv2.sucursal_id', 'inventario.sucursal_id')
                                        ->where('inv2.exhibido', true);
                                });
                         })

                         // ── Caso 2: producto con variante ──────────────────
                         ->orWhere(function ($q2) {
                             $q2->whereNotNull('variante_id')

                                // Caso 2a: no es la variante específica exhibida
                                ->whereNotExists(function ($sub) {
                                    $sub->from('inventario as inv2')
                                        ->whereColumn('inv2.producto_id',          'inventario.producto_id')
                                        ->whereColumn('inv2.empresa_id',           'inventario.empresa_id')
                                        ->whereColumn('inv2.sucursal_id',          'inventario.sucursal_id')
                                        ->where('inv2.exhibido', true)
                                        ->whereColumn('inv2.variante_exhibida_id', 'inventario.variante_id');
                                })

                                // Caso 2b: no hay exhibición general (sin variante_exhibida_id)
                                ->whereNotExists(function ($sub) {
                                    $sub->from('inventario as inv3')
                                        ->whereColumn('inv3.producto_id', 'inventario.producto_id')
                                        ->whereColumn('inv3.empresa_id',  'inventario.empresa_id')
                                        ->whereColumn('inv3.sucursal_id', 'inventario.sucursal_id')
                                        ->where('inv3.exhibido', true)
                                        ->whereNull('inv3.variante_exhibida_id');
                                })
                                ->where(function ($variantScope) {
                                    $variantScope->where(function ($noColor) {
                                        $noColor->whereNotExists(function ($sub) {
                                            $sub->from('producto_variantes as pv_color')
                                                ->join('variante_atributos as va_color', 'va_color.variante_id', '=', 'pv_color.id')
                                                ->join('tipo_atributos as ta_color', 'ta_color.id', '=', 'va_color.tipo_atributo_id')
                                                ->whereColumn('pv_color.producto_id', 'inventario.producto_id')
                                                ->whereIn(DB::raw('LOWER(ta_color.nombre)'), self::nombresAtributoColor());
                                        })
                                        ->whereNotExists(function ($sub) {
                                            $sub->from('inventario as inv4')
                                                ->whereColumn('inv4.producto_id', 'inventario.producto_id')
                                                ->whereColumn('inv4.empresa_id', 'inventario.empresa_id')
                                                ->whereColumn('inv4.sucursal_id', 'inventario.sucursal_id')
                                                ->where('inv4.exhibido', true);
                                        });
                                    })
                                    ->orWhere(function ($withColor) {
                                        $withColor->whereExists(function ($sub) {
                                            $sub->from('producto_variantes as pv_color')
                                                ->join('variante_atributos as va_color', 'va_color.variante_id', '=', 'pv_color.id')
                                                ->join('tipo_atributos as ta_color', 'ta_color.id', '=', 'va_color.tipo_atributo_id')
                                                ->whereColumn('pv_color.producto_id', 'inventario.producto_id')
                                                ->whereIn(DB::raw('LOWER(ta_color.nombre)'), self::nombresAtributoColor());
                                        })
                                        ->whereNotExists(function ($sub) {
                                            $sub->from('inventario as inv5')
                                                ->join('producto_variantes as pv_exhibida', 'pv_exhibida.id', '=', 'inv5.variante_exhibida_id')
                                                ->join('variante_atributos as va_exhibida', 'va_exhibida.variante_id', '=', 'pv_exhibida.id')
                                                ->join('tipo_atributos as ta_exhibida', 'ta_exhibida.id', '=', 'va_exhibida.tipo_atributo_id')
                                                ->join('variante_atributos as va_actual', function ($join) {
                                                    $join->on('va_actual.variante_id', '=', 'inventario.variante_id')
                                                        ->on('va_actual.tipo_atributo_id', '=', 'va_exhibida.tipo_atributo_id')
                                                        ->on('va_actual.atributo_id', '=', 'va_exhibida.atributo_id');
                                                })
                                                ->whereColumn('inv5.producto_id', 'inventario.producto_id')
                                                ->whereColumn('inv5.empresa_id', 'inventario.empresa_id')
                                                ->whereColumn('inv5.sucursal_id', 'inventario.sucursal_id')
                                                ->where('inv5.exhibido', true)
                                                ->whereIn(DB::raw('LOWER(ta_exhibida.nombre)'), self::nombresAtributoColor());
                                        });
                                    });
                                });
                         });
                     });
    }

    public function scopeSinStock($query)
    {
        return $query->where('stock', '<=', 0);
    }

    public function scopeDeSucursal($query, int $empresaId, int $sucursalId)
    {
        return $query->where('empresa_id', $empresaId)
                     ->where('sucursal_id', $sucursalId);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Unidades disponibles en bodega.
     * El exhibido también es vendible, así que bodega = stock - 1 solo
     * sirve como referencia visual (cuántas están físicamente en bodega).
     */
    public function getStockBodegaAttribute(): float
    {
        if ($this->exhibido) {
            return max(0, (float) $this->stock - 1);
        }
        return (float) $this->stock;
    }

    public function getEstadoExhibicionLabelAttribute(): ?string
    {
        if (! $this->estado_exhibicion) return null;
        return self::ESTADOS_EXHIBICION[$this->estado_exhibicion] ?? $this->estado_exhibicion;
    }

    // ── Acciones ──────────────────────────────────────────────────────────────

    /**
     * Marcar como exhibido.
     *
     * @param  string        $estado          'perfecto' | 'caja_abierta' | 'con_detalles'
     * @param  int|null      $varianteExhibidaId  Variante que se pone en piso (opcional)
     *
     * Para productos SIN variantes:  marcarExhibido('perfecto')
     * Para productos CON variantes:  marcarExhibido('perfecto', $varianteId)
     *                                o sin variante específica: marcarExhibido('perfecto', null)
     */
    public function marcarExhibido(string $estado, ?int $varianteExhibidaId = null): void
    {
        if ($this->stock <= 0) {
            throw new \RuntimeException('Sin stock disponible para exhibir.');
        }

        if (! array_key_exists($estado, self::ESTADOS_EXHIBICION)) {
            throw new \RuntimeException('Estado de exhibición no válido.');
        }

        // Si se indica una variante, validar que tenga stock propio
        if ($varianteExhibidaId) {
            $stockVariante = self::where('empresa_id', $this->empresa_id)
                ->where('sucursal_id', $this->sucursal_id)
                ->where('producto_id', $this->producto_id)
                ->where('variante_id', $varianteExhibidaId)
                ->value('stock');

            if (! $stockVariante || $stockVariante <= 0) {
                throw new \RuntimeException('La variante seleccionada no tiene stock disponible.');
            }
        }

        $this->update([
            'exhibido'             => true,
            'variante_exhibida_id' => $varianteExhibidaId,
            'estado_exhibicion'    => $estado,
        ]);
    }

    /**
     * Quitar de exhibición. Limpia todos los campos relacionados.
     */
    public function quitarExhibicion(): void
    {
        $this->update([
            'exhibido'             => false,
            'variante_exhibida_id' => null,
            'estado_exhibicion'    => null,
        ]);
    }

    /**
     * Llamar al registrar una venta.
     * Descuenta stock y, si era el exhibido, limpia la exhibición.
     *
     * @param  float  $cantidad
     * @param  bool   $eraExhibido  true si el artículo vendido era el de exhibición
     */
    public function descontarVenta(float $cantidad, bool $eraExhibido = false): void
    {
        $this->decrement('stock', $cantidad);

        // Si se vendió el exhibido, o ya no queda stock → limpiar exhibición
        if ($eraExhibido || ($this->exhibido && $this->fresh()->stock <= 0)) {
            $this->quitarExhibicion();
        }
    }

    public static function nombresAtributoColor(): array
    {
        return ['color', 'colores', 'colors', 'colour', 'colours'];
    }
}
