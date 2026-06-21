<?php

namespace App\Models;

use App\Support\PublicImageStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'empresa_id',
        'hash',
        'ruta',
        'nombre_original',
        'carpeta',
        'tamanio',
        'mime_type',
    ];

    protected $appends = ['url'];

    // ── Relationships ────────────────────────────────────────────────────────────

    public function mediables(): HasMany
    {
        return $this->hasMany(Mediable::class, 'media_id');
    }

    // ── Accessors ────────────────────────────────────────────────────────────────

    public function getUrlAttribute(): ?string
    {
        return PublicImageStorage::url($this->ruta);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    /** Label legible para mostrar en la UI */
    public function carpetaLabel(): string
    {
        return match (true) {
            str_starts_with($this->carpeta, 'productos/')  => 'Productos',
            str_starts_with($this->carpeta, 'variantes/')  => 'Variantes',
            $this->carpeta === 'marcas/logos'              => 'Logos de Marcas',
            $this->carpeta === 'modelos/imagenes'          => 'Modelos',
            default                                        => $this->carpeta,
        };
    }

    /** Tipo normalizado para agrupar en la UI */
    public function tipoNormalizado(): string
    {
        return match (true) {
            str_starts_with($this->carpeta, 'productos/')  => 'productos',
            str_starts_with($this->carpeta, 'variantes/')  => 'variantes',
            $this->carpeta === 'marcas/logos'              => 'marcas',
            $this->carpeta === 'modelos/imagenes'          => 'modelos',
            default                                        => 'otros',
        };
    }

    /** Tamaño formateado KB / MB */
    public function tamanioFormateado(): string
    {
        if ($this->tamanio >= 1_048_576) {
            return round($this->tamanio / 1_048_576, 1) . ' MB';
        }
        return round($this->tamanio / 1_024, 1) . ' KB';
    }
}
