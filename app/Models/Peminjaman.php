<?php

namespace App\Models;

use App\Enums\KeperluanPeminjaman;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nim',
    'nama',
    'keperluan',
    'praktikum_slug',
    'no_hp',
    'institusi',
    'email',
    'tanggal_pinjam',
    'tanggal_kembali',
    'status',
    'catatan',
    'approved_by',
    'approved_at',
    'returned_at',
])]
class Peminjaman extends Model
{
    protected $table = 'peminjaman';

    public const STATUS = ['pending', 'disetujui', 'ditolak'];

    public function items(): HasMany
    {
        return $this->hasMany(PeminjamanAlat::class);
    }

    protected function casts(): array
    {
        return [
            'nim' => 'integer',
            'keperluan' => KeperluanPeminjaman::class,
            'tanggal_pinjam' => 'date',
            'tanggal_kembali' => 'date',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }
}
