<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PraktikumResource;
use App\Models\Mahasiswa;
use App\Models\Praktikum;
use App\Models\PraktikumNilai;
use App\Services\CrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PraktikumController extends Controller
{
    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    public function __construct(private readonly CrudService $crud)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $praktikum = Praktikum::query()
            ->with('jadwal')
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('praktikum_label', 'like', "%{$search}%")
                        ->orWhere('praktikum_slug', 'like', "%{$search}%");
                });
            })
            ->when($request->user()->role === 'asisten', fn ($query) => $query
                ->whereIn('praktikum_slug', $request->user()->pengampu_praktikum ?? []))
            ->orderBy('praktikum_label')
            ->paginate(perPage: $validated['per_page'] ?? 50, page: $validated['page'] ?? null);

        $counts = $this->mahasiswaCounts();

        foreach ($praktikum->getCollection() as $item) {
            foreach ($item->jadwal as $jadwal) {
                $jadwal->mahasiswa_count = $counts[$item->praktikum_slug.'|'.$jadwal->plug] ?? 0;
            }
        }

        return $this->crud->paginated($praktikum, PraktikumResource::class);
    }

    public function show(Praktikum $praktikum): JsonResponse
    {
        $praktikum->load('jadwal');

        $counts = $this->mahasiswaCounts();

        foreach ($praktikum->jadwal as $jadwal) {
            $jadwal->mahasiswa_count = $counts[$praktikum->praktikum_slug.'|'.$jadwal->plug] ?? 0;
        }

        return response()->json(new PraktikumResource($praktikum));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'praktikum_label' => ['required', 'string', 'max:255'],
            'semester' => ['required', 'string', 'max:255'],
            'praktikum_slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:praktikum_nama,praktikum_slug'],
            'is_active' => ['boolean'],
            ...$this->plugsRules(),
        ]);

        $praktikum = DB::transaction(function () use ($data) {
            $praktikum = $this->crud->store(Praktikum::class, collect($data)->except('plugs')->all() + ['is_active' => true]);
            $this->syncPlugs($praktikum, $data['plugs']);

            return $praktikum;
        });

        $praktikum->setRelation('jadwal', $praktikum->jadwal()->get());

        return response()->json(new PraktikumResource($praktikum), 201);
    }

    public function update(Request $request, Praktikum $praktikum): JsonResponse
    {
        $data = $request->validate([
            'praktikum_label' => ['sometimes', 'string', 'max:255'],
            'semester' => ['sometimes', 'string', 'max:255'],
            'praktikum_slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', Rule::unique('praktikum_nama', 'praktikum_slug')->ignore($praktikum)],
            'is_active' => ['boolean'],
            ...$this->plugsRules(),
        ]);

        DB::transaction(function () use ($praktikum, $data) {
            $this->crud->update($praktikum, collect($data)->except('plugs')->all());
            $this->syncPlugs($praktikum, $data['plugs']);
        });

        $praktikum->setRelation('jadwal', $praktikum->jadwal()->get());

        return response()->json(new PraktikumResource($praktikum));
    }

    public function destroy(Praktikum $praktikum): JsonResponse
    {
        $this->crud->destroy($praktikum);

        return response()->json(['message' => 'Praktikum dihapus.']);
    }

    private function plugsRules(): array
    {
        return [
            'plugs' => ['present', 'array'],
            'plugs.*.plug' => ['required', 'string', 'max:50'],
            'plugs.*.hari' => ['required', Rule::in(self::HARI)],
            'plugs.*.jam_mulai' => ['required', 'date_format:H:i'],
            'plugs.*.jam_selesai' => ['required', 'date_format:H:i', 'after:plugs.*.jam_mulai'],
        ];
    }

    private function syncPlugs(Praktikum $praktikum, array $plugs): void
    {
        $praktikum->jadwal()->delete();

        foreach ($plugs as $plug) {
            $praktikum->jadwal()->create($plug + ['is_active' => true]);
        }
    }

    public function listMahasiswa(Request $request, Praktikum $praktikum, string $plug): JsonResponse
    {
        $slug = $praktikum->praktikum_slug;
        $plug = urldecode($plug);

        if (! $praktikum->jadwal()->where('plug', $plug)->exists()) {
            return response()->json([
                'message' => "Jadwal untuk plug {$plug} pada praktikum {$slug} tidak ditemukan.",
            ], 422);
        }

        $pertemuan = $praktikum->pertemuan()->get(['id', 'parameter']);
        $nilaiRows = PraktikumNilai::query()
            ->whereIn('pertemuan_id', $pertemuan->pluck('id'))
            ->get()
            ->groupBy('mahasiswa_id');

        $data = Mahasiswa::query()
            ->orderBy('nim')
            ->get()
            ->map(fn (Mahasiswa $mahasiswa) => [
                'id' => $mahasiswa->id,
                'nama' => $mahasiswa->nama,
                'nim' => $mahasiswa->nim,
                'assigned' => (($mahasiswa->praktikum_plug ?? [])[$slug] ?? null) === $plug,
                'kelompok' => ($mahasiswa->praktikum_kelompok ?? [])[$slug] ?? null,
                'skor' => $this->skorPerPertemuan($pertemuan, $nilaiRows->get($mahasiswa->id)),
            ]);

        if ($request->boolean('assigned')) {
            $data = $data->filter(fn (array $mahasiswa) => $mahasiswa['assigned'])->values();
        }

        return response()->json(['data' => $data]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\PraktikumPertemuan>  $pertemuan
     * @param  \Illuminate\Support\Collection<int, PraktikumNilai>|null  $nilaiRows
     * @return array<string, float>
     */
    private function skorPerPertemuan($pertemuan, $nilaiRows): array
    {
        if ($nilaiRows === null) {
            return [];
        }

        return $nilaiRows
            ->mapWithKeys(function (PraktikumNilai $row) use ($pertemuan) {
                $parameter = $pertemuan->firstWhere('id', $row->pertemuan_id)?->parameter ?? [];

                return [(string) $row->pertemuan_id => $this->hitungSkor($row->nilai, $parameter)];
            })
            ->filter()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $nilai
     * @param  array<int, array{nama: string, bobot: int}>  $parameter
     */
    private function hitungSkor(?array $nilai, array $parameter): ?float
    {
        if ($nilai === null) {
            return null;
        }

        $skor = 0.0;
        foreach ($parameter as $param) {
            $value = $nilai[$param['nama']] ?? null;
            if (! is_numeric($value)) {
                return null;
            }
            $skor += $value * $param['bobot'] / 100;
        }

        return round($skor, 2);
    }

    public function syncMahasiswa(Request $request, Praktikum $praktikum, string $plug): JsonResponse
    {
        $plug = urldecode($plug);

        if (! $praktikum->jadwal()->where('plug', $plug)->exists()) {
            return response()->json([
                'message' => "Jadwal untuk plug {$plug} pada praktikum {$praktikum->praktikum_slug} tidak ditemukan.",
            ], 422);
        }

        $data = $request->validate([
            'mahasiswa_ids' => ['present', 'array'],
            'mahasiswa_ids.*' => ['integer', Rule::exists('user_mahasiswa', 'id')],
        ]);

        $selectedIds = array_fill_keys($data['mahasiswa_ids'], true);
        $slug = $praktikum->praktikum_slug;

        $kandidat = Mahasiswa::query()
            ->where(function ($query) use ($slug, $data) {
                $query->whereJsonContains('praktikum', $slug)
                    ->orWhereIn('id', $data['mahasiswa_ids']);
            })
            ->get();

        DB::transaction(function () use ($kandidat, $selectedIds, $slug, $plug) {
            foreach ($kandidat as $mahasiswa) {
                $praktikumList = $mahasiswa->praktikum ?? [];
                $plugMap = $mahasiswa->praktikum_plug ?? [];
                $kelompokMap = $mahasiswa->praktikum_kelompok ?? [];
                $isPlugged = ($plugMap[$slug] ?? null) === $plug;
                $isSelected = isset($selectedIds[$mahasiswa->id]);

                if ($isSelected) {
                    if (! in_array($slug, $praktikumList, true)) {
                        $praktikumList[] = $slug;
                    }
                    $plugMap[$slug] = $plug;
                } elseif ($isPlugged) {
                    $praktikumList = array_values(array_diff($praktikumList, [$slug]));
                    unset($plugMap[$slug], $kelompokMap[$slug]);
                } else {
                    continue;
                }

                $mahasiswa->update([
                    'praktikum' => $praktikumList,
                    'praktikum_plug' => $plugMap,
                    'praktikum_kelompok' => $kelompokMap,
                ]);
            }
        });

        return response()->json(['message' => "Peserta plug {$plug} diperbarui."]);
    }

    public function updateKelompok(Request $request, Praktikum $praktikum, Mahasiswa $mahasiswa): JsonResponse
    {
        $slug = $praktikum->praktikum_slug;

        if (! in_array($slug, $mahasiswa->praktikum ?? [], true)) {
            return response()->json([
                'message' => "Mahasiswa tidak terdaftar pada praktikum {$slug}.",
            ], 422);
        }

        $user = $request->user();

        if ($user->role === 'asisten') {
            $plugMahasiswa = ($mahasiswa->praktikum_plug ?? [])[$slug] ?? null;

            if ($plugMahasiswa === null || ! in_array($plugMahasiswa, $user->pengampu_plug[$slug] ?? [], true)) {
                return response()->json([
                    'message' => 'Anda hanya dapat mengatur kelompok pada plug yang Anda ampu.',
                ], 403);
            }
        }

        $data = $request->validate([
            'kelompok' => ['nullable', 'string', 'max:50'],
        ]);

        $kelompokMap = $mahasiswa->praktikum_kelompok ?? [];

        if (($data['kelompok'] ?? null) === null || trim($data['kelompok']) === '') {
            unset($kelompokMap[$slug]);
        } else {
            $kelompokMap[$slug] = trim($data['kelompok']);
        }

        $mahasiswa->update(['praktikum_kelompok' => $kelompokMap]);

        return response()->json([
            'kelompok' => $kelompokMap[$slug] ?? null,
            'message' => 'Kelompok diperbarui.',
        ]);
    }

    /**
     * @return array<string, int> map "slug|plug" => jumlah mahasiswa
     */
    private function mahasiswaCounts(): array
    {
        $counts = [];

        Mahasiswa::query()->get(['praktikum', 'praktikum_plug'])->each(function (Mahasiswa $mahasiswa) use (&$counts) {
            foreach (($mahasiswa->praktikum_plug ?? []) as $slug => $plug) {
                if (is_string($plug)) {
                    $counts["{$slug}|{$plug}"] = ($counts["{$slug}|{$plug}"] ?? 0) + 1;
                }
            }
        });

        return $counts;
    }
}
