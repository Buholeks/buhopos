<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class CorteCaja extends Model
{
    protected $table = 'cortes_caja';

    protected $fillable = [
        'empresa_id',
        'sucursal_id',
        'user_id',
        'terminal',
        'estado',
        'fecha_apertura',
        'fecha_cierre',
        'fondo_inicial_efectivo',

        'ventas_efectivo',
        'ventas_tarjeta',
        'ventas_transferencia',
        'ventas_credito',
        'num_ventas',

        // OJO: tus columnas reales
        'movs_efectivo',
        'movs_tarjeta',
        'movs_transferencia',

        'esperado_efectivo',
        'esperado_tarjeta',
        'esperado_transferencia',
        'contado_efectivo',
        'contado_tarjeta',
        'contado_transferencia',

        // OJO: tus columnas reales
        'dif_efectivo',
        'dif_tarjeta',
        'dif_transferencia',

        'notas_apertura',
        'notas_cierre',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre'   => 'datetime',

        'fondo_inicial_efectivo' => 'decimal:2',

        'ventas_efectivo'      => 'decimal:2',
        'ventas_tarjeta'       => 'decimal:2',
        'ventas_transferencia' => 'decimal:2',
        'ventas_credito'       => 'decimal:2',

        // OJO: tus columnas reales
        'movs_efectivo'      => 'decimal:2',
        'movs_tarjeta'       => 'decimal:2',
        'movs_transferencia' => 'decimal:2',

        'esperado_efectivo'      => 'decimal:2',
        'esperado_tarjeta'       => 'decimal:2',
        'esperado_transferencia' => 'decimal:2',

        'contado_efectivo'      => 'decimal:2',
        'contado_tarjeta'       => 'decimal:2',
        'contado_transferencia' => 'decimal:2',

        // OJO: tus columnas reales
        'dif_efectivo'      => 'decimal:2',
        'dif_tarjeta'       => 'decimal:2',
        'dif_transferencia' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class, 'corte_id');
    }
    public function desglose(): HasOne
    {
        return $this->hasOne(CorteDesgloseEfectivo::class, 'corte_id');
    }

    /** Recalcula ventas del turno desde tabla ventas */
    public function recalcularVentas(): void
    {
        $ventas = DB::table('ventas')
            ->where('empresa_id', $this->empresa_id)
            ->where('sucursal_id', $this->sucursal_id)
            ->where('corte_id', $this->id) // ✅ exacto
            ->where('estado', 'confirmada')
            ->selectRaw("
            SUM(CASE WHEN forma_pago = 'efectivo' THEN GREATEST(total - COALESCE(saldo_aplicado, 0), 0) ELSE 0 END) as efectivo,
            SUM(CASE WHEN forma_pago = 'tarjeta' THEN GREATEST(total - COALESCE(saldo_aplicado, 0), 0) ELSE 0 END) as tarjeta,
            SUM(CASE WHEN forma_pago = 'transferencia' THEN GREATEST(total - COALESCE(saldo_aplicado, 0), 0) ELSE 0 END) as transferencia,
            SUM(CASE WHEN forma_pago = 'credito' THEN GREATEST(total - COALESCE(saldo_aplicado, 0), 0) ELSE 0 END) as credito,
            COUNT(*) as num
        ")
            ->first();

        $this->update([
            'ventas_efectivo'      => $ventas->efectivo      ?? 0,
            'ventas_tarjeta'       => $ventas->tarjeta       ?? 0,
            'ventas_transferencia' => $ventas->transferencia ?? 0,
            'ventas_credito'       => $ventas->credito       ?? 0,
            'num_ventas'           => $ventas->num           ?? 0,
        ]);

        $this->recalcularEsperados();
    }

    /** Recalcula movimientos extras desde tabla movimientos_caja */
    public function recalcularMovimientos(): void
    {
        $movs = $this->movimientos()
            ->selectRaw("
            forma_pago,
            SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END) as neto
        ")
            ->groupBy('forma_pago')
            ->get()
            ->keyBy('forma_pago');

        $this->update([
            'movs_efectivo'      => (float)($movs->get('efectivo')?->neto ?? 0),
            'movs_tarjeta'       => (float)($movs->get('tarjeta')?->neto ?? 0),
            'movs_transferencia' => (float)($movs->get('transferencia')?->neto ?? 0),
        ]);

        $this->recalcularEsperados(); // ✅ antes era recalcularEsperado()
    }

    /** Recalcula totales esperados */
    public function recalcularEsperados(): void
    {
        $fondo = (float) ($this->fondo_inicial_efectivo ?? 0);

        $this->update([
            'esperado_efectivo'       => $fondo + (float)($this->ventas_efectivo ?? 0) + (float)($this->movs_efectivo ?? 0),
            'esperado_tarjeta'        => (float)($this->ventas_tarjeta ?? 0) + (float)($this->movs_tarjeta ?? 0),
            'esperado_transferencia'  => (float)($this->ventas_transferencia ?? 0) + (float)($this->movs_transferencia ?? 0),
        ]);
    }

    /** Recalcula diferencias al cerrar caja */
    public function recalcularDiferencias(): void
    {
        $this->refresh();

        $this->update([
            'dif_efectivo'       => (float)($this->contado_efectivo ?? 0) - (float)($this->esperado_efectivo ?? 0),
            'dif_tarjeta'        => (float)($this->contado_tarjeta ?? 0) - (float)($this->esperado_tarjeta ?? 0),
            'dif_transferencia'  => (float)($this->contado_transferencia ?? 0) - (float)($this->esperado_transferencia ?? 0),
        ]);
    }
}
