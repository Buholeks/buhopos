<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorteCajaAbrirRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'terminal' => ['nullable','string','max:60'],
            'fondo_inicial_efectivo' => ['nullable','numeric','min:0'],
            'notas_apertura' => ['nullable','string'],
        ];
    }
}