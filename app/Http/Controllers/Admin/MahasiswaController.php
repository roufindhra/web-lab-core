<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MahasiswaResource;
use App\Models\Mahasiswa;
use App\Services\CrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MahasiswaController extends Controller
{
    public function __construct(private readonly CrudService $crud)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'angkatan' => ['nullable', 'integer'],
            'role' => ['nullable', 'in:asisten,asisten_all,mahasiswa'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ]);

        $mahasiswa = Mahasiswa::query()
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('nim', 'like', "%{$search}%");
                });
            })
            ->when($validated['angkatan'] ?? null, fn ($query, int $angkatan) => $query->where('angkatan', $angkatan))
            ->when(($validated['role'] ?? null) === 'asisten', fn ($query) => $query
                ->where('is_asisten', true)
                ->where('is_active_asisten', true))
            ->when(($validated['role'] ?? null) === 'asisten_all', fn ($query) => $query
                ->where('is_asisten', true))
            ->when(($validated['role'] ?? null) === 'mahasiswa', fn ($query) => $query
                ->where(function ($query) {
                    $query->where('is_asisten', false)->orWhere('is_active_asisten', false);
                }))
            ->orderBy('nim', $validated['sort_direction'] ?? 'asc')
            ->paginate(perPage: $validated['per_page'] ?? 50, page: $validated['page'] ?? null);

        return $this->crud->paginated($mahasiswa, MahasiswaResource::class);
    }

    public function update(Request $request, Mahasiswa $mahasiswa): JsonResponse
    {
        $data = $request->validate([
            'praktikum' => ['present', 'array'],
            'praktikum.*' => ['string', 'alpha_dash', Rule::exists('praktikum_nama', 'praktikum_slug')],
            'praktikum_plug' => ['present', 'array'],
            'praktikum_plug.*' => ['string', 'max:50'],
        ]);

        $slugs = array_values($data['praktikum']);
        $plugs = $data['praktikum_plug'];

        if (array_diff($slugs, array_keys($plugs)) !== [] || array_diff(array_keys($plugs), $slugs) !== []) {
            return response()->json([
                'message' => 'praktikum_plug harus memiliki key yang sama dengan praktikum.',
            ], 422);
        }

        foreach ($plugs as $slug => $plug) {
            $jadwalTersedia = DB::table('praktikum_jadwal')
                ->join('praktikum_nama', 'praktikum_jadwal.praktikum_id', '=', 'praktikum_nama.id')
                ->where('praktikum_nama.praktikum_slug', $slug)
                ->where('praktikum_jadwal.plug', $plug)
                ->exists();

            if (! $jadwalTersedia) {
                return response()->json([
                    'message' => "Jadwal untuk plug {$plug} pada praktikum {$slug} belum tersedia.",
                ], 422);
            }
        }

        $mahasiswa->update([
            'praktikum' => $slugs,
            'praktikum_plug' => $plugs,
        ]);

        return response()->json(new MahasiswaResource($mahasiswa->fresh()));
    }

    public function updateAsisten(Request $request, Mahasiswa $mahasiswa): JsonResponse
    {
        $data = $request->validate([
            'is_asisten' => ['required', 'boolean'],
            'is_active_asisten' => ['required', 'boolean'],
            'pengampu_praktikum' => ['present', 'array'],
            'pengampu_praktikum.*' => ['string', 'alpha_dash', Rule::exists('praktikum_nama', 'praktikum_slug')],
            'pengampu_plug' => ['present', 'array'],
            'pengampu_plug.*' => ['present', 'array'],
            'pengampu_plug.*.*' => ['string', 'max:50'],
        ]);

        $slugs = array_values($data['pengampu_praktikum']);
        $plugs = $data['pengampu_plug'];

        if (array_diff($slugs, array_keys($plugs)) !== [] || array_diff(array_keys($plugs), $slugs) !== []) {
            return response()->json([
                'message' => 'pengampu_plug harus memiliki key yang sama dengan pengampu_praktikum.',
            ], 422);
        }

        foreach ($plugs as $slug => $plugList) {
            foreach ($plugList as $plug) {
                $jadwalTersedia = DB::table('praktikum_jadwal')
                    ->join('praktikum_nama', 'praktikum_jadwal.praktikum_id', '=', 'praktikum_nama.id')
                    ->where('praktikum_nama.praktikum_slug', $slug)
                    ->where('praktikum_jadwal.plug', $plug)
                    ->exists();

                if (! $jadwalTersedia) {
                    return response()->json([
                        'message' => "Jadwal untuk plug {$plug} pada praktikum {$slug} belum tersedia.",
                    ], 422);
                }
            }
        }

        $mahasiswa->update([
            'is_asisten' => $data['is_asisten'],
            'is_active_asisten' => $data['is_active_asisten'],
            'pengampu_praktikum' => $slugs,
            'pengampu_plug' => $plugs,
        ]);

        return response()->json(new MahasiswaResource($mahasiswa->fresh()));
    }

    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return response()->json(['message' => 'File tidak dapat dibaca.'], 422);
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return response()->json(['message' => 'File CSV kosong.'], 422);
        }

        $header = array_map(fn (string $column): string => strtolower(trim($column)), $header);

        $requiredColumns = ['nama', 'nim'];
        $missingColumns = array_diff($requiredColumns, $header);

        if ($missingColumns !== []) {
            fclose($handle);

            return response()->json([
                'message' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missingColumns).'.',
            ], 422);
        }

        $columns = array_flip($header);
        $imported = 0;
        $updated = 0;
        $failed = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $nama = trim((string) ($row[$columns['nama']] ?? ''));
            $nim = trim((string) ($row[$columns['nim']] ?? ''));

            if ($nama === '' || $nim === '') {
                $failed[] = ['row' => $rowNumber, 'message' => 'nama dan nim wajib diisi.'];

                continue;
            }

            if (! ctype_digit($nim) || strlen($nim) !== 9) {
                $failed[] = ['row' => $rowNumber, 'message' => 'nim harus berupa 9 digit angka.'];

                continue;
            }

            $angkatan = 2000 + (int) substr($nim, 3, 2);
            $surel = $nim.'@student.upnyk.ac.id';

            $existing = Mahasiswa::where('nim', $nim)->first();
            $values = [
                'nama' => mb_convert_case($nama, MB_CASE_TITLE, 'UTF-8'),
                'nim' => $nim,
                'surel' => $surel,
                'angkatan' => $angkatan,
            ];

            if ($existing) {
                $existing->update($values);
                $updated++;
            } else {
                Mahasiswa::create($values + ['password' => $nim, 'is_asisten' => false, 'is_active_user' => true]);
                $imported++;
            }
        }

        fclose($handle);

        return response()->json([
            'message' => "Import selesai: {$imported} mahasiswa baru, {$updated} diperbarui, ".count($failed).' gagal.',
            'imported' => $imported,
            'updated' => $updated,
            'failed' => $failed,
        ]);
    }
}
