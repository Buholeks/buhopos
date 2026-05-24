<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorteDesgloseEfectivo extends Model
{
    protected $table = 'corte_desglose_efectivo';

    protected $fillable = [
        'corte_id',
        'billetes_1000','billetes_500','billetes_200','billetes_100','billetes_50','billetes_20',
        'monedas_20','monedas_10','monedas_5','monedas_2','monedas_1','monedas_050',
        'total_calculado',
    ];

    protected $casts = [
        'total_calculado' => 'decimal:2',
    ];

    public function corte(): BelongsTo
    {
        return $this->belongsTo(CorteCaja::class, 'corte_id');
    }

    public function calcularTotal(): float
    {
        $b1000 = (int)($this->billetes_1000 ?? 0) * 1000;
        $b500  = (int)($this->billetes_500  ?? 0) * 500;
        $b200  = (int)($this->billetes_200  ?? 0) * 200;
        $b100  = (int)($this->billetes_100  ?? 0) * 100;
        $b50   = (int)($this->billetes_50   ?? 0) * 50;
        $b20   = (int)($this->billetes_20   ?? 0) * 20;

        $m20   = (int)($this->monedas_20    ?? 0) * 20;
        $m10   = (int)($this->monedas_10    ?? 0) * 10;
        $m5    = (int)($this->monedas_5     ?? 0) * 5;
        $m2    = (int)($this->monedas_2     ?? 0) * 2;
        $m1    = (int)($this->monedas_1     ?? 0) * 1;
        $m050  = (int)($this->monedas_050   ?? 0) * 0.5;

        return round($b1000+$b500+$b200+$b100+$b50+$b20+$m20+$m10+$m5+$m2+$m1+$m050, 2);
    }
}