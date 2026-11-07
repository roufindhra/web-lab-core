<?php

namespace App\Jobs;

use App\Models\Mahasiswa;
use App\Models\MahasiswaImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImportMahasiswaCsv implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 500;

    private const MAX_REPORTED_FAILURES = 100;

    /**
     * Hash cost sementara untuk password hasil import. Hash akan otomatis
     * di-upgrade ke cost normal saat mahasiswa login pertama kali.
     */
    private const IMPORT_HASH_ROUNDS = 4;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $importId) {}

    public function handle(): void
    {
        @set_time_limit(0);

        $import = MahasiswaImport::find($this->importId);

        if (! $import) {
            return;
        }

        $import->update([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ]);

        $path = $import->file_path;

        try {
            $this->process($import, $path);
        } catch (Throwable $e) {
            $import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    public function failed(?Throwable $e): void
    {
        $import = MahasiswaImport::find($this->importId);

        if (! $import || $import->isFinished()) {
            return;
        }

        $import->update([
            'status' => 'failed',
            'error_message' => $e?->getMessage() ?? 'Import gagal diproses.',
            'finished_at' => now(),
        ]);
    }

    private function process(MahasiswaImport $import, string $path): void
    {
        $handle = Storage::disk('local')->readStream($path);

        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        try {
            [$rows, $failures, $totalRows] = $this->parse($handle);
        } finally {
            fclose($handle);
        }

        $import->update([
            'total_rows' => $totalRows,
            'failed_count' => count($failures),
            'failures' => array_slice($failures, 0, self::MAX_REPORTED_FAILURES),
        ]);

        if ($rows === []) {
            $import->update([
                'status' => 'completed',
                'processed_rows' => $totalRows,
                'finished_at' => now(),
            ]);

            return;
        }

        $existing = Mahasiswa::query()
            ->whereIn('nim', array_keys($rows))
            ->get(['nim', 'password'])
            ->mapWithKeys(fn (Mahasiswa $mahasiswa): array => [
                (int) $mahasiswa->nim => $mahasiswa->password,
            ]);

        $imported = 0;
        $updated = 0;
        $processed = count($failures);

        foreach (array_chunk($rows, self::CHUNK_SIZE, true) as $chunk) {
            $values = [];

            foreach ($chunk as $nim => $row) {
                // Nilai password & flag ikut dikirim hanya agar tuple INSERT valid;
                // kolom-kolom tersebut tidak di-update saat NIM sudah ada.
                if ($existing->has($nim)) {
                    $row['password'] = $existing->get($nim);
                    $updated++;
                } else {
                    $row['password'] = Hash::make((string) $nim, ['rounds' => self::IMPORT_HASH_ROUNDS]);
                    $imported++;
                }

                $row['is_asisten'] = false;
                $row['is_active_user'] = true;

                $values[] = $row;
            }

            DB::transaction(function () use ($values): void {
                Mahasiswa::upsert($values, ['nim'], ['nama', 'surel', 'angkatan']);
            });

            $processed += count($chunk);

            $import->update([
                'processed_rows' => $processed,
                'imported' => $imported,
                'updated' => $updated,
            ]);
        }

        $import->update([
            'status' => 'completed',
            'processed_rows' => $totalRows,
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  resource  $handle
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array{row: int, message: string}>, 2: int}
     */
    private function parse($handle): array
    {
        $header = fgetcsv($handle);

        if ($header === false) {
            throw new RuntimeException('File CSV kosong.');
        }

        $header = array_map(
            fn ($column): string => strtolower(trim((string) $column)),
            $header,
        );
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $missingColumns = array_diff(['nama', 'nim'], $header);

        if ($missingColumns !== []) {
            throw new RuntimeException('Kolom wajib tidak ditemukan: '.implode(', ', $missingColumns).'.');
        }

        $columns = array_flip($header);
        $rows = [];
        $failures = [];
        $totalRows = 0;
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $totalRows++;

            $nama = trim((string) ($row[$columns['nama']] ?? ''));
            $nim = trim((string) ($row[$columns['nim']] ?? ''));

            if ($nama === '' || $nim === '') {
                $failures[] = ['row' => $rowNumber, 'message' => 'nama dan nim wajib diisi.'];

                continue;
            }

            if (! ctype_digit($nim) || strlen($nim) !== 9) {
                $failures[] = ['row' => $rowNumber, 'message' => 'nim harus berupa 9 digit angka.'];

                continue;
            }

            // NIM duplikat dalam satu file: baris terakhir yang dipakai.
            $rows[(int) $nim] = [
                'nama' => mb_convert_case($nama, MB_CASE_TITLE, 'UTF-8'),
                'nim' => (int) $nim,
                'surel' => $nim.'@student.upnyk.ac.id',
                'angkatan' => 2000 + (int) substr($nim, 3, 2),
            ];
        }

        return [$rows, $failures, $totalRows];
    }
}
