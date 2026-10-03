<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComisionEsquema extends Model
{
    protected $table = 'comision_esquemas';

    public const BENEFICIARIO_VENDEDOR = 'vendedor';
    public const BENEFICIARIO_CAJERO   = 'cajero';
    public const BENEFICIARIOS = [self::BENEFICIARIO_VENDEDOR, self::BENEFICIARIO_CAJERO];

    // Qué reglas admite el esquema además de la general ("para lo demás").
    public const MODO_GENERAL   = 'general';
    public const MODO_CATEGORIA = 'categoria';
    public const MODO_PRODUCTO  = 'producto';
    public const MODOS = [self::MODO_GENERAL, self::MODO_CATEGORIA, self::MODO_PRODUCTO];

    protected $fillable = [
        'empresa_id',
        'nombre',
        'beneficiario',
        'modo',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function reglas(): HasMany
    {
        return $this->hasMany(ComisionRegla::class, 'esquema_id');
    }

    /** Usuarios que tienen asignado este esquema (como vendedor o cajero según beneficiario). */
    public function usuarios(): HasMany
    {
        $columna = $this->beneficiario === self::BENEFICIARIO_CAJERO
            ? 'esquema_cajero_id'
            : 'esquema_vendedor_id';

        return $this->hasMany(User::class, $columna);
    }
}
