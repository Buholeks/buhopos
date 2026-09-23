<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

class TicketConfigValidator
{
    public function validate(array $config): void
    {
        $rules = [
            'ancho_mm' => ['required', 'numeric', 'between:40,112'],
            'margen_mm' => ['required', 'numeric', 'between:0,10'],
            'productos' => ['sometimes', 'array'],
            'resumen' => ['sometimes', 'array'],
        ];
        foreach (['mostrar_folio', 'mostrar_vendedor', 'mostrar_cliente', 'productos.mostrar_variante',
            'productos.mostrar_identificador', 'productos.mostrar_precio_unitario', 'productos.mostrar_descuento',
            'resumen.mostrar_subtotal_lista', 'resumen.mostrar_desc_precios', 'resumen.mostrar_forma_pago', 'resumen.mostrar_cambio'] as $field) {
            $rules[$field] = ['sometimes', 'boolean'];
        }
        foreach (['encabezado', 'pie'] as $zone) {
            $rules[$zone] = ['required', 'array'];
            $rules["$zone.alto_mm"] = ['required', 'numeric', 'between:0,1000'];
            $rules["$zone.elementos"] = ['present', 'array', 'max:100'];
            $rules["$zone.elementos.*"] = ['required', 'array'];
            $prefix = "$zone.elementos.*.";
            $rules[$prefix.'id'] = ['required', 'string', 'max:100', 'distinct'];
            $rules[$prefix.'tipo'] = ['required', 'in:campo,texto,separador,codigo_barras'];
            foreach (['x', 'y'] as $field) $rules[$prefix.$field] = ['required', 'numeric', 'between:-1000,1000'];
            $rules[$prefix.'ancho'] = ['required', 'numeric', 'gt:0', 'max:1000'];
            $rules[$prefix.'alto'] = ['required', 'numeric', 'between:0,1000'];
            foreach (['fuente', 'fuente_barcode'] as $field) $rules[$prefix.$field] = ['nullable', 'numeric', 'between:1,100'];
            $rules[$prefix.'campo'] = ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z_][a-zA-Z0-9_.]*$/'];
            $rules[$prefix.'texto'] = ['nullable', 'string', 'max:2000'];
            $rules[$prefix.'familia_fuente'] = ['nullable', 'string', 'max:200', 'regex:/^[a-zA-Z0-9 ,\x27\x22-]+$/'];
            $rules[$prefix.'alineacion'] = ['sometimes', 'in:izquierda,centro,derecha'];
            foreach (['negrita', 'mostrar_texto'] as $field) $rules[$prefix.$field] = ['sometimes', 'boolean'];
        }
        $validator = Validator::make($config, $rules);
        $validator->after(function ($validator) use ($config) {
            foreach (['encabezado', 'pie'] as $zone) {
                $elements = $config[$zone]['elementos'] ?? [];
                if (!is_array($elements)) continue;
                foreach ($elements as $index => $el) {
                    if (is_array($el) && ($el['tipo'] ?? '') !== 'separador' && is_numeric($el['alto'] ?? null) && (float) $el['alto'] <= 0) {
                        $validator->errors()->add("$zone.elementos.$index.alto", 'El alto debe ser mayor que cero.');
                    }
                }
            }
        });
        $validator->validate();
    }
}
