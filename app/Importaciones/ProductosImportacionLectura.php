<?php

namespace App\Importaciones;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductosImportacionLectura implements ToCollection, WithHeadingRow
{
    private Collection $filas;

    public function __construct()
    {
        $this->filas = collect();
    }

    public function collection(Collection $rows): void
    {
        $this->filas = $rows;
    }

    public function filas(): Collection
    {
        return $this->filas;
    }
}
