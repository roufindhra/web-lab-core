<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nama_alat',
    'tarif_per_hari',
])]
class HargaSewa extends Model
{
    use SoftDeletes;

    protected $table = 'harga_sewa';

    protected function casts(): array
    {
        return [
            'tarif_per_hari' => 'decimal:2',
        ];
    }
}
