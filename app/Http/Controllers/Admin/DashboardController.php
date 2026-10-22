<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KeperluanPeminjaman;
use App\Http\Controllers\Controller;
use App\Models\Alat;
use App\Models\Peminjaman;
use App\Models\Praktikum;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    private const JADWAL_HARI = 8;

    public function index(): JsonResponse
    {
        $now = Carbon::now('Asia/Jakarta');

        $pendingTotal = Peminjaman::query()->where('status', 'pending')->count();
        $pendingItems = Peminjaman::query()
            ->with('items.alat:id,inventaris,nama_alat')
            ->where('status', 'pending')
            ->latest()
            ->limit(5)
            ->get(['id', 'nim', 'nama', 'keperluan', 'praktikum_slug', 'created_at']);

        $kategoriKeluar = [];
        foreach (KeperluanPeminjaman::cases() as $case) {
            $kategoriKeluar[$case->value] = [
                'keperluan' => $case->value,
                'label' => $case->label(),
                'jumlah' => 0,
            ];
        }

        $peminjamanAktif = Peminjaman::query()
            ->with('items:id,peminjaman_id,jumlah')
            ->where('status', 'disetujui')
            ->whereNull('returned_at')
            ->get(['id', 'keperluan']);

        foreach ($peminjamanAktif as $peminjaman) {
            $kategoriKeluar[$peminjaman->keperluan->value]['jumlah'] += (int) $peminjaman->items->sum('jumlah');
        }

        $tanggalList = collect(range(0, self::JADWAL_HARI - 1))->map(fn (int $i) => [
            'tanggal' => $now->copy()->addDays($i)->toDateString(),
            'hari' => self::HARI[$now->copy()->addDays($i)->dayOfWeekIso - 1],
        ]);

        $praktikumAktif = $this->praktikumDenganJadwal($tanggalList->pluck('hari')->unique());

        $jadwalMendatang = $tanggalList->map(fn (array $tanggal) => [
            'tanggal' => $tanggal['tanggal'],
            'hari' => $tanggal['hari'],
            'sesi' => $this->sesiUntukHari($praktikumAktif, $tanggal['hari']),
        ])->all();

        return response()->json([
            'data' => [
                'pending' => [
                    'total' => $pendingTotal,
                    'items' => $pendingItems->map(fn (Peminjaman $peminjaman) => [
                        'id' => $peminjaman->id,
                        'nim' => $peminjaman->nim,
                        'nama' => $peminjaman->nama,
                        'keperluan' => $peminjaman->keperluan->value,
                        'keperluan_label' => $peminjaman->keperluan->label(),
                        'praktikum_slug' => $peminjaman->praktikum_slug,
                        'created_at' => $peminjaman->created_at?->toIso8601String(),
                        'alat' => $peminjaman->items->map(fn ($item) => [
                            'nama_alat' => $item->alat->nama_alat,
                            'jumlah' => $item->jumlah,
                        ])->all(),
                    ])->all(),
                ],
                'alat_keluar' => [
                    'kategori' => array_values($kategoriKeluar),
                    'maintain' => Alat::query()
                        ->where('ketersediaan', 'perbaikan')
                        ->count(),
                ],
                'jadwal_mendatang' => $jadwalMendatang,
            ],
        ]);
    }

    /**
     * Praktikum aktif beserta jadwalnya yang jatuh pada kumpulan nama hari.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $namaHari
     * @return \Illuminate\Support\Collection<int, Praktikum>
     */
    private function praktikumDenganJadwal(\Illuminate\Support\Collection $namaHari): \Illuminate\Support\Collection
    {
        return Praktikum::query()
            ->where('is_active', true)
            ->whereHas('jadwal', fn ($query) => $query
                ->whereIn('hari', $namaHari)
                ->where('is_active', true))
            ->with(['jadwal' => fn ($query) => $query
                ->whereIn('hari', $namaHari)
                ->where('is_active', true)
                ->orderBy('jam_mulai')])
            ->orderBy('praktikum_label')
            ->get(['id', 'praktikum_label', 'praktikum_slug']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Praktikum>  $praktikum
     * @return array<int, array{praktikum: string, slug: string, plug: string, jam_mulai: ?string, jam_selesai: ?string}>
     */
    private function sesiUntukHari(\Illuminate\Support\Collection $praktikum, string $hari): array
    {
        return $praktikum
            ->flatMap(fn (Praktikum $praktikum) => $praktikum->jadwal
                ->where('hari', $hari)
                ->map(fn ($jadwal) => [
                    'praktikum' => $praktikum->praktikum_label,
                    'slug' => $praktikum->praktikum_slug,
                    'plug' => $jadwal->plug,
                    'jam_mulai' => $jadwal->jam_mulai?->format('H:i'),
                    'jam_selesai' => $jadwal->jam_selesai?->format('H:i'),
                ]))
            ->values()
            ->all();
    }
}
