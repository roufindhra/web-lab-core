<?php

namespace Tests\Feature\Admin;

use App\Models\Alat;
use App\Models\AlatLog;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Praktikum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_mahasiswa_search_is_case_insensitive(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $this->makeMahasiswa(117250001, 'Budi Santoso');
        $this->makeMahasiswa(117250002, 'Andi Wijaya');

        $this->getJson('/api/admin/mahasiswa?status=all&search=bUdI')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nama', 'Budi Santoso');
    }

    public function test_mahasiswa_search_by_nim_is_case_insensitive(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $this->makeMahasiswa(117250001, 'Budi Santoso');
        $this->makeMahasiswa(117250002, 'Andi Wijaya');

        $this->getJson('/api/admin/mahasiswa?status=all&search=117250001')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nim', 117250001);
    }

    public function test_praktikum_search_is_case_insensitive(): void
    {
        Sanctum::actingAs($this->makeDosen());

        Praktikum::create([
            'praktikum_label' => 'Alpha',
            'praktikum_slug' => 'alpha',
            'semester' => '1',
            'is_active' => true,
        ]);
        Praktikum::create([
            'praktikum_label' => 'Beta',
            'praktikum_slug' => 'beta',
            'semester' => '1',
            'is_active' => true,
        ]);

        $this->getJson('/api/admin/praktikum?search=ALPHA')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.label', 'Alpha');
    }

    public function test_alat_log_search_is_case_insensitive(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $alat = $this->makeAlat('INV-001', 'Total Station');

        AlatLog::create([
            'id_log' => 'log-a',
            'keperluan' => '-',
            'inventaris' => $alat->inventaris,
            'kondisi' => [],
            'status' => 'tambah',
            'nama_pic' => 'Budi Santoso',
        ]);
        AlatLog::create([
            'id_log' => 'log-b',
            'keperluan' => '-',
            'inventaris' => $alat->inventaris,
            'kondisi' => [],
            'status' => 'tambah',
            'nama_pic' => 'Andi Wijaya',
        ]);

        $this->getJson('/api/admin/alat-log?search=bUdI')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nama_pic', 'Budi Santoso');
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

    private function makeMahasiswa(int $nim, string $nama): Mahasiswa
    {
        return Mahasiswa::create([
            'nama' => $nama,
            'nim' => $nim,
            'surel' => $nim.'@student.upnyk.ac.id',
            'angkatan' => 2025,
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
}
