<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mahasiswa_id',
    'dosen_id',
    'token',
    'permissions',
    'expires_at',
    'claimed_at',
    'revoked_at',
])]
class AdminAlatToken extends Model
{
    public const PERMISSIONS = ['edit_status', 'edit_kondisi', 'crud_inventaris'];

    /** durasi dalam menit */
    public const DURATIONS = [
        '30_mins' => 30,
        '2_hours' => 120,
        '1_day' => 1440,
    ];

    protected $table = 'admin_alat_tokens';

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function isActive(): bool
    {
        return $this->claimed_at !== null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    public function status(): string
    {
        if ($this->revoked_at !== null) {
            return 'dicabut';
        }
        if ($this->expires_at->isPast()) {
            return 'kadaluarsa';
        }
        if ($this->claimed_at === null) {
            return 'belum_diklaim';
        }

        return 'aktif';
    }
}
