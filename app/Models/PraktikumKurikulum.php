<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'praktikum_id',
    'materi_id',
    'materi_nama',
    'deskripsi',
    'is_active',
])]
class PraktikumKurikulum extends Model
{
    protected $table = 'praktikum_kurikulum';

    protected function casts(): array
    {
        return [
            'materi_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function praktikum(): BelongsTo
    {
        return $this->belongsTo(Praktikum::class);
    }

    public function pertemuan(): HasMany
    {
        return $this->hasMany(PraktikumPertemuan::class, 'materi_id');
    }
}
