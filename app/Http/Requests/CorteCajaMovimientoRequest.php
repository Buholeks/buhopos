<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorteCajaMovimientoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tipo' => ['required','in:ingreso,egreso'],
            'forma_pago' => ['required','in:efectivo,tarjeta,transferencia'],
            'monto' => ['required','numeric','min:0.01'],
            'concepto' => ['required','string','max:255'],
        ];
    }
}