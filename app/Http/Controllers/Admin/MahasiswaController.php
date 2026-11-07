<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MahasiswaImportResource;
use App\Http\Resources\MahasiswaResource;
use App\Jobs\ImportMahasiswaCsv;
use App\Models\Mahasiswa;
use App\Models\MahasiswaImport;
use App\Services\CrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MahasiswaController extends Controller
{
    private const SORTABLE = ['nim', 'nama', 'angkatan', 'surel', 'is_active_user', 'is_active_asisten'];

    public function __construct(private readonly CrudService $crud) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'angkatan' => ['nullable', 'integer'],
            'role' => ['nullable', 'in:asisten,asisten_all,mahasiswa,asisten_nonaktif'],
            'status' => ['nullable', 'in:active,inactive,all'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ...$this->sortRules(self::SORTABLE),
        ]);

        $status = $validated['status'] ?? 'active';

        $mahasiswa = $this->applySort(
            Mahasiswa::query()
                ->when($validated['search'] ?? null, function ($query, string $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('nama', $this->likeOperator(), "%{$search}%")
                            ->orWhere('nim', $this->likeOperator(), "%{$search}%");
                    });
                })
                ->when($status === 'active', fn ($query) => $query->where('is_active_user', true))
                ->when($status === 'inactive', fn ($query) => $query->where('is_active_user', false))
                ->when($validated['angkatan'] ?? null, fn ($query, int $angkatan) => $query->where('angkatan', $angkatan))
                ->when(($validated['role'] ?? null) === 'asisten', fn ($query) => $query
                    ->where('is_asisten', true)
                    ->where('is_active_asisten', true))
                ->when(($validated['role'] ?? null) === 'asisten_nonaktif', fn ($query) => $query
                    ->where('is_asisten', true)
                    ->where('is_active_asisten', false))
                ->when(($validated['role'] ?? null) === 'asisten_all', fn ($query) => $query
                    ->where('is_asisten', true))
                ->when(($validated['role'] ?? null) === 'mahasiswa', fn ($query) => $query
                    ->where(function ($query) {
                        $query->where('is_asisten', false)->orWhere('is_active_asisten', false);
                    })),
            $validated,
            self::SORTABLE,
            'nim',
            'asc',
            ['nim'],
        )->paginate(perPage: $validated['per_page'] ?? 50, page: $validated['page'] ?? null);

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

    public function updateStatus(Request $request, Mahasiswa $mahasiswa): JsonResponse
    {
        $data = $request->validate([
            'is_active_user' => ['required', 'boolean'],
        ]);

        $mahasiswa->update(['is_active_user' => $data['is_active_user']]);

        if (! $data['is_active_user']) {
            $mahasiswa->tokens()->delete();
        }

        return response()->json(new MahasiswaResource($mahasiswa->fresh()));
    }

    public function resetPassword(Mahasiswa $mahasiswa): JsonResponse
    {
        $mahasiswa->update(['password' => (string) $mahasiswa->nim]);
        $mahasiswa->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil direset ke NIM. Minta mahasiswa login lalu ganti password.',
        ]);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $file = $request->file('file');

        if (($headerError = $this->csvHeaderError($file->getRealPath())) !== null) {
            return response()->json(['message' => $headerError], 422);
        }

        $path = $file->storeAs('imports', Str::uuid()->toString().'.csv', 'local');

        $import = MahasiswaImport::create([
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => 'queued',
            'total_rows' => 0,
            'processed_rows' => 0,
        ]);

        $import->refresh();

        ImportMahasiswaCsv::dispatch($import->id);

        return response()->json(new MahasiswaImportResource($import), 202);
    }

    public function importStatus(MahasiswaImport $import): JsonResponse
    {
        return response()->json(new MahasiswaImportResource($import));
    }

    public function latestImport(): JsonResponse
    {
        $import = MahasiswaImport::query()->latest('id')->first();

        return response()->json($import !== null ? new MahasiswaImportResource($import) : null);
    }

    private function csvHeaderError(string $path): ?string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return 'File tidak dapat dibaca.';
        }

        $header = fgetcsv($handle);
        fclose($handle);

        if ($header === false) {
            return 'File CSV kosong.';
        }

        $header = array_map(fn ($column): string => strtolower(trim((string) $column)), $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $missingColumns = array_diff(['nama', 'nim'], $header);

        if ($missingColumns !== []) {
            return 'Kolom wajib tidak ditemukan: '.implode(', ', $missingColumns).'.';
        }

        return null;
    }
}
