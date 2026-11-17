<?php

namespace Tests\Feature\Admin;

use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Praktikum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PraktikumKelompokTest extends TestCase
{
    use RefreshDatabase;

    public function test_dosen_can_sync_kelompok_for_a_plug(): void
    {
        Sanctum::actingAs($this->makeDosen());
        $praktikum = $this->makePraktikum();
        $slug = $praktikum->praktikum_slug;

        $a = $this->makeMahasiswa(117250001, $slug, 'Plug A', '4');
        $b = $this->makeMahasiswa(117250002, $slug, 'Plug A', '5');
        $other = $this->makeMahasiswa(117250003, $slug, 'Plug B', '9');

        $this->putJson(
            "/api/admin/praktikum/{$slug}/plugs/".rawurlencode('Plug A').'/kelompok',
            [
                'kelompok' => [
                    (string) $a->id => '6',
                    (string) $b->id => null,
                ],
            ],
        )->assertOk();

        $this->assertSame([$slug => '6'], $a->fresh()->praktikum_kelompok);
        $this->assertArrayNotHasKey($slug, $b->fresh()->praktikum_kelompok ?? []);
        $this->assertSame([$slug => '9'], $other->fresh()->praktikum_kelompok);
    }

    public function test_asisten_cannot_sync_kelompok_outside_their_plug(): void
    {
        $praktikum = $this->makePraktikum();
        $slug = $praktikum->praktikum_slug;

        $asisten = $this->makeMahasiswa(
            117250099,
            $slug,
            'Plug A',
            '2',
            isAsisten: true,
            pengampuPlug: [$slug => ['Plug B']],
        );

        Sanctum::actingAs($asisten);

        $this->putJson(
            "/api/admin/praktikum/{$slug}/plugs/".rawurlencode('Plug A').'/kelompok',
            [
                'kelompok' => [
                    (string) $asisten->id => '3',
                ],
            ],
        )->assertForbidden();

        $this->assertSame([$slug => '2'], $asisten->fresh()->praktikum_kelompok);
    }

    public function test_asisten_can_sync_kelompok_for_their_own_plug(): void
    {
        $praktikum = $this->makePraktikum();
        $slug = $praktikum->praktikum_slug;

        $asisten = $this->makeMahasiswa(
            117250099,
            $slug,
            'Plug A',
            '2',
            isAsisten: true,
            pengampuPlug: [$slug => ['Plug A']],
        );
        $student = $this->makeMahasiswa(117250001, $slug, 'Plug A', '2');

        Sanctum::actingAs($asisten);

        $this->putJson(
            "/api/admin/praktikum/{$slug}/plugs/".rawurlencode('Plug A').'/kelompok',
            [
                'kelompok' => [
                    (string) $asisten->id => '4',
                    (string) $student->id => '4',
                ],
            ],
        )->assertOk();

        $this->assertSame([$slug => '4'], $asisten->fresh()->praktikum_kelompok);
        $this->assertSame([$slug => '4'], $student->fresh()->praktikum_kelompok);
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

    private function makePraktikum(): Praktikum
    {
        $praktikum = Praktikum::create([
            'praktikum_label' => 'Sistem Geospasial',
            'semester' => 'Ganjil 2025/2026',
            'is_active' => true,
            'praktikum_slug' => 'sig',
        ]);

        foreach (['Plug A', 'Plug B'] as $plug) {
            $praktikum->jadwal()->create([
                'plug' => $plug,
                'hari' => 'Senin',
                'jam_mulai' => '08:00',
                'jam_selesai' => '10:00',
                'is_active' => true,
            ]);
        }

        return $praktikum;
    }

    private function makeMahasiswa(
        int $nim,
        string $slug,
        string $plug,
        ?string $kelompok,
        bool $isAsisten = false,
        ?array $pengampuPlug = null,
    ): Mahasiswa {
        return Mahasiswa::create([
            'nama' => $isAsisten ? 'Asisten Uji' : 'Mahasiswa Uji',
            'nim' => $nim,
            'surel' => $nim.'@student.upnyk.ac.id',
            'angkatan' => 2025,
            'password' => (string) $nim,
            'praktikum' => [$slug],
            'praktikum_plug' => [$slug => $plug],
            'praktikum_kelompok' => $kelompok === null ? [] : [$slug => $kelompok],
            'is_asisten' => $isAsisten,
            'is_active_asisten' => $isAsisten,
            'is_active_user' => true,
            'pengampu_praktikum' => $isAsisten ? [$slug] : [],
            'pengampu_plug' => $pengampuPlug ?? [],
        ]);
    }
}
