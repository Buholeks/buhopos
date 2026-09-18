<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class QzMessageValidator
{
    public function validate(string $json): void
    {
        $message = json_decode($json, true, 32);
        $this->check(is_array($message), 'Mensaje QZ inválido.');
        $this->check(empty(array_diff(array_keys($message), ['call', 'params', 'timestamp'])), 'Campos QZ no permitidos.');
        $this->check(is_numeric($message['timestamp'] ?? null)
            && abs(microtime(true) * 1000 - (float) $message['timestamp']) <= 300000, 'Mensaje vencido; revisa el reloj del equipo.');
        $call = $message['call'] ?? '';
        $params = $message['params'] ?? null;
        $this->check(in_array($call, ['printers.find', 'printers.getDefault', 'print'], true), 'Operación QZ no permitida.');
        if ($call !== 'print') {
            $this->check($params === null || (is_array($params)
                && empty(array_diff(array_keys($params), ['query']))
                && (!isset($params['query']) || (is_string($params['query']) && strlen($params['query']) <= 255))), 'Consulta de impresora inválida.');
            return;
        }
        $this->check(is_array($params), 'Parámetros inválidos.');
        $this->check(empty(array_diff(array_keys($params), ['printer', 'options', 'data'])), 'Parámetros no permitidos.');
        $printer = $params['printer'] ?? null;
        // Solo colas por nombre: nunca destinos host/puerto ni archivos locales.
        $this->check(is_array($printer) && array_keys($printer) === ['name']
            && is_string($printer['name']) && strlen(trim($printer['name'])) > 0
            && strlen($printer['name']) <= 255 && !preg_match('/[\x00-\x1f]/', $printer['name']), 'Selecciona una cola de impresión válida.');
        $options = $params['options'] ?? [];
        $this->check(is_array($options) && ($options['copies'] ?? 1) == 1, 'Solo se permite una copia por trabajo.');
        $data = $params['data'] ?? null;
        $this->check(is_array($data) && array_is_list($data) && count($data) > 0 && count($data) <= 500, 'Contenido QZ inválido.');
        foreach ($data as $item) {
            $this->check(is_array($item) && is_string($item['data'] ?? null), 'Contenido inválido.');
            $this->check(empty(array_diff(array_keys($item), ['type', 'format', 'flavor', 'data'])), 'Opciones de contenido no permitidas.');
            $html = ($item['type'] ?? '') === 'pixel' && ($item['format'] ?? '') === 'html' && ($item['flavor'] ?? '') === 'plain';
            $raw = ($item['type'] ?? '') === 'raw' && ($item['format'] ?? '') === 'command' && ($item['flavor'] ?? '') === 'hex';
            $this->check($html || $raw, 'Solo se permite HTML inline o comandos RAW hexadecimales.');
            if ($raw) {
                // 4,000,000 caracteres hex (~2 MB de bytes) cubre un ticket rasterizado largo a 600 DPI
                // sin permitir payloads arbitrariamente grandes.
                $this->check(strlen($item['data']) > 0 && strlen($item['data']) <= 4_000_000
                    && strlen($item['data']) % 2 === 0 && ctype_xdigit($item['data']), 'Comandos RAW inválidos.');
            } else {
                $this->check(!preg_match('/<\s*(script|iframe|object|embed)\b|\bon\w+\s*=|(?:file|javascript):/i', $item['data']), 'HTML activo o archivos locales no permitidos.');
            }
        }
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['request' => $message]);
    }
}
