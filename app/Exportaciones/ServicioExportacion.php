<?php

namespace App\Exportaciones;

use App\Models\Empresa;
use App\Models\Sucursal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ServicioExportacion
{
    /**
     * Exporta cualquier exportación a Excel (.xlsx) o PDF.
     *
     * @param  ExportacionBase  $exportacion  Instancia de la exportación concreta
     * @param  string           $formato      'excel' | 'pdf'
     * @param  string           $nombreArchivo  Sin extensión
     */
    public function exportar(
        ExportacionBase $exportacion,
        string $formato,
        string $nombreArchivo
    ): BinaryFileResponse|Response {
        return match ($formato) {
            'excel' => $this->exportarExcel($exportacion, $nombreArchivo),
            'pdf'   => $this->exportarPdf($exportacion, $nombreArchivo),
            default => abort(400, "Formato '{$formato}' no soportado. Use 'excel' o 'pdf'."),
        };
    }

    private function exportarExcel(ExportacionBase $exportacion, string $nombreArchivo): BinaryFileResponse
    {
        return Excel::download($exportacion, "{$nombreArchivo}.xlsx");
    }

    private function exportarPdf(ExportacionBase $exportacion, string $nombreArchivo): Response
    {
        $vista = $exportacion->vistaParaPdf();

        $empresa  = $exportacion->empresaId()  ? Empresa::find($exportacion->empresaId())   : null;
        $sucursal = $exportacion->sucursalId() ? Sucursal::find($exportacion->sucursalId()) : null;

        $filas = $exportacion->datos();

        $pdf = Pdf::loadView($vista, [
            'titulo'          => $exportacion->titulo(),
            'cabeceras'       => $exportacion->cabeceras(),
            'filas'           => $filas,
            'totales'         => $exportacion->totales(),
            'empresaNombre'   => $empresa?->nombre ?? config('app.name'),
            'empresaLogoB64'  => $this->logoBase64($empresa),
            'empresaDireccion' => $empresa?->direccion,
            'sucursalNombre'  => $sucursal?->nombre,
            'sucursalDireccion' => $sucursal?->direccion,
            'filtrosAplicados' => $exportacion->filtrosAplicados(),
            'fecha'           => now('America/Mexico_City')->format('d/m/Y H:i'),
        ])->setPaper('letter', 'landscape');

        return $pdf->download("{$nombreArchivo}.pdf");
    }

    private function logoBase64(?Empresa $empresa): ?string
    {
        if (! $empresa?->logo) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($empresa->logo)) {
            return null;
        }

        $contenido = $disk->get($empresa->logo);
        $mime      = $disk->mimeType($empresa->logo) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contenido);
    }
}
