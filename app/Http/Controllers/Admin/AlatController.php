<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusAlatLog;
use App\Enums\TipePic;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlatResource;
use App\Models\Alat;
use App\Services\AlatLogService;
use App\Services\AlatPermissionService;
use App\Services\CrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AlatController extends Controller
{
    /** field alat yang menyentuh identitas/kuota unit → butuh crud_inventaris */
    private const FIELD_BUCKET_CRUD = ['inventaris', 'nama_alat', 'merk', 'tipe', 'serial_number', 'lokasi_penyimpanan', 'jumlah'];

    public function __construct(
        private readonly CrudService $crud,
        private readonly AlatLogService $alatLog,
        private readonly AlatPermissionService $permissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'ketersediaan' => ['nullable', 'in:'.implode(',', Alat::KETERSEDIAAN_STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ]);

        $alat = Alat::query()
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('inventaris', 'ilike', "%{$search}%")
                        ->orWhere('nama_alat', 'ilike', "%{$search}%")
                        ->orWhere('merk', 'ilike', "%{$search}%")
                        ->orWhere('tipe', 'ilike', "%{$search}%")
                        ->orWhere('serial_number', 'ilike', "%{$search}%")
                        ->orWhere('lokasi_penyimpanan', 'ilike', "%{$search}%");
                });
            })
            ->when($validated['ketersediaan'] ?? null, fn ($query, string $ketersediaan) => $query
                ->where('ketersediaan', $ketersediaan))
            ->orderBy('nama_alat', $validated['sort_direction'] ?? 'asc')
            ->paginate(perPage: $validated['per_page'] ?? 15, page: $validated['page'] ?? null);

        return $this->crud->paginated($alat, AlatResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->permissions->can($request, 'crud_inventaris')) {
            return $this->forbidden('crud_inventaris');
        }

        $data = $this->validateAlat($request);

        if (($error = $this->resolveKondisi($data)) !== null) {
            return response()->json(['message' => $error], 422);
        }

        $alat = DB::transaction(function () use ($request, $data): Alat {
            $alat = $this->crud->store(Alat::class, $data);

            $this->alatLog->catat(
                kode: 'inv',
                attributes: [
                    'keperluan' => '-',
                    'nim_pic' => null,
                    'nama_pic' => $request->user()?->nama,
                    'inventaris' => $alat->inventaris,
                    'kondisi' => $alat->kondisi,
                    'status' => StatusAlatLog::Tambah,
                ],
                peminjam: TipePic::Sys,
            );

            return $alat;
        });

        return response()->json(new AlatResource($alat), 201);
    }

    public function update(Request $request, Alat $alat): JsonResponse
    {
        $data = $this->validateAlat($request, $alat);

        if (($error = $this->resolveKondisi($data)) !== null) {
            return response()->json(['message' => $error], 422);
        }

        if (! $this->izinkanPerubahan($request, $alat, $data)) {
            return $this->forbidden($this->permissionYangDibutuhkan($alat, $data));
        }

        $kondisiBerubah = $data['kondisi'] !== $alat->kondisi;

        $alat = DB::transaction(function () use ($request, $alat, $data, $kondisiBerubah): Alat {
            $this->crud->update($alat, $data);

            if ($kondisiBerubah) {
                $this->alatLog->catat(
                    kode: 'inv',
                    attributes: [
                        'keperluan' => '-',
                        'nim_pic' => null,
                        'nama_pic' => $request->user()?->nama,
                        'inventaris' => $alat->inventaris,
                        'kondisi' => $alat->fresh()->kondisi,
                        'status' => StatusAlatLog::Edit,
                    ],
                    peminjam: TipePic::Sys,
                );
            }

            return $alat->fresh();
        });

        return response()->json(new AlatResource($alat));
    }

    public function destroy(Request $request, Alat $alat): JsonResponse
    {
        if (! $this->permissions->can($request, 'crud_inventaris')) {
            return $this->forbidden('crud_inventaris');
        }

        DB::transaction(function () use ($request, $alat): void {
            $this->alatLog->catat(
                kode: 'inv',
                attributes: [
                    'keperluan' => '-',
                    'nim_pic' => null,
                    'nama_pic' => $request->user()?->nama,
                    'inventaris' => $alat->inventaris,
                    'kondisi' => $alat->kondisi,
                    'status' => StatusAlatLog::Hapus,
                ],
                peminjam: TipePic::Sys,
            );

            $alat->delete();
        });

        return response()->json(null, 204);
    }

    private function validateAlat(Request $request, ?Alat $alat = null): array
    {
        return $request->validate([
            'inventaris' => ['required', 'string', 'max:100',
                Rule::unique('alat', 'inventaris')->ignore($alat?->id),
            ],
            'nama_alat' => ['required', 'string', 'max:255'],
            'merk' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'string', 'max:255'],
            'serial_number' => ['required', 'string', 'max:255'],
            'lokasi_penyimpanan' => ['required', 'string', 'max:255'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'kondisi' => ['required', 'array', 'min:1'],
            'kondisi.*.status' => ['required', 'in:'.implode(',', Alat::KONDISI_STATUSES)],
            'kondisi.*.jumlah' => ['required', 'integer', 'min:1'],
            'kondisi.*.catatan' => ['present', 'array'],
            'kondisi.*.catatan.*.komponen' => ['required', 'string', 'max:100'],
            'kondisi.*.catatan.*.keterangan' => ['required', 'string', 'max:255'],
            'ketersediaan' => ['required', 'in:'.implode(',', Alat::KETERSEDIAAN_STATUSES)],
        ]);
    }

    private function resolveKondisi(array &$data): ?string
    {
        $totalKondisi = collect($data['kondisi'])->sum('jumlah');

        if ($totalKondisi !== (int) $data['jumlah']) {
            return 'Total unit kondisi harus sama dengan jumlah alat.';
        }

        return null;
    }

    /**
     * @return string[] permission yang dibutuhkan untuk perubahan ini
     */
    private function permissionYangDibutuhkan(Alat $alat, array $data): array
    {
        $dibutuhkan = [];

        if (($data['ketersediaan'] ?? null) !== $alat->ketersediaan) {
            $dibutuhkan[] = 'edit_status';
        }

        if ($data['kondisi'] !== $alat->kondisi) {
            $dibutuhkan[] = 'edit_kondisi';
        }

        foreach (self::FIELD_BUCKET_CRUD as $field) {
            if ($data[$field] !== $alat->{$field}) {
                $dibutuhkan[] = 'crud_inventaris';
                break;
            }
        }

        return array_values(array_unique($dibutuhkan));
    }

    private function izinkanPerubahan(Request $request, Alat $alat, array $data): bool
    {
        foreach ($this->permissionYangDibutuhkan($alat, $data) as $permission) {
            if (! $this->permissions->can($request, $permission)) {
                return false;
            }
        }

        return true;
    }

    private function forbidden(array|string $permissions): JsonResponse
    {
        $labels = [
            'edit_status' => 'edit status ketersediaan',
            'edit_kondisi' => 'edit catatan kondisi',
            'crud_inventaris' => 'tambah/hapus inventaris',
        ];

        $list = is_string($permissions) ? [$permissions] : $permissions;
        $label = implode(' & ', array_map(fn ($p) => $labels[$p] ?? $p, $list));

        return response()->json([
            'message' => "Token izin Anda tidak mencakup: {$label}.",
        ], 403);
    }
}
