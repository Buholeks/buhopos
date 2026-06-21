<?php

namespace App\Traits;

use App\Models\Media;
use App\Models\Mediable;
use App\Support\PublicImageStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesMediaImages
{
    /**
     * Asigna una imagen desde la biblioteca de media a una entidad.
     * Elimina la referencia mediable anterior para esa entidad+role.
     * Devuelve la ruta que debe guardarse en el campo imagen del modelo.
     */
    protected function asignarImagenDesdeMedia(
        Model $entity,
        int   $mediaId,
        string $role = 'imagen'
    ): string {
        $empresaId = (int) auth()->user()->empresa_id;

        $media = Media::where('empresa_id', $empresaId)->findOrFail($mediaId);

        // Eliminar referencia previa de esta entidad + role
        Mediable::where('mediable_type', get_class($entity))
            ->where('mediable_id', $entity->id)
            ->where('role', $role)
            ->delete();

        // Crear nueva referencia
        Mediable::create([
            'media_id'      => $media->id,
            'mediable_type' => get_class($entity),
            'mediable_id'   => $entity->id,
            'role'          => $role,
        ]);

        return $media->ruta;
    }

    /**
     * Sube un archivo directamente, registrándolo en la tabla media con dedup por hash.
     * Devuelve la ruta que debe guardarse en el campo imagen del modelo.
     */
    protected function subirYRegistrarImagen(
        Model        $entity,
        UploadedFile $archivo,
        string       $carpetaReal,
        string       $role = 'imagen'
    ): string {
        $empresaId = (int) auth()->user()->empresa_id;

        $hash = hash_file('sha256', $archivo->getRealPath());

        $media = Media::where('empresa_id', $empresaId)->where('hash', $hash)->first();

        if (! $media) {
            $ruta = PublicImageStorage::store($archivo, $carpetaReal);
            $media = Media::create([
                'empresa_id'      => $empresaId,
                'hash'            => $hash,
                'ruta'            => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'carpeta'         => $carpetaReal,
                'tamanio'         => $archivo->getSize(),
                'mime_type'       => $archivo->getMimeType(),
            ]);
        }

        // Eliminar referencia previa y crear nueva
        Mediable::where('mediable_type', get_class($entity))
            ->where('mediable_id', $entity->id)
            ->where('role', $role)
            ->delete();

        Mediable::create([
            'media_id'      => $media->id,
            'mediable_type' => get_class($entity),
            'mediable_id'   => $entity->id,
            'role'          => $role,
        ]);

        return $media->ruta;
    }

    /**
     * Quita la referencia mediable de una entidad + role.
     * El archivo físico NO se elimina automáticamente (se limpia desde MediaLibrary).
     */
    protected function quitarReferenciaMedia(Model $entity, string $role = 'imagen'): void
    {
        Mediable::where('mediable_type', get_class($entity))
            ->where('mediable_id', $entity->id)
            ->where('role', $role)
            ->delete();
    }

    /**
     * Borra el archivo físico del storage si existe.
     * Úsalo solo cuando la entidad NO usaba el sistema media (legacy).
     */
    protected function borrarArchivoLegacy(?string $ruta): void
    {
        if ($ruta) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
