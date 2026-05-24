<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorteCajaCerrarRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        // Convierte "" a 0 para evitar: "must be integer"
        $all = $this->all();
        foreach ($all as $k => $v) {
            if ($v === '') $all[$k] = 0;
        }
        $this->replace($all);
    }

    public function rules(): array
    {
        return [
            'contado_tarjeta' => ['nullable','numeric','min:0'],
            'contado_transferencia' => ['nullable','numeric','min:0'],
            'notas_cierre' => ['nullable','string'],

            'billetes_1000' => ['nullable','integer','min:0'],
            'billetes_500' => ['nullable','integer','min:0'],
            'billetes_200' => ['nullable','integer','min:0'],
            'billetes_100' => ['nullable','integer','min:0'],
            'billetes_50' => ['nullable','integer','min:0'],
            'billetes_20' => ['nullable','integer','min:0'],

            'monedas_20' => ['nullable','integer','min:0'],
            'monedas_10' => ['nullable','integer','min:0'],
            'monedas_5' => ['nullable','integer','min:0'],
            'monedas_2' => ['nullable','integer','min:0'],
            'monedas_1' => ['nullable','integer','min:0'],
            'monedas_050' => ['nullable','integer','min:0'],
        ];
    }
}