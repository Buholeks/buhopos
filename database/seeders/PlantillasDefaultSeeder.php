<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlantillasDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = DB::table('empresas')->pluck('id');

        foreach ($empresas as $empresaId) {
            $this->seedPlantillas($empresaId);
            $this->seedPerfiles($empresaId);
            $this->seedTicketConfig($empresaId);
        }
    }

    private function seedTicketConfig(int $empresaId): void
    {
        $yaExiste = DB::table('empresas')
            ->where('id', $empresaId)
            ->whereNotNull('ticket_config')
            ->exists();

        if ($yaExiste) return;

        DB::table('empresas')->where('id', $empresaId)->update([
            'ticket_config' => json_encode($this->ticketConfigDefault()),
            'updated_at'    => now(),
        ]);
    }

    private function ticketConfigDefault(): array
    {
        return [
            'ancho_mm'         => 80,
            'margen_mm'        => 4,
            'mostrar_folio'    => true,
            'mostrar_vendedor' => true,
            'mostrar_cliente'  => true,
            'encabezado'       => [
                'alto_mm'   => 32,
                'elementos' => [
                    ['id' => 'ba9f83dc-4ff1-4fb3-a5fa-952526d2ccad', 'tipo' => 'campo', 'campo' => 'empresa.nombre',     'x' => 0, 'y' => 2,  'ancho' => 73, 'alto' => 7, 'fuente' => 14, 'negrita' => true,  'alineacion' => 'centro'],
                    ['id' => '558f77dc-6d81-4f1b-aef2-5192a38f5f6b', 'tipo' => 'campo', 'campo' => 'empresa.rfc',        'x' => 0, 'y' => 10, 'ancho' => 73, 'alto' => 4, 'fuente' => 8,  'negrita' => false, 'alineacion' => 'centro'],
                    ['id' => '35b9371e-efcc-40bb-a6da-5bb7b5a58276', 'tipo' => 'campo', 'campo' => 'sucursal.nombre',    'x' => 0, 'y' => 15, 'ancho' => 73, 'alto' => 5, 'fuente' => 10, 'negrita' => true,  'alineacion' => 'centro'],
                    ['id' => 'a91bf907-0cb0-4d8c-98d9-a11198948157', 'tipo' => 'campo', 'campo' => 'sucursal.direccion', 'x' => 0, 'y' => 21, 'ancho' => 73, 'alto' => 4, 'fuente' => 8,  'negrita' => false, 'alineacion' => 'centro'],
                    ['id' => 'fba9399c-17c0-43d1-859a-beaec5f53d8a', 'tipo' => 'campo', 'campo' => 'sucursal.telefono', 'x' => 0, 'y' => 26, 'ancho' => 73, 'alto' => 4, 'fuente' => 8,  'negrita' => false, 'alineacion' => 'centro'],
                ],
            ],
            'productos' => [
                'mostrar_variante'        => true,
                'mostrar_identificador'   => true,
                'mostrar_precio_unitario' => true,
                'mostrar_descuento'       => true,
            ],
            'resumen' => [
                'mostrar_subtotal_lista' => true,
                'mostrar_desc_precios'   => true,
                'mostrar_forma_pago'     => true,
                'mostrar_cambio'         => true,
            ],
            'pie' => [
                'alto_mm'   => 28,
                'elementos' => [
                    ['id' => '324f5a1f-2e9a-4e1d-96e1-8b1bf5014d66', 'tipo' => 'separador',    'campo' => '',       'x' => 0,   'y' => 1,  'ancho' => 73, 'alto' => 2],
                    ['id' => '9416ed4c-08ae-436d-8676-86a21a08811d', 'tipo' => 'texto',        'campo' => '',       'texto' => 'Gracias por su compra',                         'x' => 0,   'y' => 4,  'ancho' => 73, 'alto' => 6,  'fuente' => 11, 'negrita' => true,  'alineacion' => 'centro'],
                    ['id' => 'b1d702dc-7b64-472b-90cf-7d5ca7e79f9d', 'tipo' => 'texto',        'campo' => '',       'texto' => 'Conserve este ticket para Cambios o aclaración', 'x' => 0,   'y' => 11, 'ancho' => 73, 'alto' => 4,  'fuente' => 8,  'negrita' => false, 'alineacion' => 'centro'],
                    ['id' => '240378a8-2a79-4097-b734-1b04a5cdede6', 'tipo' => 'codigo_barras', 'campo' => 'folio',  'x' => 2.5, 'y' => 16, 'ancho' => 67, 'alto' => 10, 'mostrar_texto' => false, 'fuente_barcode' => null, 'familia_fuente' => ''],
                ],
            ],
        ];
    }

    private function seedPlantillas(int $empresaId): void
    {
        foreach ($this->plantillasDefault() as $plantilla) {
            $existente = DB::table('etiqueta_plantillas')
                ->where('empresa_id', $empresaId)
                ->where('tipo', $plantilla['tipo'])
                ->first();

            if ($existente) {
                // Actualiza diseño y medidas para que coincida con el diseño master
                DB::table('etiqueta_plantillas')
                    ->where('id', $existente->id)
                    ->update([
                        'ancho_mm'   => $plantilla['ancho_mm'],
                        'alto_mm'    => $plantilla['alto_mm'],
                        'diseno'     => json_encode($plantilla['diseno']),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('etiqueta_plantillas')->insert([
                    'empresa_id'    => $empresaId,
                    'nombre'        => $plantilla['nombre'],
                    'tipo'          => $plantilla['tipo'],
                    'ancho_mm'      => $plantilla['ancho_mm'],
                    'alto_mm'       => $plantilla['alto_mm'],
                    'diseno'        => json_encode($plantilla['diseno']),
                    'predeterminada'=> $plantilla['predeterminada'],
                    'activa'        => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    private function seedPerfiles(int $empresaId): void
    {
        $yaExiste = DB::table('etiqueta_perfiles')
            ->where('empresa_id', $empresaId)
            ->exists();

        if ($yaExiste) return;

        DB::table('etiqueta_perfiles')->insert([
            'empresa_id'       => $empresaId,
            'nombre'           => 'Brother QL-800 DK-1209',
            'impresora'        => 'Brother QL-800',
            'material'         => 'continua',
            'ancho_mm'         => 62,
            'alto_mm'          => 29,
            'separacion_mm'    => 0,
            'offset_x_mm'      => 0,
            'offset_y_mm'      => 0,
            'escala'           => 1,
            'rotacion'         => 0,
            'corte_automatico' => true,
            'predeterminado'   => true,
            'activo'           => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private function plantillasDefault(): array
    {
        return [
            [
                'nombre'        => 'Compra',
                'tipo'          => 'compra',
                'ancho_mm'      => 62,
                'alto_mm'       => 29,
                'predeterminada'=> true,
                'diseno'        => [
                    'elementos' => [
                        ['id' => 'empresa',        'tipo' => 'campo',        'campo' => 'empresa.nombre',               'x' => 3,    'y' => 2.5,  'ancho' => 56, 'alto' => 3,   'fuente' => 6,  'negrita' => false, 'alineacion' => 'izquierda', 'familia_fuente' => 'Helvetica, Arial, sans-serif'],
                        ['id' => 'producto',       'tipo' => 'campo',        'campo' => 'calculados.producto_variante', 'x' => 3,    'y' => 5.5,  'ancho' => 56, 'alto' => 4,   'fuente' => 7,  'negrita' => false, 'alineacion' => 'izquierda', 'familia_fuente' => 'Trebuchet MS, sans-serif'],
                        ['id' => 'linea_superior', 'tipo' => 'linea_h',      'campo' => null,                           'x' => 0,    'y' => 9,    'ancho' => 62, 'alto' => 1,   'fuente' => 8,  'negrita' => false, 'alineacion' => 'izquierda', 'texto' => null, 'color' => '#000000', 'color_relleno' => '#000000', 'grosor' => 0.3],
                        ['id' => 'barras',         'tipo' => 'codigo_barras','campo' => 'calculados.codigo_preferido',  'x' => 11.5, 'y' => 10,   'ancho' => 39, 'alto' => 9.5, 'fuente' => 8,  'negrita' => false, 'alineacion' => 'centro', 'mostrar_texto' => true, 'fuente_barcode' => 7],
                        ['id' => 'linea_inferior', 'tipo' => 'linea_h',      'campo' => null,                           'x' => 0,    'y' => 20,   'ancho' => 62, 'alto' => 1,   'fuente' => 8,  'negrita' => false, 'alineacion' => 'izquierda', 'texto' => null, 'color' => '#000000', 'color_relleno' => '#000000', 'grosor' => 0.3],
                        ['id' => 'precio',         'tipo' => 'precio',       'campo' => 'precios.venta',                'x' => 3,    'y' => 21,   'ancho' => 28, 'alto' => 5.5, 'fuente' => 15, 'negrita' => true,  'alineacion' => 'izquierda', 'familia_fuente' => 'Helvetica, Arial, sans-serif'],
                        ['id' => 'folio',          'tipo' => 'campo',        'campo' => 'compra.folio',                 'x' => 32,   'y' => 21.5, 'ancho' => 27, 'alto' => 4.5, 'fuente' => 8,  'negrita' => true,  'alineacion' => 'derecha', 'texto' => null, 'mostrar_texto' => true],
                        ['id' => 'compra',         'tipo' => 'campo',        'campo' => 'compra.folio_fecha',           'x' => 3,    'y' => 26,   'ancho' => 56, 'alto' => 2.5, 'fuente' => 5,  'negrita' => false, 'alineacion' => 'centro'],
                    ],
                ],
            ],
            [
                'nombre'        => 'precios',
                'tipo'          => 'precio',
                'ancho_mm'      => 62,
                'alto_mm'       => 29,
                'predeterminada'=> false,
                'diseno'        => [
                    'elementos' => [
                        ['id' => 'f7ff480e-5d42-441f-b400-f320f202ed16', 'tipo' => 'campo',        'campo' => 'empresa.nombre',               'x' => 3,    'y' => 3,    'ancho' => 30,   'alto' => 3,  'fuente' => 6,  'negrita' => false, 'alineacion' => 'izquierda', 'texto' => null, 'mostrar_texto' => true],
                        ['id' => 'ccf7afda-1d89-492e-8e4e-c524b18c6dab', 'tipo' => 'campo',        'campo' => 'calculados.producto_variante', 'x' => 3,    'y' => 6.5,  'ancho' => 54,   'alto' => 3,  'fuente' => 6,  'negrita' => false, 'alineacion' => 'izquierda', 'texto' => null, 'mostrar_texto' => true],
                        ['id' => '9e26af74-5699-470c-a7ef-861262bed6c0', 'tipo' => 'codigo_barras','campo' => 'variante.codigo_barras',        'x' => 24.5, 'y' => 11,   'ancho' => 33.5, 'alto' => 10, 'fuente' => 8,  'negrita' => false, 'alineacion' => 'izquierda', 'texto' => null, 'mostrar_texto' => true, 'fuente_barcode' => 7],
                        ['id' => 'a5b18ec6-318c-432f-aa06-1afa2d2968f4', 'tipo' => 'precio',       'campo' => 'precios.venta',                'x' => 3,    'y' => 13,   'ancho' => 30,   'alto' => 5,  'fuente' => 15, 'negrita' => true,  'alineacion' => 'izquierda', 'texto' => null, 'mostrar_texto' => true, 'familia_fuente' => 'Helvetica, Arial, sans-serif'],
                        ['id' => '6e1a88ac-a94c-484a-ab93-6cd68f054242', 'tipo' => 'linea_h',      'campo' => null,                           'x' => 0,    'y' => 20.5, 'ancho' => 62,   'alto' => 3,  'fuente' => 8,  'negrita' => false, 'alineacion' => 'izquierda', 'texto' => null, 'color' => '#000000', 'color_relleno' => '#000000', 'grosor' => 0.3],
                        ['id' => '31daefa7-d3da-48d3-aa4b-61697ec7c3ba', 'tipo' => 'linea_h',      'campo' => null,                           'x' => 0,    'y' => 8.5,  'ancho' => 62,   'alto' => 3,  'fuente' => 8,  'negrita' => false, 'alineacion' => 'izquierda', 'texto' => null, 'color' => '#000000', 'color_relleno' => '#000000', 'grosor' => 0.3],
                        ['id' => '90434941-176a-4ab3-83d8-e3f995118a06', 'tipo' => 'texto',        'campo' => 'texto_libre',                  'x' => 3,    'y' => 24,   'ancho' => 55,   'alto' => 3,  'fuente' => 6,  'negrita' => false, 'alineacion' => 'centro',    'texto' => 'Contáctanos al 985 227 33 20', 'mostrar_texto' => true],
                    ],
                ],
            ],
        ];
    }
}
