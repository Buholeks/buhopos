<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\EtiquetaPerfil;
use App\Models\EtiquetaPlantilla;
use App\Models\Producto;
use App\Models\ProductoVariante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EtiquetaController extends Controller
{
    public function qzCertificado(): \Illuminate\Http\Response
    {
        $path = storage_path('qztray/digital-certificate.txt');
        abort_unless(file_exists($path), 404, 'Certificado QZ Tray no encontrado.');
        return response(file_get_contents($path))->header('Content-Type', 'text/plain');
    }

    public function qzInstalador(Request $request): \Illuminate\Http\Response
    {
        $certUrl = url('/api/etiquetas/qztray/cert');
        $script = <<<BAT
        @echo off
        echo Instalando certificado BuhoPos en QZ Tray...
        set CERTS_DIR=%USERPROFILE%\.qz\certs
        if not exist "%CERTS_DIR%" mkdir "%CERTS_DIR%"
        powershell -Command "Invoke-WebRequest -Uri '{$certUrl}' -OutFile '%CERTS_DIR%\buhopos.txt'"
        echo Deteniendo QZ Tray...
        taskkill /F /IM "qz-tray.exe" 2>nul
        timeout /t 2 /nobreak >nul
        echo Iniciando QZ Tray...
        start "" "%ProgramFiles%\QZ Tray\qz-tray.exe"
        if errorlevel 1 start "" "%ProgramFiles(x86)%\QZ Tray\qz-tray.exe"
        echo.
        echo Listo. Certificado instalado correctamente.
        timeout /t 3 /nobreak >nul
        BAT;

        // Eliminar la indentacion extra del heredoc
        $script = preg_replace('/^        /m', '', $script);

        return response($script)
            ->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="instalar-qztray-buhopos.bat"');
    }

    public function qzFirmar(Request $request): JsonResponse
    {
        $datos = $request->input('request', '');
        $keyPath = storage_path('qztray/private-key.pem');
        abort_unless(file_exists($keyPath), 404, 'Clave privada QZ Tray no encontrada.');
        $privateKey = openssl_pkey_get_private(file_get_contents($keyPath));
        abort_unless($privateKey, 500, 'No se pudo leer la clave privada.');
        openssl_sign($datos, $firma, $privateKey, OPENSSL_ALGO_SHA512);
        return response()->json(['signature' => base64_encode($firma)]);
    }

    public function configuracion(): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('etiquetas.imprimir') || Auth::user()->tienePermiso('etiquetas.disenar'), 403);
        $empresaId = (int) Auth::user()->empresa_id;
        $this->asegurarPredeterminados($empresaId);

        return response()->json([
            'plantillas' => EtiquetaPlantilla::where('empresa_id', $empresaId)->where('activa', true)->orderBy('tipo')->orderBy('nombre')->get(),
            'perfiles' => EtiquetaPerfil::where('empresa_id', $empresaId)->where('activo', true)->orderByDesc('predeterminado')->orderBy('nombre')->get(),
            'variables' => $this->variables(),
        ]);
    }

    public function compra(int $compraId): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('compras.ver') || Auth::user()->tienePermiso('compras.crear'), 403);
        $empresaId = (int) Auth::user()->empresa_id;
        $compra = Compra::where('empresa_id', $empresaId)
            ->with([
                'empresa', 'sucursal', 'proveedor',
                'detalles.producto.marca', 'detalles.producto.modelo', 'detalles.producto.categoria',
                'detalles.variante.atributos.tipoAtributo', 'detalles.variante.atributos.atributo',
            ])->findOrFail($compraId);

        return response()->json([
            'compra' => ['id' => $compra->id, 'folio' => $compra->folio, 'fecha' => $compra->fecha?->format('Y-m-d')],
            'items' => $compra->detalles->map(function ($detalle) use ($compra) {
                $datos = $detalle->etiqueta_snapshot ?: $this->snapshot($compra, $detalle->producto, $detalle->variante, (float) $detalle->precio_compra, (float) $detalle->precio_venta);
                // Siempre inyectar datos actuales de la compra aunque el snapshot sea viejo
                $datos['compra'] = [
                    'folio'     => $compra->folio,
                    'fecha'     => $compra->fecha?->format('Y-m-d'),
                    'proveedor' => $compra->proveedor?->nombre_comercial ?? $compra->proveedor?->nombre,
                ];
                return [
                    'id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'variante_id' => $detalle->variante_id,
                    'seleccionado' => true,
                    'cantidad' => max(1, (int) floor((float) $detalle->cantidad)),
                    'precio_impresion' => (float) data_get($datos, 'precios.venta', 0),
                    'datos' => $datos,
                ];
            })->values(),
        ]);
    }

    public function buscarCatalogo(Request $request): JsonResponse
    {
        $this->autorizarImpresion();
        $empresaId = (int) Auth::user()->empresa_id;
        $texto = trim((string) $request->query('q', ''));
        if ($texto === '') return response()->json([]);

        $productos = Producto::where('empresa_id', $empresaId)
            ->where('activo', true)->where('tiene_variantes', false)
            ->with(['empresa', 'sucursal', 'marca', 'modelo', 'categoria'])
            ->where(fn($q) => $q->where('nombre', 'like', "%{$texto}%")->orWhere('codigo', 'like', "%{$texto}%"))
            ->limit(10)->get()->map(fn($p) => $this->catalogoItem($p, null));

        $variantes = ProductoVariante::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->with(['producto.empresa', 'producto.sucursal', 'producto.marca', 'producto.modelo', 'producto.categoria', 'atributos.tipoAtributo', 'atributos.atributo'])
            ->where(fn($q) => $q->where('sku', 'like', "%{$texto}%")
                ->orWhere('codigo_barras', 'like', "%{$texto}%")
                ->orWhereHas('producto', fn($p) => $p->where('nombre', 'like', "%{$texto}%")->orWhere('codigo', 'like', "%{$texto}%")))
            ->limit(15)->get()->map(fn($v) => $this->catalogoItem($v->producto, $v));

        return response()->json($productos->values()->concat($variantes->values()));
    }

    public function guardarPlantilla(Request $request, ?int $id = null): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('etiquetas.disenar'), 403, 'Sin permiso: etiquetas.disenar');
        $empresaId = (int) Auth::user()->empresa_id;
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(['compra', 'precio'])],
            'ancho_mm' => ['required', 'numeric', 'min:10', 'max:300'],
            'alto_mm' => ['required', 'numeric', 'min:10', 'max:500'],
            'diseno' => ['required', 'array'],
            'diseno.elementos' => ['required', 'array'],
            'predeterminada' => ['boolean'],
        ]);

        return DB::transaction(function () use ($id, $empresaId, $data) {
            if ($data['predeterminada'] ?? false) {
                EtiquetaPlantilla::where('empresa_id', $empresaId)->where('tipo', $data['tipo'])->update(['predeterminada' => false]);
            }
            $plantilla = $id
                ? EtiquetaPlantilla::where('empresa_id', $empresaId)->findOrFail($id)
                : new EtiquetaPlantilla(['empresa_id' => $empresaId]);
            $plantilla->fill($data)->save();
            return response()->json($plantilla, $id ? 200 : 201);
        });
    }

    public function eliminarPlantilla(int $id): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('etiquetas.disenar'), 403);
        EtiquetaPlantilla::where('empresa_id', Auth::user()->empresa_id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Plantilla eliminada.']);
    }

    public function guardarPerfil(Request $request, ?int $id = null): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('etiquetas.disenar'), 403);
        $empresaId = (int) Auth::user()->empresa_id;
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'impresora' => ['required', 'string', 'max:120'],
            'material' => ['required', Rule::in(['precortada', 'continua', 'hoja'])],
            'ancho_mm' => ['required', 'numeric', 'min:10', 'max:300'],
            'alto_mm' => ['required', 'numeric', 'min:10', 'max:500'],
            'separacion_mm' => ['numeric', 'min:0', 'max:30'],
            'offset_x_mm' => ['numeric', 'min:-20', 'max:20'],
            'offset_y_mm' => ['numeric', 'min:-20', 'max:20'],
            'escala' => ['numeric', 'min:0.8', 'max:1.2'],
            'rotacion' => ['required', Rule::in([0, 90, 180, 270])],
            'corte_automatico' => ['boolean'],
            'predeterminado' => ['boolean'],
        ]);

        if ($data['material'] === 'continua') {
            // Si el usuario puso la longitud de corte en ancho y el ancho real en alto, corregir.
            if ((float) $data['ancho_mm'] > 62 && (float) $data['alto_mm'] <= 62) {
                [$data['ancho_mm'], $data['alto_mm']] = [$data['alto_mm'], $data['ancho_mm']];
            }
            $data['ancho_mm'] = min(62, (float) $data['ancho_mm']);
            $data['alto_mm']  = max(29, (float) $data['alto_mm']);
        }

        return DB::transaction(function () use ($id, $empresaId, $data) {
            if ($data['predeterminado'] ?? false) EtiquetaPerfil::where('empresa_id', $empresaId)->update(['predeterminado' => false]);
            $perfil = $id ? EtiquetaPerfil::where('empresa_id', $empresaId)->findOrFail($id) : new EtiquetaPerfil(['empresa_id' => $empresaId]);
            $perfil->fill($data)->save();
            return response()->json($perfil, $id ? 200 : 201);
        });
    }

    public function actualizarPrecios(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->tienePermiso('productos.precios'), 403);
        $empresaId = (int) Auth::user()->empresa_id;
        $items = $request->validate(['items' => ['required', 'array'], 'items.*.producto_id' => ['required', 'integer'], 'items.*.variante_id' => ['nullable', 'integer'], 'items.*.precio' => ['required', 'numeric', 'min:0']])['items'];
        foreach ($items as $item) {
            if ($item['variante_id'] ?? null) ProductoVariante::where('empresa_id', $empresaId)->where('id', $item['variante_id'])->update(['precio_venta' => $item['precio']]);
            else Producto::where('empresa_id', $empresaId)->where('id', $item['producto_id'])->update(['precio_venta' => $item['precio']]);
        }
        return response()->json(['message' => 'Precios actualizados.']);
    }

    public function snapshot(Compra $compra, Producto $producto, ?ProductoVariante $variante, float $precioCompra, float $precioVenta): array
    {
        $atributos = $variante?->atributos?->mapWithKeys(fn($a) => [$a->tipoAtributo?->nombre => $a->atributo?->valor])->filter()->all() ?? [];
        return [
            'empresa' => ['nombre' => $compra->empresa?->nombre, 'rfc' => $compra->empresa?->rfc, 'telefono' => $compra->empresa?->telefono, 'direccion' => $compra->empresa?->direccion, 'logo' => $compra->empresa?->logo],
            'sucursal' => ['nombre' => $compra->sucursal?->nombre, 'telefono' => $compra->sucursal?->telefono, 'direccion' => $compra->sucursal?->direccion],
            'compra' => ['folio' => $compra->folio, 'fecha' => $compra->fecha?->format('Y-m-d'), 'proveedor' => $compra->proveedor?->nombre_comercial ?? $compra->proveedor?->nombre],
            'producto' => ['nombre' => $producto->nombre, 'descripcion' => $producto->descripcion, 'codigo' => $producto->codigo, 'marca' => $producto->marca?->nombre, 'modelo' => $producto->modelo?->nombre, 'categoria' => $producto->categoria?->nombre],
            'variante' => ['nombre' => $variante?->nombreVariante(), 'sku' => $variante?->sku, 'codigo_barras' => $variante?->codigo_barras, 'atributos' => $atributos],
            'precios' => ['compra' => $precioCompra, 'venta' => $precioVenta, 'precio1' => (float) ($variante?->precio1 ?? $producto->precio1 ?? 0), 'precio2' => (float) ($variante?->precio2 ?? $producto->precio2 ?? 0), 'precio3' => (float) ($variante?->precio3 ?? $producto->precio3 ?? 0), 'precio4' => (float) ($variante?->precio4 ?? $producto->precio4 ?? 0), 'precio5' => (float) ($variante?->precio5 ?? $producto->precio5 ?? 0)],
            'calculados' => ['codigo_preferido' => $variante?->codigo_barras ?: $variante?->sku ?: $producto->codigo, 'producto_variante' => trim($producto->nombre . ($variante?->nombreVariante() ? ' - '.$variante->nombreVariante() : ''))],
        ];
    }

    private function catalogoItem(Producto $producto, ?ProductoVariante $variante): array
    {
        $compra = new Compra(['folio' => null, 'fecha' => null]);
        $compra->setRelation('empresa', $producto->empresa);
        $compra->setRelation('sucursal', $producto->sucursal);
        $datos = $this->snapshot($compra, $producto, $variante, (float) ($variante?->precio_costo ?? $producto->precio_costo), (float) ($variante?->precio_venta ?? $producto->precio_venta));
        return ['id' => ($variante?->id ? "v{$variante->id}" : "p{$producto->id}"), 'producto_id' => $producto->id, 'variante_id' => $variante?->id, 'cantidad' => 1, 'seleccionado' => true, 'precio_impresion' => $datos['precios']['venta'], 'datos' => $datos];
    }

    private function autorizarImpresion(): void
    {
        abort_unless(Auth::user()->tienePermiso('etiquetas.imprimir'), 403, 'Sin permiso: etiquetas.imprimir');
    }

    private function asegurarPredeterminados(int $empresaId): void
    {
        if (!EtiquetaPerfil::where('empresa_id', $empresaId)->exists()) EtiquetaPerfil::create(['empresa_id' => $empresaId, 'nombre' => 'Brother QL-800 DK-1209', 'impresora' => 'Brother QL-800', 'material' => 'precortada', 'ancho_mm' => 62, 'alto_mm' => 29, 'rotacion' => 0, 'escala' => 1.0, 'activo' => true, 'predeterminado' => true]);
        foreach (['compra', 'precio'] as $tipo) {
            if (EtiquetaPlantilla::where('empresa_id', $empresaId)->where('tipo', $tipo)->exists()) continue;
            EtiquetaPlantilla::create(['empresa_id' => $empresaId, 'nombre' => $tipo === 'compra' ? 'Caja de producto' : 'Precio colgante', 'tipo' => $tipo, 'ancho_mm' => 62, 'alto_mm' => 29, 'predeterminada' => true, 'diseno' => ['elementos' => $this->disenoInicial($tipo)]]);
        }
    }

    private function disenoInicial(string $tipo): array
    {
        $items = [
            ['id' => 'empresa', 'tipo' => 'campo', 'campo' => 'empresa.nombre', 'x' => 2, 'y' => 1, 'ancho' => 58, 'alto' => 4, 'fuente' => 8, 'negrita' => true, 'alineacion' => 'centro'],
            ['id' => 'producto', 'tipo' => 'campo', 'campo' => 'calculados.producto_variante', 'x' => 2, 'y' => 5, 'ancho' => 58, 'alto' => 5, 'fuente' => 8, 'negrita' => true, 'alineacion' => 'centro'],
            ['id' => 'barras', 'tipo' => 'codigo_barras', 'campo' => 'calculados.codigo_preferido', 'x' => 4, 'y' => 11, 'ancho' => 38, 'alto' => 12, 'fuente' => 7, 'negrita' => false, 'alineacion' => 'centro'],
            ['id' => 'precio', 'tipo' => 'precio', 'campo' => 'precios.venta', 'x' => 43, 'y' => 13, 'ancho' => 17, 'alto' => 8, 'fuente' => 15, 'negrita' => true, 'alineacion' => 'centro'],
        ];
        if ($tipo === 'compra') $items[] = ['id' => 'compra', 'tipo' => 'campo', 'campo' => 'compra.folio_fecha', 'x' => 2, 'y' => 24, 'ancho' => 58, 'alto' => 3, 'fuente' => 6, 'negrita' => false, 'alineacion' => 'centro'];
        return $items;
    }

    private function variables(): array
    {
        return [
            ['grupo' => 'Empresa', 'items' => [['campo' => 'empresa.nombre', 'label' => 'Nombre'], ['campo' => 'empresa.rfc', 'label' => 'RFC'], ['campo' => 'empresa.telefono', 'label' => 'Telefono'], ['campo' => 'empresa.direccion', 'label' => 'Direccion']]],
            ['grupo' => 'Compra', 'items' => [['campo' => 'compra.folio', 'label' => 'Folio'], ['campo' => 'compra.fecha', 'label' => 'Fecha'], ['campo' => 'compra.folio_fecha', 'label' => 'Folio y fecha'], ['campo' => 'compra.proveedor', 'label' => 'Proveedor']]],
            ['grupo' => 'Producto', 'items' => [['campo' => 'producto.nombre', 'label' => 'Nombre'], ['campo' => 'producto.codigo', 'label' => 'Codigo'], ['campo' => 'producto.descripcion', 'label' => 'Descripcion'], ['campo' => 'producto.marca', 'label' => 'Marca'], ['campo' => 'producto.modelo', 'label' => 'Modelo'], ['campo' => 'producto.categoria', 'label' => 'Categoria']]],
            ['grupo' => 'Variante', 'items' => [['campo' => 'variante.nombre', 'label' => 'Variante'], ['campo' => 'variante.sku', 'label' => 'SKU'], ['campo' => 'variante.codigo_barras', 'label' => 'Codigo de barras'], ['campo' => 'calculados.producto_variante', 'label' => 'Producto + variante']]],
            ['grupo' => 'Precios', 'items' => [['campo' => 'precios.compra', 'label' => 'Precio compra'], ['campo' => 'precios.venta', 'label' => 'Precio venta'], ['campo' => 'precios.precio1', 'label' => 'Precio 1'], ['campo' => 'precios.precio2', 'label' => 'Precio 2'], ['campo' => 'precios.precio3', 'label' => 'Precio 3']]],
            ['grupo' => 'Especiales', 'items' => [['campo' => 'calculados.codigo_preferido', 'label' => 'Codigo preferido', 'tipo' => 'codigo_barras'], ['campo' => 'texto_libre', 'label' => 'Texto libre', 'tipo' => 'texto']]],
        ];
    }
}
