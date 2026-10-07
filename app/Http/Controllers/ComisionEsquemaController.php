<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\ComisionEsquema;
use App\Models\ComisionMovimiento;
use App\Models\ComisionRegla;
use App\Models\ComisionReglaPrecio;
use App\Models\Producto;
use App\Models\User;
use App\Services\ComisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Configuración de comisiones: esquemas, grupos de reglas, asignación a usuarios y "probar regla".
 *
 * Un "grupo" es la configuración de una categoría, un producto o "lo demás" (general) dentro
 * del esquema. Se guarda como una o varias filas de comision_reglas (una por rango de precio),
 * así el cálculo de ComisionService no cambia.
 */
class ComisionEsquemaController extends Controller
{
    // ── GET /api/comisiones/esquemas ──────────────────────────────────────────

    public function index(): JsonResponse
    {
        $user = $this->autorizar();

        $esquemas = ComisionEsquema::where('empresa_id', $user->empresa_id)
            ->orderBy('beneficiario')
            ->orderBy('nombre')
            ->get();

        $grupos = ComisionRegla::where('empresa_id', $user->empresa_id)
            ->where('activo', true)
            ->where('aplica_a', '!=', ComisionRegla::APLICA_GENERAL)
            ->get(['esquema_id', 'aplica_a', 'referencia_id'])
            ->groupBy('esquema_id')
            ->map(fn($reglas) => $reglas->unique(fn($r) => $r->aplica_a . '|' . $r->referencia_id)->count());

        $conGeneral = ComisionRegla::where('empresa_id', $user->empresa_id)
            ->where('activo', true)
            ->where('aplica_a', ComisionRegla::APLICA_GENERAL)
            ->distinct()
            ->pluck('esquema_id')
            ->flip();

        $asignados = $this->conteoAsignados((int) $user->empresa_id);
        $esquemas->each(function ($e) use ($grupos, $conGeneral, $asignados) {
            $e->grupos_count = $grupos[$e->id] ?? 0;
            $e->tiene_general = isset($conGeneral[$e->id]);
            $e->usuarios_count = $asignados[$e->id] ?? 0;
        });

        return response()->json($esquemas);
    }

    // ── GET /api/comisiones/esquemas/{id} ─────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $user = $this->autorizar();
        $esquema = $this->esquemaDeEmpresa($id, (int) $user->empresa_id);

