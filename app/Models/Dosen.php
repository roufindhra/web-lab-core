<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'nama',
    'nip',
    'surel',
    'kepakaran',
    'pengampu',
    'password',
    'is_active',
])]
#[Hidden(['password'])]
class Dosen extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'user_dosen';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'kepakaran' => 'array',
            'pengampu' => 'array',
            'pengampu_slug' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $dosen) {
            if ($dosen->isDirty('pengampu')) {
                $dosen->pengampu_slug = array_map(
                    fn (string $p) => Str::slug($p),
                    $dosen->pengampu ?? [],
                );
            }
        });
    }

    public function getRoleAttribute(): string
    {
        return 'dosen';
    }
}
