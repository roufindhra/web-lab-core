<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'praktikum_label',
    'semester',
    'is_active',
    'praktikum_slug',
])]

class Praktikum extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'praktikum_nama';

    protected function casts(): array
    {
        return [
            'praktikum_label' => 'string',
            'semester' => 'string',
            'is_active' => 'boolean',
            'praktikum_slug' => 'string',
        ];
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(PraktikumJadwal::class);
    }

    public function pertemuan(): HasMany
    {
        return $this->hasMany(PraktikumPertemuan::class);
    }

    public function kurikulums(): HasMany
    {
        return $this->hasMany(PraktikumKurikulum::class);
    }
}

