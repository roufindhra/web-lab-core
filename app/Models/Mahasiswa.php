<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'nama',
    'nim',
    'surel',
    'angkatan',
    'praktikum',
    'praktikum_plug',
    'praktikum_kelompok',
    'is_asisten',
    'pengampu_praktikum',
    'pengampu_plug',
    'is_active_asisten',
    'password',
    'is_active_user',
])]
#[Hidden(['password'])]
class Mahasiswa extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'user_mahasiswa';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_asisten' => 'boolean',
            'is_active_asisten' => 'boolean',
            'is_active_user' => 'boolean',
            'praktikum' => 'array',
            'praktikum_plug' => 'array',
            'praktikum_kelompok' => 'array',
            'pengampu_praktikum' => 'array',
            'pengampu_plug' => 'array',
        ];
    }

    public function getRoleAttribute(): string
    {
        if ($this->is_asisten && $this->is_active_asisten) {
            return 'asisten';
        }

        return 'mahasiswa';
    }

    public function activeAlatToken(): ?AdminAlatToken
    {
        return AdminAlatToken::query()
            ->where('mahasiswa_id', $this->id)
            ->whereNotNull('claimed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('claimed_at')
            ->first();
    }
}
