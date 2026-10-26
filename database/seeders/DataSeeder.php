<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataSeeder extends Seeder
{
    // urutan mengikuti dependensi foreign key
    private array $tables = [
        'user_dosen',
        'user_mahasiswa',
        'alat',
        'harga_sewa',
        'peminjaman',
        'alat_log',
        'peminjaman_alat',
        'praktikum_nama',
        'praktikum_jadwal',
        'praktikum_kurikulum',
        'praktikum_pertemuan',
        'praktikum_nilai',
    ];

    public function run(): void
    {
        foreach ($this->tables as $table) {
            $path = database_path("data/{$table}.json");

            if (! file_exists($path)) {
                $this->command?->warn("  - {$table}: file tidak ditemukan, dilewati");
                continue;
            }

            $rows = json_decode(file_get_contents($path), true);

            if (empty($rows)) {
                $this->command?->line("  - {$table}: kosong");
                continue;
            }

            // samakan key antar baris (insert batch butuh kolom yang sama)
            $columns = array_unique(array_merge(...array_map('array_keys', $rows)));
            $rows = array_map(function (array $row) use ($columns) {
                foreach ($columns as $col) {
                    $row[$col] = $row[$col] ?? null;
                }
                ksort($row);
                return $row;
            }, $rows);

            // kolom jsonb diekspor sebagai array; encode ulang untuk insert
            $rows = array_map(function (array $row) {
                foreach ($row as &$value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                }
                return $row;
            }, $rows);

            DB::table($table)->insert($rows);

            // baris di-insert dengan id eksplisit, sinkronkan sequence supaya
            // insert berikutnya tidak bentrok dengan id hasil seed
            $seq = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS name', [$table, 'id']);
            if ($seq !== null && $seq->name !== null) {
                DB::selectOne("SELECT setval(?, (SELECT COALESCE(MAX(id), 1) FROM {$table}))", [$seq->name]);
            }

            $this->command?->line("  - {$table}: ".count($rows).' baris');
        }
    }
}