        return response()->json($this->esquemaCompleto($esquema));
    }

    // ── POST /api/comisiones/esquemas ─────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $user = $this->autorizar();

        $data = $request->validate([
            'nombre'       => ['required', 'string', 'min:2', 'max:100', $this->nombreUnico((int) $user->empresa_id)],
            'beneficiario' => ['required', Rule::in(ComisionEsquema::BENEFICIARIOS)],
            'modo'         => ['required', Rule::in(ComisionEsquema::MODOS)],
            'descripcion'  => ['nullable', 'string', 'max:255'],
            'activo'       => ['sometimes', 'boolean'],
        ], $this->mensajesEsquema());

        $esquema = ComisionEsquema::create($data + ['empresa_id' => $user->empresa_id, 'activo' => true]);

        return response()->json($this->esquemaCompleto($esquema), 201);
    }

    // ── PUT /api/comisiones/esquemas/{id} ─────────────────────────────────────
    // Beneficiario y modo no se cambian: los usuarios asignados y las reglas dependen de ellos.

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->autorizar();
        $esquema = $this->esquemaDeEmpresa($id, (int) $user->empresa_id);

        $data = $request->validate([
            'nombre'      => ['required', 'string', 'min:2', 'max:100', $this->nombreUnico((int) $user->empresa_id, $esquema->id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo'      => ['sometimes', 'boolean'],
        ], $this->mensajesEsquema());

        $esquema->update($data);

        return response()->json($this->esquemaCompleto($esquema));
    }

    // ── DELETE /api/comisiones/esquemas/{id} ──────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $user = $this->autorizar();
        $esquema = $this->esquemaDeEmpresa($id, (int) $user->empresa_id);

        if (ComisionMovimiento::where('esquema_id', $esquema->id)->exists()) {
            return response()->json([
                'message' => 'El esquema ya generó comisiones. Desactívalo en lugar de eliminarlo.',
            ], 422);
        }

        // Los usuarios asignados quedan sin esquema (FK nullOnDelete).
        $esquema->delete();

        return response()->json(['message' => 'Esquema eliminado.']);
    }

    // ── POST /api/comisiones/esquemas/{id}/duplicar ───────────────────────────

    public function duplicar(Request $request, int $id): JsonResponse
    {
        $user = $this->autorizar();
        $original = $this->esquemaDeEmpresa($id, (int) $user->empresa_id);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:100', $this->nombreUnico((int) $user->empresa_id)],
        ], $this->mensajesEsquema());

        $copia = DB::transaction(function () use ($original, $data) {
            $copia = $original->replicate();
            $copia->nombre = $data['nombre'];
            $copia->save();

            foreach ($original->reglas()->with('precios')->where('activo', true)->get() as $regla) {
                $nueva = $regla->replicate();
                $nueva->esquema_id = $copia->id;
                $nueva->save();

                foreach ($regla->precios as $precio) {
                    $nueva->precios()->create($precio->only(['lista', 'activo', 'tipo', 'valor']));
                }
            }

            return $copia;
        });

        return response()->json($this->esquemaCompleto($copia), 201);
    }

    // ── PUT /api/comisiones/esquemas/{id}/grupos ──────────────────────────────
    // Crea o reemplaza la configuración de una categoría/producto/"lo demás".
    // Editar solo afecta ventas futuras: los movimientos guardan su propio snapshot.

    public function guardarGrupo(Request $request, int $id): JsonResponse
    {
        $user = $this->autorizar();
        $esquema = $this->esquemaDeEmpresa($id, (int) $user->empresa_id);
        DB::transaction(function () use ($esquema, $request) {
            ComisionEsquema::whereKey($esquema->id)->lockForUpdate()->firstOrFail();
            $data = $this->validarGrupo($request, $esquema);
            // Si se cambió la categoría/producto de un grupo existente, se retira el anterior.
            if (! empty($data['anterior_referencia_id']) && (int) $data['anterior_referencia_id'] !== (int) $data['referencia_id']) {
                $this->retirarGrupo($esquema, $data['aplica_a'], (int) $data['anterior_referencia_id']);
            }
            $this->retirarGrupo($esquema, $data['aplica_a'], $data['referencia_id']);

            foreach ($this->reglasDelGrupo($data) as $campos) {
                $regla = $esquema->reglas()->create($campos['regla'] + ['empresa_id' => $esquema->empresa_id]);
                foreach ($campos['precios'] as $precio) {
                    $regla->precios()->create($precio);
                }
            }
        });

        return response()->json($this->esquemaCompleto($esquema));
    }

    // ── DELETE /api/comisiones/esquemas/{id}/grupos ───────────────────────────

    public function eliminarGrupo(Request $request, int $id): JsonResponse
    {
        $user = $this->autorizar();
        $esquema = $this->esquemaDeEmpresa($id, (int) $user->empresa_id);

        $data = $request->validate([
            'aplica_a'      => ['required', Rule::in(ComisionRegla::APLICA_A)],
            'referencia_id' => ['nullable', 'integer'],
        ]);
        $referencia = $data['aplica_a'] === ComisionRegla::APLICA_GENERAL ? null : ($data['referencia_id'] ?? null);

        DB::transaction(function () use ($esquema, $data, $referencia) {
            ComisionEsquema::whereKey($esquema->id)->lockForUpdate()->firstOrFail();
            $this->retirarGrupo($esquema, $data['aplica_a'], $referencia);
        });

        return response()->json(['message' => 'Regla eliminada.', 'esquema' => $this->esquemaCompleto($esquema)]);
    }

    // ── POST /api/comisiones/esquemas/{id}/probar ─────────────────────────────

    public function probar(Request $request, int $id, ComisionService $servicio): JsonResponse
    {
        $user = $this->autorizar();
        $esquema = $this->esquemaDeEmpresa($id, (int) $user->empresa_id);

        $data = $request->validate([
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->where('empresa_id', $user->empresa_id)],
            'precio'      => ['required', 'numeric', 'min:0'],
            'lista'       => ['required', Rule::in(ComisionReglaPrecio::LISTAS)],
            'cantidad'    => ['required', 'numeric', 'min:0.001'],
        ]);

        $producto = Producto::findOrFail($data['producto_id']);
        $precio = (float) $data['precio'];
        $cantidad = (float) $data['cantidad'];

        $resultado = $servicio->calcularRenglon(
            $esquema,
            $producto->id,
            $producto->categoria_id,
            $precio,
            $data['lista'],
            $cantidad,
            round($precio * $cantidad, 2),
            $producto->precio_costo !== null ? (float) $producto->precio_costo : null,
        );

        $regla = $resultado['regla_id'] ? ComisionRegla::find($resultado['regla_id']) : null;
        $resultado['regla'] = $regla ? [
            'aplica_a'          => $regla->aplica_a,
            'referencia_nombre' => $this->nombresReferencias(collect([$regla]))[$this->claveGrupo($regla)] ?? null,
            'precio_min'        => $regla->precio_min !== null ? (float) $regla->precio_min : null,
            'precio_max'        => $regla->precio_max !== null ? (float) $regla->precio_max : null,
        ] : null;

        return response()->json($resultado);
    }

    // ── GET /api/comisiones/usuarios ──────────────────────────────────────────

    public function usuarios(): JsonResponse
    {
        $user = $this->autorizar();

        $usuarios = User::where('empresa_id', $user->empresa_id)
            ->orderByDesc('activo')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'activo', 'esquema_vendedor_id', 'esquema_cajero_id']);

        return response()->json($usuarios);
    }

    // ── PUT /api/comisiones/usuarios/{id} ─────────────────────────────────────

    public function asignar(Request $request, int $id): JsonResponse
    {
        $user = $this->autorizar();
        $empresaId = (int) $user->empresa_id;
        $usuario = User::where('empresa_id', $empresaId)->findOrFail($id);

        $esquemaDe = fn(string $beneficiario) => Rule::exists('comision_esquemas', 'id')
            ->where('empresa_id', $empresaId)
            ->where('beneficiario', $beneficiario);

        $data = $request->validate([
            'esquema_vendedor_id' => ['nullable', 'integer', $esquemaDe(ComisionEsquema::BENEFICIARIO_VENDEDOR)],
            'esquema_cajero_id'   => ['nullable', 'integer', $esquemaDe(ComisionEsquema::BENEFICIARIO_CAJERO)],
        ], [
            'esquema_vendedor_id.exists' => 'El esquema de vendedor no es válido.',
            'esquema_cajero_id.exists'   => 'El esquema de cajero no es válido.',
        ]);

        $usuario->update([
            'esquema_vendedor_id' => $data['esquema_vendedor_id'] ?? null,
            'esquema_cajero_id'   => $data['esquema_cajero_id'] ?? null,
        ]);

        return response()->json($usuario->only(['id', 'name', 'email', 'activo', 'esquema_vendedor_id', 'esquema_cajero_id']));
    }

    // ── GET /api/comisiones/catalogo/categorias?q= ────────────────────────────

    public function buscarCategorias(Request $request): JsonResponse
    {
        $user = $this->autorizar();
        $q = trim((string) $request->query('q', ''));

        $categorias = Categoria::where('empresa_id', $user->empresa_id)
            ->with('padre:id,nombre')
            ->when($q !== '', fn($query) => $query->where('nombre', 'like', "%{$q}%"))
            ->orderBy('nombre')
            ->limit(30)
            ->get(['id', 'nombre', 'categoria_padre_id']);

        return response()->json($categorias);
    }

    // ── GET /api/comisiones/catalogo/productos?q= ─────────────────────────────

    public function buscarProductos(Request $request): JsonResponse
    {
        $user = $this->autorizar();
        $q = trim((string) $request->query('q', ''));

        $productos = Producto::where('empresa_id', $user->empresa_id)
            ->with('categoria:id,nombre')
            ->when($q !== '', fn($query) => $query->where(fn($sub) => $sub
                ->where('nombre', 'like', "%{$q}%")
                ->orWhere('codigo', 'like', "%{$q}%")))
            ->orderBy('nombre')
            ->limit(30)
            ->get(['id', 'nombre', 'codigo', 'categoria_id', 'precio_venta', 'precio1', 'precio2', 'precio3', 'precio4', 'precio5']);

        return response()->json($productos);
    }

    // ── Internos: autorización y búsquedas ────────────────────────────────────

    private function autorizar(): User
    {
        $user = Auth::user();
        abort_unless($user->tienePermiso('comisiones.configurar'), 403, 'Sin permiso: comisiones.configurar');
        abort_unless(\App\Services\ComisionAcceso::puedeConfigurar($user), 403,
            'Los esquemas afectan a toda la empresa. Necesitas permiso de configuración de comisiones en todas sus sucursales.');
        return $user;
    }

    private function esquemaDeEmpresa(int $id, int $empresaId): ComisionEsquema
    {
        return ComisionEsquema::where('empresa_id', $empresaId)->findOrFail($id);
    }

    private function nombreUnico(int $empresaId, ?int $ignorar = null)
    {
        return Rule::unique('comision_esquemas', 'nombre')
            ->where('empresa_id', $empresaId)
            ->ignore($ignorar);
    }

    private function mensajesEsquema(): array
    {
        return ['nombre.unique' => 'Ya existe un esquema con ese nombre.'];
    }

    /** @return array<int, int> esquema_id => usuarios asignados */
    private function conteoAsignados(int $empresaId): array
    {
        $conteo = [];
        foreach (['esquema_vendedor_id', 'esquema_cajero_id'] as $columna) {
            User::where('empresa_id', $empresaId)
                ->whereNotNull($columna)
                ->groupBy($columna)
                ->selectRaw("{$columna} as esquema_id, count(*) as total")
                ->get()
                ->each(function ($fila) use (&$conteo) {
                    $conteo[$fila->esquema_id] = ($conteo[$fila->esquema_id] ?? 0) + (int) $fila->total;
                });
        }
        return $conteo;
    }

    // ── Internos: grupos ──────────────────────────────────────────────────────

    /**
     * Payload de un grupo:
     *   aplica_a, referencia_id, anterior_referencia_id?, base, tipo,
     *   forma: unico | rangos,
     *   valor                      (forma unico)
     *   rangos[{desde, valor}], hasta_final?, fuera_de_rango   (forma rangos)
     *   precios[{lista, activo, tipo?, valor?}]
     *     - unico: tabla completa opcional con monto por precio
     *     - rangos: solo activo/inactivo (el monto lo da el rango)
     */
    private function validarGrupo(Request $request, ComisionEsquema $esquema): array
    {
        $data = $request->validate([
            'aplica_a'               => ['required', Rule::in([$esquema->modo, ComisionRegla::APLICA_GENERAL])],
            'referencia_id'          => ['nullable', 'integer'],
            'anterior_referencia_id' => ['nullable', 'integer'],
            'base'                   => ['required', Rule::in(ComisionRegla::BASES)],
            'tipo'                   => ['required', Rule::in(ComisionRegla::TIPOS)],
            'forma'                  => ['required', Rule::in(['unico', 'rangos'])],
            'valor'                  => ['required_if:forma,unico', 'nullable', 'numeric', 'min:0', 'max:999999'],
            'rangos'                 => ['required_if:forma,rangos', 'array'],
            'rangos.*.desde'         => ['required', 'numeric', 'min:0'],
            'rangos.*.valor'         => ['required', 'numeric', 'min:0', 'max:999999'],
            'hasta_final'            => ['nullable', 'numeric', 'min:0'],
            'fuera_de_rango'         => ['nullable', Rule::in(ComisionRegla::FUERA_DE_RANGO)],
            'precios'                => ['nullable', 'array'],
            'precios.*.lista'        => ['required', 'distinct', Rule::in(ComisionReglaPrecio::LISTAS)],
            'precios.*.activo'       => ['required', 'boolean'],
            'precios.*.tipo'         => ['nullable', Rule::in(ComisionRegla::TIPOS)],
            'precios.*.valor'        => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ], [
            'aplica_a.in'              => 'Este esquema no admite ese tipo de regla.',
            'rangos.required_if'       => 'Agrega al menos un rango.',
            'precios.*.lista.distinct' => 'Cada precio solo puede aparecer una vez.',
        ]);

        $errores = [];
        $esGeneral = $data['aplica_a'] === ComisionRegla::APLICA_GENERAL;
        $porcentaje = $data['tipo'] === ComisionRegla::TIPO_PORCENTAJE;

        // Referencia
        if ($esGeneral) {
            $data['referencia_id'] = null;
        } elseif (empty($data['referencia_id'])) {
            $errores['referencia_id'] = $data['aplica_a'] === ComisionRegla::APLICA_PRODUCTO
                ? 'Selecciona el producto.'
                : 'Selecciona la categoría.';
        } else {
            $tabla = $data['aplica_a'] === ComisionRegla::APLICA_PRODUCTO ? 'productos' : 'categorias';
            if (! DB::table($tabla)->where('id', $data['referencia_id'])->where('empresa_id', $esquema->empresa_id)->exists()) {
                $errores['referencia_id'] = 'La referencia seleccionada no pertenece a la empresa.';
            }
        }

        // Un grupo por referencia: crear uno que ya existe obliga a editarlo.
        $editando = array_key_exists('anterior_referencia_id', $data)
            && (int) ($data['anterior_referencia_id'] ?? 0) === (int) ($data['referencia_id'] ?? 0);
        if (! $editando && ! isset($errores['referencia_id']) && $this->reglasActivasDelGrupo($esquema, $data['aplica_a'], $data['referencia_id'])->isNotEmpty()) {
            $errores['referencia_id'] = $esGeneral
                ? 'La regla para lo demás ya existe; edítala.'
                : ($data['aplica_a'] === ComisionRegla::APLICA_PRODUCTO
                    ? 'Ya hay una regla para ese producto; edítala.'
                    : 'Ya hay una regla para esa categoría; edítala.');
        }

        // Valores
        if ($data['forma'] === 'unico') {
            if ($porcentaje && (float) $data['valor'] > 100) {
                $errores['valor'] = 'El porcentaje no puede ser mayor a 100.';
            }
            foreach ($data['precios'] ?? [] as $i => $p) {
                if (! empty($p['activo']) && (! isset($p['tipo'], $p['valor']))) {
                    $errores["precios.{$i}.valor"] = 'Indica el valor.';
                } elseif (! empty($p['activo']) && $p['tipo'] === ComisionRegla::TIPO_PORCENTAJE && (float) $p['valor'] > 100) {
                    $errores["precios.{$i}.valor"] = 'El porcentaje no puede ser mayor a 100.';
                }
            }
        } else {
            $anterior = null;
            foreach ($data['rangos'] as $i => $rango) {
                if ($anterior !== null && (float) $rango['desde'] <= $anterior) {
                    $errores["rangos.{$i}.desde"] = 'Cada rango debe empezar después del anterior.';
                }
                if ($porcentaje && (float) $rango['valor'] > 100) {
                    $errores["rangos.{$i}.valor"] = 'El porcentaje no puede ser mayor a 100.';
                }
                $anterior = (float) $rango['desde'];
            }
            if (isset($data['hasta_final']) && $anterior !== null && (float) $data['hasta_final'] < $anterior) {
                $errores['hasta_final'] = 'El límite final debe ser mayor o igual al inicio del último rango.';
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return $data;
    }

    /**
     * Convierte el payload del grupo en filas de comision_reglas (+ sus precios).
     * Los rangos se encadenan: cada uno termina un centavo antes del siguiente, sin huecos.
     *
     * @return array<int, array{regla: array, precios: array}>
     */
    private function reglasDelGrupo(array $data): array
    {
        $comunes = [
            'aplica_a'       => $data['aplica_a'],
            'referencia_id'  => $data['referencia_id'],
            'base'           => $data['base'],
            'tipo'           => $data['tipo'],
            'activo'         => true,
        ];

        if ($data['forma'] === 'unico') {
            $precios = collect($data['precios'] ?? [])->map(fn($p) => [
                'lista'  => $p['lista'],
                'activo' => (bool) $p['activo'],
                'tipo'   => $p['tipo'] ?? $data['tipo'],
                'valor'  => $p['valor'] ?? 0,
            ])->all();

            return [[
                'regla'   => $comunes + [
                    'valor' => $data['valor'], 'precio_min' => null, 'precio_max' => null,
                    'fuera_de_rango' => ComisionRegla::FUERA_USAR_SIGUIENTE,
                ],
                'precios' => $precios,
            ]];
        }

        // En rangos, la tabla de precios solo apaga listas; las encendidas usan el valor del rango.
        $apagadas = collect($data['precios'] ?? [])->filter(fn($p) => ! $p['activo'])->pluck('lista');
        $rangos = array_values($data['rangos']);
        $fuera = $data['aplica_a'] === ComisionRegla::APLICA_GENERAL
            ? ComisionRegla::FUERA_SIN_COMISION   // "lo demás" no tiene una regla siguiente
            : ($data['fuera_de_rango'] ?? ComisionRegla::FUERA_USAR_SIGUIENTE);

        $filas = [];
        foreach ($rangos as $i => $rango) {
            $siguiente = $rangos[$i + 1] ?? null;
            $hasta = $siguiente
                ? round((float) $siguiente['desde'] - 0.01, 2)
                : (isset($data['hasta_final']) ? (float) $data['hasta_final'] : null);

            $filas[] = [
                'regla'   => $comunes + [
                    'valor'          => $rango['valor'],
                    'precio_min'     => (float) $rango['desde'] > 0 ? (float) $rango['desde'] : null,
                    'precio_max'     => $hasta,
                    'fuera_de_rango' => $fuera,
                ],
                'precios' => $apagadas->map(fn($lista) => [
                    'lista' => $lista, 'activo' => false, 'tipo' => $data['tipo'], 'valor' => 0,
                ])->all(),
            ];
        }

        return $filas;
    }

    private function reglasActivasDelGrupo(ComisionEsquema $esquema, string $aplicaA, ?int $referenciaId): Collection
    {
        return $esquema->reglas()
            ->where('activo', true)
            ->where('aplica_a', $aplicaA)
            ->when($referenciaId, fn($q) => $q->where('referencia_id', $referenciaId), fn($q) => $q->whereNull('referencia_id'))
            ->get();
    }

    /** Quita las reglas de un grupo: borra las que nunca se usaron y desactiva las que ya generaron comisiones. */
    private function retirarGrupo(ComisionEsquema $esquema, string $aplicaA, ?int $referenciaId): void
    {
        $reglas = $this->reglasActivasDelGrupo($esquema, $aplicaA, $referenciaId);
        if ($reglas->isEmpty()) {
            return;
        }

        $usadas = ComisionMovimiento::whereIn('regla_id', $reglas->pluck('id'))->distinct()->pluck('regla_id')->flip();

        foreach ($reglas as $regla) {
            isset($usadas[$regla->id]) ? $regla->update(['activo' => false]) : $regla->delete();
        }
    }

    private function claveGrupo(ComisionRegla $regla): string
    {
        return $regla->aplica_a . '|' . ($regla->referencia_id ?? '');
    }

    /** @return array<string, string> clave de grupo => nombre de la categoría/producto */
    private function nombresReferencias(Collection $reglas): array
    {
        $categorias = Categoria::withTrashed()
            ->whereIn('id', $reglas->where('aplica_a', ComisionRegla::APLICA_CATEGORIA)->pluck('referencia_id')->filter())
            ->pluck('nombre', 'id');
        $productos = Producto::withTrashed()
            ->whereIn('id', $reglas->where('aplica_a', ComisionRegla::APLICA_PRODUCTO)->pluck('referencia_id')->filter())
            ->pluck('nombre', 'id');

        $nombres = [];
        foreach ($reglas as $regla) {
            $nombres[$this->claveGrupo($regla)] = match ($regla->aplica_a) {
                ComisionRegla::APLICA_CATEGORIA => $categorias[$regla->referencia_id] ?? '(categoría eliminada)',
                ComisionRegla::APLICA_PRODUCTO  => $productos[$regla->referencia_id] ?? '(producto eliminado)',
                default                         => 'Lo demás',
            };
        }
        return $nombres;
    }

    private function esquemaCompleto(ComisionEsquema $esquema): array
    {
        $reglas = $esquema->reglas()->with('precios')->where('activo', true)->orderBy('id')->get();
        $nombres = $this->nombresReferencias($reglas);

        $grupos = $reglas->groupBy(fn($r) => $this->claveGrupo($r))
            ->map(fn(Collection $grupo, string $clave) => $this->describirGrupo($grupo, $nombres[$clave] ?? ''))
            ->values();

        $general = $grupos->firstWhere('aplica_a', ComisionRegla::APLICA_GENERAL);
        $especificos = $grupos->where('aplica_a', '!=', ComisionRegla::APLICA_GENERAL)
            ->sortBy('referencia_nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $esquema->only(['id', 'empresa_id', 'nombre', 'beneficiario', 'modo', 'descripcion', 'activo']) + [
            'usuarios_count' => $this->conteoAsignados((int) $esquema->empresa_id)[$esquema->id] ?? 0,
            'grupos'         => $especificos,
            'general'        => $general,
        ];
    }

    /** Reconstruye el payload de edición a partir de las filas de reglas del grupo. */
    private function describirGrupo(Collection $reglas, string $nombre): array
    {
        $primera = $reglas->first();
        $conRango = $reglas->contains(fn(ComisionRegla $r) => $r->tieneRango());

        $base = [
            'aplica_a'          => $primera->aplica_a,
            'referencia_id'     => $primera->referencia_id,
            'referencia_nombre' => $nombre,
            'base'              => $primera->base,
            'tipo'              => $primera->tipo,
        ];

        if (! $conRango && $reglas->count() === 1) {
            return $base + [
                'forma'   => 'unico',
                'valor'   => (float) $primera->valor,
                'rangos'  => [],
                'hasta_final' => null,
                'fuera_de_rango' => ComisionRegla::FUERA_USAR_SIGUIENTE,
                'precios' => $primera->precios->map(fn($p) => [
                    'lista' => $p->lista, 'activo' => (bool) $p->activo, 'tipo' => $p->tipo, 'valor' => (float) $p->valor,
                ])->values(),
            ];
        }

        $ordenadas = $reglas->sortBy(fn($r) => (float) ($r->precio_min ?? 0))->values();
        $apagadas = $ordenadas->flatMap(fn($r) => $r->precios->where('activo', false)->pluck('lista'))->unique()->values();

        return $base + [
            'forma'          => 'rangos',
            'valor'          => null,
            'rangos'         => $ordenadas->map(fn($r) => ['desde' => (float) ($r->precio_min ?? 0), 'valor' => (float) $r->valor])->values(),
            'hasta_final'    => $ordenadas->last()->precio_max !== null ? (float) $ordenadas->last()->precio_max : null,
            'fuera_de_rango' => $primera->fuera_de_rango,
            'precios'        => $apagadas->map(fn($lista) => ['lista' => $lista, 'activo' => false])->values(),
        ];
    }
}
