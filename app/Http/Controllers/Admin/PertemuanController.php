<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\Praktikum;
use App\Models\PraktikumNilai;
use App\Models\PraktikumPertemuan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PertemuanController extends Controller
{
    public function index(Praktikum $praktikum): JsonResponse
    {
        $data = $praktikum->pertemuan()->orderBy('nomor')->get();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request, Praktikum $praktikum): JsonResponse
    {
        $data = $this->validatePertemuan($request, $praktikum);

        $pertemuan = $praktikum->pertemuan()->create($data);

        return response()->json($pertemuan, 201);
    }

    public function update(Request $request, Praktikum $praktikum, PraktikumPertemuan $pertemuan): JsonResponse
    {
        if ($pertemuan->praktikum_id !== $praktikum->id) {
            return response()->json(['message' => 'Pertemuan tidak ditemukan pada praktikum ini.'], 404);
        }

        $data = $this->validatePertemuan($request, $praktikum, $pertemuan);

        $pertemuan->update($data);

        return response()->json($pertemuan);
    }

    public function destroy(Praktikum $praktikum, PraktikumPertemuan $pertemuan): JsonResponse
    {
        if ($pertemuan->praktikum_id !== $praktikum->id) {
            return response()->json(['message' => 'Pertemuan tidak ditemukan pada praktikum ini.'], 404);
        }

        $pertemuan->delete();

        return response()->json(['message' => 'Pertemuan dihapus.']);
    }

    public function nilaiPlug(Praktikum $praktikum, string $plug): JsonResponse
    {
        [$slug, $plug] = $this->resolvePlug($praktikum, $plug);

        $pertemuanIds = $praktikum->pertemuan()->pluck('id');

        $mahasiswaIds = Mahasiswa::query()
            ->orderBy('nim')
            ->get()
            ->filter(fn (Mahasiswa $m) => (($m->praktikum_plug ?? [])[$slug] ?? null) === $plug)
            ->pluck('id');

        $nilaiRows = PraktikumNilai::query()
            ->whereIn('pertemuan_id', $pertemuanIds)
            ->whereIn('mahasiswa_id', $mahasiswaIds)
            ->get();

        $data = $mahasiswaIds->map(fn (int $id) => [
            'mahasiswa_id' => $id,
            'nilai' => $nilaiRows
                ->where('mahasiswa_id', $id)
                ->mapWithKeys(fn (PraktikumNilai $row) => [(string) $row->pertemuan_id => $row->nilai])
                ->all(),
        ])->values();

        return response()->json(['data' => $data]);
    }

    public function saveNilai(Request $request, Praktikum $praktikum, string $plug, PraktikumPertemuan $pertemuan): JsonResponse
    {
        if ($pertemuan->praktikum_id !== $praktikum->id) {
            return response()->json(['message' => 'Pertemuan tidak ditemukan pada praktikum ini.'], 404);
        }

        [, $plug] = $this->resolvePlug($praktikum, $plug);

        $data = $request->validate([
            'nilai' => ['present', 'array'],
        ]);

        $parameterNama = collect($pertemuan->parameter)->pluck('nama')->all();
        $errors = [];
        $parsed = [];

        foreach ($data['nilai'] as $mahasiswaId => $values) {
            if (! is_numeric($mahasiswaId) || ! Mahasiswa::whereKey($mahasiswaId)->exists()) {
                $errors[] = "Mahasiswa {$mahasiswaId} tidak ditemukan.";
                continue;
            }

            if (! is_array($values)) {
                $errors[] = "Nilai mahasiswa {$mahasiswaId} tidak valid.";
                continue;
            }

            $clean = [];
            foreach ($values as $nama => $value) {
                if (! in_array($nama, $parameterNama, true)) {
                    $errors[] = "Parameter '{$nama}' tidak ada pada pertemuan {$pertemuan->nomor}.";
                    continue;
                }
                if ($value !== null && (! is_numeric($value) || $value < 0 || $value > 100)) {
                    $errors[] = "Nilai '{$nama}' mahasiswa {$mahasiswaId} harus angka 0-100.";
                    continue;
                }
                if ($value !== null && $value !== '') {
                    $clean[$nama] = (float) $value;
                }
            }
            $parsed[(int) $mahasiswaId] = $clean;
        }

        if ($errors !== []) {
            return response()->json(['message' => implode(' ', array_unique($errors))], 422);
        }

        DB::transaction(function () use ($parsed, $pertemuan) {
            foreach ($parsed as $mahasiswaId => $values) {
                if ($values === []) {
                    PraktikumNilai::query()
                        ->where('pertemuan_id', $pertemuan->id)
                        ->where('mahasiswa_id', $mahasiswaId)
                        ->delete();
                    continue;
                }

                PraktikumNilai::updateOrCreate(
                    ['pertemuan_id' => $pertemuan->id, 'mahasiswa_id' => $mahasiswaId],
                    ['nilai' => $values],
                );
            }
        });

        return response()->json(['message' => "Nilai pertemuan {$pertemuan->nomor} plug {$plug} tersimpan."]);
    }

    private function resolvePlug(Praktikum $praktikum, string $plug): array
    {
        $slug = $praktikum->praktikum_slug;
        $plug = urldecode($plug);

        if (! $praktikum->jadwal()->where('plug', $plug)->exists()) {
            abort(response()->json([
                'message' => "Jadwal untuk plug {$plug} pada praktikum {$slug} tidak ditemukan.",
            ], 422));
        }

        return [$slug, $plug];
    }

    private function validatePertemuan(Request $request, Praktikum $praktikum, ?PraktikumPertemuan $pertemuan = null): array
    {
        $data = $request->validate([
            'nomor' => [
                'required', 'integer', 'min:1', 'max:255',
                Rule::unique('praktikum_pertemuan', 'nomor')
                    ->where('praktikum_id', $praktikum->id)
                    ->ignore($pertemuan),
            ],
            'materi_id' => [
                'required', 'integer',
                Rule::exists('praktikum_kurikulum', 'id')->where('praktikum_id', $praktikum->id),
            ],
            'tanggal' => ['nullable', 'date'],
            'bobot' => ['required', 'integer', 'min:1', 'max:100'],
            'parameter' => ['required', 'array', 'min:1'],
            'parameter.*.nama' => ['required', 'string', 'max:50', 'distinct'],
            'parameter.*.bobot' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $totalBobot = collect($data['parameter'])->sum('bobot');
        if ($totalBobot !== 100) {
            throw ValidationException::withMessages([
                'parameter' => "Total bobot parameter harus 100, sekarang {$totalBobot}.",
            ]);
        }

        return [
            'nomor' => $data['nomor'],
            'materi_id' => $data['materi_id'],
            'tanggal' => $data['tanggal'] ?? null,
            'bobot' => $data['bobot'],
            'parameter' => $data['parameter'],
        ];
    }
}
