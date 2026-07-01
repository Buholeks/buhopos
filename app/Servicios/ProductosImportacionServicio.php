<?php

namespace App\Servicios;

use App\Importaciones\ProductosImportacionLectura;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\InventarioMovimiento;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ProductosImportacionServicio
{
    private array $categoriasCreadas = [];
    private array $marcasCreadas = [];
    private array $modelosCreados = [];
    private array $unidadesCreadas = [];

    public function previsualizar(UploadedFile $archivo): array
    {
        return $this->procesar($archivo, false);
    }

    public function importar(UploadedFile $archivo): array
    {
        return DB::transaction(fn() => $this->procesar($archivo, true));
    }

    private function procesar(UploadedFile $archivo, bool $guardar): array
    {
        $this->reiniciarResumenCatalogos();

        $filas = $this->leerFilas($archivo);
        $errores = [];
        $productos = [];
        $codigosEnArchivo = [];

        foreach ($filas as $indice => $fila) {
            $numeroFila = $indice + 2;
            $datos = $this->normalizarFila($fila);

            if ($this->filaVacia($datos)) {
                continue;
            }

            $erroresFila = $this->validarFila($datos, $numeroFila, $codigosEnArchivo);
            if ($erroresFila) {
                array_push($errores, ...$erroresFila);
                continue;
            }

            if ($datos['codigo'] !== '') {
                $codigosEnArchivo[$this->llave($datos['codigo'])] = $numeroFila;
            }

            $productos[] = [
                'fila' => $numeroFila,
                'datos' => $datos,
                'existente' => $this->productoExistente($datos['codigo']),
            ];
        }

        if ($errores) {
            return $this->respuesta($productos, $errores, false);
        }

        $creados = 0;
        $actualizados = 0;
        $stocksActualizados = 0;

        foreach ($productos as $item) {
            $datos = $item['datos'];
            $producto = $item['existente'];
            $esNuevo = ! $producto;

            $categoriaId = $this->resolverCategoria($datos['categoria'], $guardar)?->id;
            $marca = $this->resolverMarca($datos['marca'], $guardar);
            $modelo = $this->resolverModelo($datos['modelo'], $marca, $guardar);
            $unidad = $this->resolverUnidad($datos['unidad_medida'], $datos['unidad_abreviatura'], $guardar);

            if ($guardar) {
                $payload = [
                    'empresa_id' => $this->empresaId(),
                    'sucursal_id' => $this->sucursalId(),
                    'user_id' => Auth::id(),
                    'categoria_id' => $categoriaId,
                    'marca_id' => $marca?->id,
                    'modelo_id' => $modelo?->id,
                    'unidad_medida_id' => $unidad?->id,
                    'nombre' => $datos['nombre'],
                    'codigo' => $datos['codigo'] !== '' ? $datos['codigo'] : Producto::generarCodigo($this->empresaId()),
                    'descripcion' => $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
                    'precio_costo' => $datos['precio_costo'],
                    'precio_venta' => $datos['precio_venta'],
                    'precio1' => $datos['precio1'],
                    'precio2' => $datos['precio2'],
                    'precio3' => $datos['precio3'],
                    'precio4' => $datos['precio4'],
                    'precio5' => $datos['precio5'],
                    'stock_minimo' => $datos['stock_minimo'] ?? 0,
                    'peso' => $datos['peso'],
                    'tiene_variantes' => false,
                    'tiene_series' => $datos['tiene_series'],
                    'pedido_generico' => $datos['pedido_generico'],
                    'activo' => $datos['activo'],
                ];

                if ($producto) {
                    $producto->fill($payload);
                    $producto->save();
                    $actualizados++;
                } else {
                    $producto = Producto::create($payload);
                    $creados++;
                }

                if ($datos['stock_inicial'] !== null) {
                    $this->actualizarStock($producto, $datos['stock_inicial'], (float) ($datos['stock_minimo'] ?? 0));
                    $stocksActualizados++;
                }
            } elseif ($esNuevo) {
                $creados++;
            } else {
                $actualizados++;
            }
        }

        return array_merge($this->respuesta($productos, [], true), [
            'creados' => $creados,
            'actualizados' => $actualizados,
            'stocks_actualizados' => $stocksActualizados,
        ]);
    }

    private function leerFilas(UploadedFile $archivo): Collection
    {
        $importacion = new ProductosImportacionLectura();
        Excel::import($importacion, $archivo);

        return $importacion->filas();
    }

    private function normalizarFila(Collection|array $fila): array
    {
        $fila = collect($fila)->mapWithKeys(fn($valor, $clave) => [
            $this->normalizarEncabezado((string) $clave) => is_string($valor) ? trim($valor) : $valor,
        ]);

        return [
            'nombre' => $this->texto($fila->get('nombre')),
            'codigo' => $this->texto($fila->get('codigo')),
            'descripcion' => $this->texto($fila->get('descripcion')),
            'categoria' => $this->texto($fila->get('categoria')),
            'marca' => $this->texto($fila->get('marca')),
            'modelo' => $this->texto($fila->get('modelo')),
            'unidad_medida' => $this->texto($fila->get('unidad_medida')),
            'unidad_abreviatura' => $this->texto($fila->get('unidad_abreviatura')),
            'precio_costo' => $this->decimal($fila->get('precio_costo')),
            'precio_venta' => $this->decimal($fila->get('precio_venta')),
            'precio1' => $this->decimal($fila->get('precio1')),
            'precio2' => $this->decimal($fila->get('precio2')),
            'precio3' => $this->decimal($fila->get('precio3')),
            'precio4' => $this->decimal($fila->get('precio4')),
            'precio5' => $this->decimal($fila->get('precio5')),
            'stock_minimo' => $this->decimal($fila->get('stock_minimo')),
            'peso' => $this->decimal($fila->get('peso')),
            'activo' => $this->booleano($fila->get('activo'), true),
            'tiene_series' => $this->booleano($fila->get('tiene_series'), false),
            'pedido_generico' => $this->booleano($fila->get('pedido_generico'), false),
            'stock_inicial' => $this->decimal($fila->get('stock_inicial')),
        ];
    }

    private function validarFila(array $datos, int $fila, array $codigosEnArchivo): array
    {
        $errores = [];

        if ($datos['nombre'] === '') {
            $errores[] = $this->error($fila, 'nombre', 'El nombre es obligatorio.');
        }

        if ($datos['precio_costo'] === null || $datos['precio_costo'] < 0) {
            $errores[] = $this->error($fila, 'precio_costo', 'El precio de costo debe ser mayor o igual a 0.');
        }

        if ($datos['precio_venta'] === null || $datos['precio_venta'] < 0) {
            $errores[] = $this->error($fila, 'precio_venta', 'El precio de venta debe ser mayor o igual a 0.');
        }

        foreach (['precio1', 'precio2', 'precio3', 'precio4', 'precio5', 'stock_minimo', 'peso', 'stock_inicial'] as $campo) {
            if ($datos[$campo] !== null && $datos[$campo] < 0) {
                $errores[] = $this->error($fila, $campo, 'El valor debe ser mayor o igual a 0.');
            }
        }

        if ($datos['modelo'] !== '' && $datos['marca'] === '') {
            $errores[] = $this->error($fila, 'modelo', 'Para importar modelo debes indicar una marca.');
        }

        if ($datos['codigo'] !== '') {
            $llave = $this->llave($datos['codigo']);

            if (isset($codigosEnArchivo[$llave])) {
                $errores[] = $this->error($fila, 'codigo', "El codigo ya aparece en la fila {$codigosEnArchivo[$llave]}.");
            }

            $eliminado = Producto::withTrashed()
                ->where('empresa_id', $this->empresaId())
                ->where('codigo', $datos['codigo'])
                ->whereNotNull('deleted_at')
                ->exists();

            if ($eliminado) {
                $errores[] = $this->error($fila, 'codigo', 'Existe un producto eliminado con este codigo. Recuperalo antes de importar.');
            }
        }

        return $errores;
    }

    private function resolverCategoria(string $ruta, bool $crear): ?Categoria
    {
        if ($ruta === '') {
            return null;
        }

        $partes = collect(preg_split('/\s*[>\/]\s*/', $ruta))
            ->map(fn($parte) => trim((string) $parte))
            ->filter()
            ->values();

        if ($partes->isEmpty()) {
            return null;
        }

        $padreId = null;
        $actual = null;
        $rutaAcumulada = [];

        foreach ($partes as $parte) {
            $rutaAcumulada[] = $parte;
            $actual = Categoria::where('empresa_id', $this->empresaId())
                ->whereRaw('LOWER(nombre) = ?', [Str::lower($parte)])
                ->when($padreId, fn($q) => $q->where('categoria_padre_id', $padreId), fn($q) => $q->whereNull('categoria_padre_id'))
                ->first();

            if (! $actual) {
                $this->registrarPendiente($this->categoriasCreadas, implode(' > ', $rutaAcumulada));

                if (! $crear) {
                    $padreId = -1;
                    continue;
                }

                $actual = new Categoria([
                    'empresa_id' => $this->empresaId(),
                    'sucursal_id' => $this->sucursalId(),
                    'user_id' => Auth::id(),
                    'categoria_padre_id' => $padreId > 0 ? $padreId : null,
                    'nombre' => $parte,
                    'activo' => true,
                    'orden' => 0,
                ]);
                $actual->profundidad = $actual->calcularProfundidad();
                $actual->save();
            }

            $padreId = $actual->id;
        }

        return $actual;
    }

    private function resolverMarca(string $nombre, bool $crear): ?Marca
    {
        if ($nombre === '') {
            return null;
        }

        $marca = Marca::where('empresa_id', $this->empresaId())
            ->whereRaw('LOWER(nombre) = ?', [Str::lower($nombre)])
            ->first();

        if ($marca || ! $crear) {
            if (! $marca) {
                $this->registrarPendiente($this->marcasCreadas, $nombre);
            }
            return $marca;
        }

        $this->registrarPendiente($this->marcasCreadas, $nombre);

        return Marca::create([
            'empresa_id' => $this->empresaId(),
            'sucursal_id' => $this->sucursalId(),
            'user_id' => Auth::id(),
            'nombre' => $nombre,
            'activo' => true,
        ]);
    }

    private function resolverModelo(string $nombre, ?Marca $marca, bool $crear): ?Modelo
    {
        if ($nombre === '' || ! $marca) {
            return null;
        }

        $modelo = Modelo::where('empresa_id', $this->empresaId())
            ->where('marca_id', $marca->id)
            ->whereRaw('LOWER(nombre) = ?', [Str::lower($nombre)])
            ->first();

        $etiqueta = "{$marca->nombre} > {$nombre}";
        if ($modelo || ! $crear) {
            if (! $modelo) {
                $this->registrarPendiente($this->modelosCreados, $etiqueta);
            }
            return $modelo;
        }

        $this->registrarPendiente($this->modelosCreados, $etiqueta);

        return Modelo::create([
            'empresa_id' => $this->empresaId(),
            'sucursal_id' => $this->sucursalId(),
            'user_id' => Auth::id(),
            'marca_id' => $marca->id,
            'nombre' => $nombre,
            'activo' => true,
        ]);
    }

    private function resolverUnidad(string $nombre, string $abreviatura, bool $crear): ?UnidadMedida
    {
        if ($nombre === '' && $abreviatura === '') {
            return null;
        }

        $unidad = UnidadMedida::where('empresa_id', $this->empresaId())
            ->where(function ($q) use ($nombre, $abreviatura) {
                if ($nombre !== '') {
                    $q->orWhereRaw('LOWER(nombre) = ?', [Str::lower($nombre)]);
                }
                if ($abreviatura !== '') {
                    $q->orWhereRaw('LOWER(abreviatura) = ?', [Str::lower($abreviatura)]);
                }
            })
            ->first();

        $nombreFinal = $nombre !== '' ? $nombre : Str::upper($abreviatura);
        $abreviaturaFinal = $abreviatura !== '' ? $abreviatura : Str::upper(Str::substr($nombreFinal, 0, 3));

        if ($unidad || ! $crear) {
            if (! $unidad) {
                $this->registrarPendiente($this->unidadesCreadas, "{$nombreFinal} ({$abreviaturaFinal})");
            }
            return $unidad;
        }

        $this->registrarPendiente($this->unidadesCreadas, "{$nombreFinal} ({$abreviaturaFinal})");

        return UnidadMedida::create([
            'empresa_id' => $this->empresaId(),
            'sucursal_id' => $this->sucursalId(),
            'user_id' => Auth::id(),
            'nombre' => $nombreFinal,
            'abreviatura' => $abreviaturaFinal,
            'tipo' => 'cantidad',
            'activo' => true,
        ]);
    }

    private function actualizarStock(Producto $producto, float $stockNuevo, float $stockMinimo): void
    {
        $inventario = Inventario::firstOrCreate(
            [
                'empresa_id' => $this->empresaId(),
                'sucursal_id' => $this->sucursalId(),
                'producto_id' => $producto->id,
                'variante_id' => null,
            ],
            [
                'stock' => 0,
                'stock_minimo' => $stockMinimo,
                'exhibido' => false,
            ],
        );

        $stockAnterior = (float) $inventario->stock;

        $inventario->update([
            'stock' => $stockNuevo,
            'stock_minimo' => $stockMinimo,
        ]);

        $diferencia = $stockNuevo - $stockAnterior;

        if ($diferencia != 0.0) {
            InventarioMovimiento::create([
                'empresa_id' => $this->empresaId(),
                'sucursal_id' => $this->sucursalId(),
                'producto_id' => $producto->id,
                'variante_id' => null,
                'user_id' => Auth::id(),
                'tipo' => $diferencia > 0 ? 'ajuste_positivo' : 'ajuste_negativo',
                'cantidad_anterior' => $stockAnterior,
                'cantidad_movimiento' => abs($diferencia),
                'cantidad_nueva' => $stockNuevo,
                'motivo' => 'Importacion de productos',
            ]);
        }
    }

    private function productoExistente(string $codigo): ?Producto
    {
        if ($codigo === '') {
            return null;
        }

        return Producto::where('empresa_id', $this->empresaId())
            ->where('codigo', $codigo)
            ->first();
    }

    private function respuesta(array $productos, array $errores, bool $valido): array
    {
        return [
            'valido' => $valido,
            'total_filas' => count($productos) + count($errores),
            'errores' => $errores,
            'creados' => collect($productos)->where('existente', null)->count(),
            'actualizados' => collect($productos)->filter(fn($item) => $item['existente'])->count(),
            'stocks_actualizados' => collect($productos)->filter(fn($item) => $item['datos']['stock_inicial'] !== null)->count(),
            'catalogos' => [
                'categorias' => array_values($this->categoriasCreadas),
                'marcas' => array_values($this->marcasCreadas),
                'modelos' => array_values($this->modelosCreados),
                'unidades' => array_values($this->unidadesCreadas),
            ],
        ];
    }

    private function filaVacia(array $datos): bool
    {
        return collect($datos)->every(fn($valor) => $valor === '' || $valor === null);
    }

    private function decimal(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $limpio = str_replace(['$', ',', ' '], '', (string) $valor);

        return is_numeric($limpio) ? (float) $limpio : null;
    }

    private function booleano(mixed $valor, bool $default): bool
    {
        if ($valor === null || $valor === '') {
            return $default;
        }

        if (is_bool($valor)) {
            return $valor;
        }

        $normalizado = Str::lower(trim((string) $valor));

        return in_array($normalizado, ['1', 'si', 'sí', 'true', 'activo', 'activa', 'yes'], true);
    }

    private function texto(mixed $valor): string
    {
        return trim((string) ($valor ?? ''));
    }

    private function normalizarEncabezado(string $clave): string
    {
        return Str::of($clave)->ascii()->lower()->replace(' ', '_')->toString();
    }

    private function llave(string $valor): string
    {
        return Str::lower(trim($valor));
    }

    private function registrarPendiente(array &$lista, string $valor): void
    {
        $lista[$this->llave($valor)] = $valor;
    }

    private function error(int $fila, string $campo, string $mensaje): array
    {
        return compact('fila', 'campo', 'mensaje');
    }

    private function reiniciarResumenCatalogos(): void
    {
        $this->categoriasCreadas = [];
        $this->marcasCreadas = [];
        $this->modelosCreados = [];
        $this->unidadesCreadas = [];
    }

    private function empresaId(): int
    {
        return (int) Auth::user()->empresa_id;
    }

    private function sucursalId(): int
    {
        return (int) Auth::user()->sucursal_id;
    }
}
