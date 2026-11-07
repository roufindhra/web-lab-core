<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'original_filename',
    'file_path',
    'status',
    'total_rows',
    'processed_rows',
    'imported',
    'updated',
    'failed_count',
    'failures',
    'error_message',
    'started_at',
    'finished_at',
])]
class MahasiswaImport extends Model
{
    protected $table = 'mahasiswa_import';

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'imported' => 'integer',
            'updated' => 'integer',
            'failed_count' => 'integer',
            'failures' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }
}
