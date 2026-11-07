<?php

namespace Tests\Feature\Admin;

use App\Models\Alat;
use App\Models\AlatLog;
use App\Models\Dosen;
use App\Models\HargaSewa;
use App\Models\Mahasiswa;
use App\Models\Peminjaman;
use App\Models\Praktikum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mahasiswa_can_be_sorted_ascending_and_descending(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $this->makeMahasiswa(117250001, 'Andi');
        $this->makeMahasiswa(117250002, 'Budi');

        $this->getJson('/api/admin/mahasiswa?status=all&sort_by=nama&sort_direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Andi');

        $this->getJson('/api/admin/mahasiswa?status=all&sort_by=nama&sort_direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Budi');
    }

    public function test_alat_can_be_sorted_by_nama_alat(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $this->makeAlat('INV-002', 'Beta');
        $this->makeAlat('INV-001', 'Alpha');

        $this->getJson('/api/admin/alat?sort_by=nama_alat&sort_direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.nama_alat', 'Beta');
    }

    public function test_alat_log_can_be_sorted(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $alat = $this->makeAlat('INV-001', 'Alpha');

        AlatLog::create([
            'id_log' => 'log-a',
            'keperluan' => '-',
            'inventaris' => $alat->inventaris,
            'kondisi' => [],
            'status' => 'tambah',
            'nama_pic' => 'Andi',
        ]);
        AlatLog::create([
            'id_log' => 'log-b',
            'keperluan' => '-',
            'inventaris' => $alat->inventaris,
            'kondisi' => [],
            'status' => 'tambah',
            'nama_pic' => 'Budi',
        ]);

        $this->getJson('/api/admin/alat-log?sort_by=id_log&sort_direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.id_log', 'log-b');
    }

    public function test_peminjaman_can_be_sorted_by_nama(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $this->makePeminjaman('Andi');
        $this->makePeminjaman('Budi');

        $this->getJson('/api/admin/peminjaman?sort_by=nama&sort_direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Budi');
    }

    public function test_harga_sewa_can_be_sorted_by_tarif(): void
    {
        Sanctum::actingAs($this->makeDosen());

        HargaSewa::create(['nama_alat' => 'Beta', 'tarif_per_hari' => 200000]);
        HargaSewa::create(['nama_alat' => 'Alpha', 'tarif_per_hari' => 100000]);

        $this->getJson('/api/admin/harga-sewa?sort_by=tarif_per_hari&sort_direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.nama_alat', 'Beta');
    }

    public function test_praktikum_can_be_sorted_by_label(): void
    {
        Sanctum::actingAs($this->makeDosen());

        Praktikum::create([
            'praktikum_label' => 'Zeta',
            'praktikum_slug' => 'zeta',
            'semester' => '1',
            'is_active' => true,
        ]);
        Praktikum::create([
            'praktikum_label' => 'Alpha',
            'praktikum_slug' => 'alpha',
            'semester' => '1',
            'is_active' => true,
        ]);

        $this->getJson('/api/admin/praktikum?sort_by=praktikum_label&sort_direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Zeta');
    }

    public function test_mahasiswa_can_be_sorted_by_multiple_columns(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $this->makeMahasiswa(117250001, 'Andi', 2024);
        $this->makeMahasiswa(117250002, 'Budi', 2024);
        $this->makeMahasiswa(117250003, 'Zaki', 2024);
        $this->makeMahasiswa(117250004, 'Cici', 2025);

        $query = http_build_query([
            'status' => 'all',
            'sort' => [
                ['column' => 'angkatan', 'direction' => 'asc'],
                ['column' => 'nama', 'direction' => 'desc'],
            ],
        ]);

        $this->getJson('/api/admin/mahasiswa?'.$query)
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.nama', 'Zaki')
            ->assertJsonPath('data.1.nama', 'Budi')
            ->assertJsonPath('data.2.nama', 'Andi')
            ->assertJsonPath('data.3.nama', 'Cici');
    }

    public function test_duplicate_sort_columns_are_rejected(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $query = http_build_query([
            'sort' => [
                ['column' => 'nama', 'direction' => 'asc'],
                ['column' => 'nama', 'direction' => 'desc'],
            ],
        ]);

        $this->getJson('/api/admin/mahasiswa?'.$query)->assertStatus(422);
    }

    public function test_sort_levels_are_limited_to_five(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $query = http_build_query([
            'sort' => [
                ['column' => 'nim', 'direction' => 'asc'],
                ['column' => 'nama', 'direction' => 'asc'],
                ['column' => 'angkatan', 'direction' => 'asc'],
                ['column' => 'surel', 'direction' => 'asc'],
                ['column' => 'is_active_user', 'direction' => 'asc'],
                ['column' => 'is_active_asisten', 'direction' => 'asc'],
            ],
        ]);

        $this->getJson('/api/admin/mahasiswa?'.$query)->assertStatus(422);
    }

    public function test_unknown_sort_column_is_rejected(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $this->getJson('/api/admin/mahasiswa?sort_by=password')
            ->assertStatus(422);
    }

    private function makeDosen(): Dosen
    {
        return Dosen::create([
            'nama' => 'Dosen Penguji',
            'nip' => '1234567890',
            'surel' => 'dosen@upnyk.ac.id',
            'kepakaran' => [],
            'pengampu' => [],
            'password' => 'rahasia',
            'is_active' => true,
        ]);
    }

    private function makeMahasiswa(int $nim, string $nama, int $angkatan = 2025): Mahasiswa
    {
        return Mahasiswa::create([
            'nama' => $nama,
            'nim' => $nim,
            'surel' => $nim.'@student.upnyk.ac.id',
            'angkatan' => $angkatan,
            'password' => (string) $nim,
            'is_asisten' => false,
            'is_active_asisten' => false,
            'is_active_user' => true,
        ]);
    }

    private function makeAlat(string $inventaris, string $nama): Alat
    {
        return Alat::create([
            'inventaris' => $inventaris,
            'nama_alat' => $nama,
            'merk' => 'Merek',
            'tipe' => 'Tipe',
            'serial_number' => 'SN-'.$inventaris,
            'jumlah' => 1,
            'kondisi' => [],
            'lokasi_penyimpanan' => 'Lab',
            'ketersediaan' => 'tersedia',
        ]);
    }

    private function makePeminjaman(string $nama): Peminjaman
    {
        return Peminjaman::create([
            'nama' => $nama,
            'keperluan' => 'belajar_mandiri',
            'status' => 'pending',
        ]);
    }
}
