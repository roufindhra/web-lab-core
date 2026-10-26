<?php

namespace App\Http\Controllers\Client;

use App\Enums\KeperluanPeminjaman;
use App\Http\Controllers\Controller;
use App\Http\Resources\PeminjamanResource;
use App\Models\Alat;
use App\Models\HargaSewa;
use App\Services\PeminjamanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SewaController extends Controller
{
    public function __construct(
        private readonly PeminjamanService $peminjaman,
    ) {}

    public function options(): JsonResponse
    {
        $harga = HargaSewa::query()->pluck('tarif_per_hari', 'nama_alat');

        $katalog = Alat::query()
            ->orderBy('nama_alat')
            ->orderBy('inventaris')
            ->get()
            ->groupBy('nama_alat')
            ->values()
            ->filter(fn ($group) => $harga->has($group->first()->nama_alat))
            ->map(fn ($group) => [
                'nama_alat' => $group->first()->nama_alat,
                'jumlah_unit' => (int) $group->sum('jumlah'),
                'jumlah_tersedia' => (int) $group->where('ketersediaan', 'tersedia')->sum('jumlah'),
                'tarif_per_hari' => (float) $harga->get($group->first()->nama_alat),
            ])
            ->values();

        return response()->json(['data' => $katalog]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'string', 'max:20'],
            'institusi' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:100'],
            'tanggal_pinjam' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_kembali' => ['required', 'date', 'after_or_equal:tanggal_pinjam'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_alat' => ['required', 'string', 'exists:harga_sewa,nama_alat'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
        ]);

        $items = $this->alokasiUnit($validated['items']);

        $peminjaman = $this->peminjaman->ajukan([
            'nim' => null,
            'nama' => $validated['nama'],
            'keperluan' => KeperluanPeminjaman::PenyewaanKomersil,
            'praktikum_slug' => null,
            'no_hp' => $validated['no_hp'],
            'institusi' => $validated['institusi'] ?? null,
            'email' => $validated['email'] ?? null,
            'tanggal_pinjam' => $validated['tanggal_pinjam'],
            'tanggal_kembali' => $validated['tanggal_kembali'],
        ], $items);

        return response()->json([
            'data' => new PeminjamanResource($peminjaman->load('items.alat')),
            'total_estimasi' => $this->totalEstimasi(
                $validated['items'],
                $validated['tanggal_pinjam'],
                $validated['tanggal_kembali'],
            ),
        ], 201);
    }

    /**
     * Terjemahkan permintaan level grup (nama_alat + jumlah) menjadi baris
     * peminjaman_alat per unit alat, sesuai kapasitas tiap baris.
     *
     * @param  array<int, array{nama_alat: string, jumlah: int}>  $items
     * @return array<int, array{alat_id: int, jumlah: int}>
     */
    private function alokasiUnit(array $items): array
    {
        $errors = [];
        $allocated = [];

        foreach ($items as $index => $item) {
            $sisa = (int) $item['jumlah'];

            foreach (Alat::query()->where('nama_alat', $item['nama_alat'])->orderBy('inventaris')->get() as $alat) {
                if ($sisa <= 0) {
                    break;
                }

                $ambil = min($alat->jumlah, $sisa);
                $allocated[] = ['alat_id' => $alat->id, 'jumlah' => $ambil];
                $sisa -= $ambil;
            }

            if ($sisa > 0) {
                $kapasitas = (int) Alat::query()->where('nama_alat', $item['nama_alat'])->sum('jumlah');
                $errors["items.{$index}.jumlah"] = ["Stok {$item['nama_alat']} tidak cukup (tersedia {$kapasitas})."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $allocated;
    }

    /**
     * @param  array<int, array{nama_alat: string, jumlah: int}>  $items
     */
    private function totalEstimasi(array $items, string $tanggalPinjam, string $tanggalKembali): float
    {
        $hari = max(1, (int) Carbon::parse($tanggalPinjam)->startOfDay()->diffInDays(Carbon::parse($tanggalKembali)->startOfDay()) + 1);
        $tarif = HargaSewa::query()->pluck('tarif_per_hari', 'nama_alat');

        return round(collect($items)->sum(
            fn (array $item) => $item['jumlah'] * $hari * (float) $tarif->get($item['nama_alat'], 0),
        ), 2);
    }
}
