<?php

namespace App\Support;

use App\Models\CuentaBancaria;
use App\Models\TerminalPago;

/**
 * Filtro secundario de los reportes: con forma de pago "tarjeta" se puede
 * elegir la terminal y con "transferencia" la cuenta bancaria. Fuera de esa
 * forma de pago el id se ignora, para no filtrar por algo que no se ve.
 */
class FiltroCuentaPago
{
    public const REGLAS = [
        'terminal_pago_id'   => ['nullable', 'integer'],
        'cuenta_bancaria_id' => ['nullable', 'integer'],
    ];

    /** @return array{0: string, 1: int}|null [columna, id] a filtrar */
    public static function columna(array $filtros): ?array
    {
        $formaPago = $filtros['forma_pago'] ?? '';

        if ($formaPago === 'tarjeta' && ($id = (int) ($filtros['terminal_pago_id'] ?? 0))) {
            return ['terminal_pago_id', $id];
        }

        if ($formaPago === 'transferencia' && ($id = (int) ($filtros['cuenta_bancaria_id'] ?? 0))) {
            return ['cuenta_bancaria_id', $id];
        }

        return null;
    }

    /** Agrega a una consulta de pagos la condición de terminal o cuenta, si aplica. */
    public static function aplicar($query, array $filtros, string $prefijo = '')
    {
        if ($columna = self::columna($filtros)) {
            $query->where($prefijo . $columna[0], $columna[1]);
        }

        return $query;
    }

    /** Para el encabezado de las exportaciones: ['Terminal' => 'BBVA Mostrador'] o ['Cuenta' => ...]. */
    public static function etiquetas(array $filtros): array
    {
        [$columna, $id] = self::columna($filtros) ?? [null, null];

        return match ($columna) {
            'terminal_pago_id'   => ['Terminal' => TerminalPago::withTrashed()->find($id)?->nombre ?? "ID {$id}"],
            'cuenta_bancaria_id' => ['Cuenta' => CuentaBancaria::withTrashed()->find($id)?->nombre ?? "ID {$id}"],
            default              => [],
        };
    }

    /** Terminales y cuentas de la empresa, incluidas las inactivas: el historial puede tenerlas. */
    public static function opciones(int $empresaId): array
    {
        $opciones = fn($modelo) => $modelo::withTrashed()
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'banco'])
            ->map(fn($item) => [
                'id'     => $item->id,
                'nombre' => collect([$item->nombre, $item->banco])->filter()->join(' · '),
            ]);

        return [
            'terminales' => $opciones(TerminalPago::class),
            'cuentas'    => $opciones(CuentaBancaria::class),
        ];
    }
}
