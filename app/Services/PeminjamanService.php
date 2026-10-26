<?php

namespace App\Services;

use App\Enums\KeperluanPeminjaman;
use App\Enums\StatusAlatLog;
use App\Enums\TipePic;
use App\Models\Alat;
use App\Models\Peminjaman;
use App\Models\PeminjamanAlat;
use App\Models\Praktikum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeminjamanService
{
    public function __construct(
        private readonly AlatLogService $alatLog,
    ) {}

    /**
     * @param  array{nim: ?int, nama: string, keperluan: KeperluanPeminjaman, praktikum_slug: ?string, no_hp?: ?string, institusi?: ?string, email?: ?string, tanggal_pinjam?: ?string, tanggal_kembali?: ?string}  $data
     * @param  array<int, array{alat_id: int, jumlah: int}>  $items
     */
    public function ajukan(array $data, array $items): Peminjaman
    {
        return DB::transaction(function () use ($data, $items): Peminjaman {
            $keperluan = $data['keperluan'];
            $praktikumSlug = $data['praktikum_slug'] ?? null;
            $nim = $data['nim'] ?? null;

            $alatList = Alat::query()->findOrFail(collect($items)->pluck('alat_id'));

            $peminjaman = Peminjaman::create($data + ['status' => 'pending']);

            foreach ($items as $item) {
                $alat = $alatList->firstOrFail(fn (Alat $alat) => $alat->id === (int) $item['alat_id']);

                $peminjaman->items()->create([
                    'alat_id' => $alat->id,
                    'inventaris' => $alat->inventaris,
                    'jumlah' => $item['jumlah'],
                ]);

                $this->alatLog->catat(
                    kode: $this->kodeEvent($keperluan, $praktikumSlug),
                    attributes: [
                        'keperluan' => $keperluan->keterangan(),
                        'nim_pic' => $nim,
                        'nama_pic' => $data['nama'],
                        'inventaris' => $alat->inventaris,
                        'kondisi' => $alat->kondisi,
                        'status' => StatusAlatLog::Pengajuan,
                    ],
                    peminjam: TipePic::Mhs,
                );
            }

            return $peminjaman;
        });
    }

    public function setujui(Peminjaman $peminjaman, string $approvedBy): Peminjaman
    {
        return DB::transaction(function () use ($peminjaman, $approvedBy): Peminjaman {
            $items = $peminjaman->items()->with('alat')->get();

            $stokErrors = [];
            /** @var array<int, array{alat: Alat, jumlah: int, keluar_lain: int}> $approved */
            $approved = [];

            foreach ($items as $item) {
                $alat = Alat::query()->whereKey($item->alat_id)->lockForUpdate()->firstOrFail();

                $keluarLain = $this->unitKeluar($alat->id, $peminjaman->id);
                $tersedia = $alat->jumlah - $keluarLain;
                $sisa = $tersedia - $item->jumlah;

                if ($sisa < 0) {
                    $stokErrors["items.{$item->id}"] = "Stok {$alat->nama_alat} tidak cukup (sisa {$tersedia}, diminta {$item->jumlah}).";

                    continue;
                }

                $approved[] = ['alat' => $alat, 'jumlah' => $item->jumlah, 'keluar_lain' => $keluarLain];
            }

            if ($stokErrors !== []) {
                throw ValidationException::withMessages($stokErrors);
            }

            $kodeEvent = $this->kodeEvent($peminjaman->keperluan, $peminjaman->praktikum_slug);

            foreach ($approved as $entry) {
                $this->alatLog->catat(
                    kode: $kodeEvent,
                    attributes: [
                        'keperluan' => $peminjaman->keperluan->keterangan(),
                        'nim_pic' => $peminjaman->nim,
                        'nama_pic' => $peminjaman->nama,
                        'inventaris' => $entry['alat']->inventaris,
                        'kondisi' => $entry['alat']->kondisi,
                        'status' => StatusAlatLog::Keluar,
                    ],
                    peminjam: TipePic::Mhs,
                );

                $this->sinkronKetersediaan($entry['alat'], $entry['keluar_lain'] + $entry['jumlah']);
            }

            $peminjaman->update([
                'status' => 'disetujui',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            return $peminjaman;
        });
    }

    /**
     * Ganti daftar alat yang diminta saat pengajuan masih pending (revisi laboran).
     *
     * @param  array<int, array{alat_id: int, jumlah: int}>  $items
     */
    public function revisi(Peminjaman $peminjaman, array $items): Peminjaman
    {
        return DB::transaction(function () use ($peminjaman, $items): Peminjaman {
            $alatList = Alat::query()->findOrFail(collect($items)->pluck('alat_id'));

            $kodeEvent = $this->kodeEvent($peminjaman->keperluan, $peminjaman->praktikum_slug);

            $peminjaman->items()->delete();

            foreach ($items as $item) {
                $alat = $alatList->firstOrFail(fn (Alat $alat) => $alat->id === (int) $item['alat_id']);

                $peminjaman->items()->create([
                    'alat_id' => $alat->id,
                    'inventaris' => $alat->inventaris,
                    'jumlah' => $item['jumlah'],
                ]);

                $this->alatLog->catat(
                    kode: $kodeEvent,
                    attributes: [
                        'keperluan' => $peminjaman->keperluan->keterangan(),
                        'nim_pic' => $peminjaman->nim,
                        'nama_pic' => $peminjaman->nama,
                        'inventaris' => $alat->inventaris,
                        'kondisi' => $alat->kondisi,
                        'status' => StatusAlatLog::Edit,
                    ],
                    peminjam: TipePic::Mhs,
                );
            }

            return $peminjaman;
        });
    }

    public function tolak(Peminjaman $peminjaman, string $catatan): Peminjaman
    {
        $peminjaman->update([
            'status' => 'ditolak',
            'catatan' => $catatan,
        ]);

        return $peminjaman;
    }

    /**
     * @param  array<int, array{peminjaman_alat_id: int, kondisi: array, ketersediaan: string}>  $checkedItems
     */
    public function kembalikan(Peminjaman $peminjaman, array $checkedItems): Peminjaman
    {
        return DB::transaction(function () use ($peminjaman, $checkedItems): Peminjaman {
            $items = collect($checkedItems)->keyBy('peminjaman_alat_id');

            foreach ($peminjaman->items()->with('alat')->get() as $pivot) {
                $alat = Alat::query()->whereKey($pivot->alat_id)->lockForUpdate()->firstOrFail();

                $checked = $items[$pivot->id];
                $alat->update([
                    'kondisi' => $checked['kondisi'],
                    'ketersediaan' => $checked['ketersediaan'],
                ]);

                $this->alatLog->catat(
                    kode: $this->kodeEvent($peminjaman->keperluan, $peminjaman->praktikum_slug),
                    attributes: [
                        'keperluan' => $peminjaman->keperluan->keterangan(),
                        'nim_pic' => $peminjaman->nim,
                        'nama_pic' => $peminjaman->nama,
                        'inventaris' => $alat->inventaris,
                        'kondisi' => $alat->kondisi,
                        'status' => StatusAlatLog::Masuk,
                    ],
                    peminjam: TipePic::Mhs,
                );
            }

            $peminjaman->update(['returned_at' => now()]);

            return $peminjaman;
        });
    }

    /**
     * Kode keperluan event untuk segment pertama id_log alat_log.
     * Untuk keperluan praktikum, kode disertai id praktikum (mis. 'prk:3').
     */
    private function kodeEvent(KeperluanPeminjaman $keperluan, ?string $praktikumSlug): string
    {
        $kode = $keperluan->kode();

        if ($keperluan === KeperluanPeminjaman::Praktikum) {
            $praktikumId = Praktikum::query()
                ->where('praktikum_slug', $praktikumSlug)
                ->value('id');

            $kode = "{$kode}:{$praktikumId}";
        }

        return $kode;
    }

    /**
     * Total unit alat yang sedang dipinjam lewat peminjaman lain yang masih aktif.
     */
    public function unitKeluar(int $alatId, int $kecualiPeminjamanId = 0): int
    {
        return (int) PeminjamanAlat::query()
            ->where('alat_id', $alatId)
            ->where('peminjaman_id', '!=', $kecualiPeminjamanId)
            ->whereHas('peminjaman', fn ($query) => $query
                ->where('status', 'disetujui')
                ->whereNull('returned_at'))
            ->sum('jumlah');
    }

    private function sinkronKetersediaan(Alat $alat, int $keluar): void
    {
        if ($keluar >= $alat->jumlah && $alat->ketersediaan !== 'dipinjam') {
            $alat->update(['ketersediaan' => 'dipinjam']);
        } elseif ($keluar < $alat->jumlah && $alat->ketersediaan === 'dipinjam') {
            $alat->update(['ketersediaan' => 'tersedia']);
        }
    }
}
