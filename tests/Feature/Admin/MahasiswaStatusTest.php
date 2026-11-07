<?php

namespace Tests\Feature\Admin;

use App\Models\Dosen;
use App\Models\Mahasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MahasiswaStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_returns_active_students_by_default(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $active = $this->makeMahasiswa(117250001, true);
        $this->makeMahasiswa(117250002, false);

        $response = $this->getJson('/api/admin/mahasiswa');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.is_active_user', true);
    }

    public function test_index_can_filter_inactive_or_all_students(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $active = $this->makeMahasiswa(117250001, true);
        $inactive = $this->makeMahasiswa(117250002, false);

        $this->getJson('/api/admin/mahasiswa?status=inactive')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $inactive->id)
            ->assertJsonPath('data.0.is_active_user', false);

        $this->getJson('/api/admin/mahasiswa?status=all')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_dosen_can_deactivate_student_and_revoke_tokens(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $mahasiswa = $this->makeMahasiswa(117250001, true);
        $mahasiswa->createToken('client');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->putJson("/api/admin/mahasiswa/{$mahasiswa->id}/status", [
            'is_active_user' => false,
        ])
            ->assertOk()
            ->assertJsonPath('is_active_user', false);

        $this->assertFalse($mahasiswa->fresh()->is_active_user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_dosen_can_reactivate_student(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $mahasiswa = $this->makeMahasiswa(117250001, false);

        $this->putJson("/api/admin/mahasiswa/{$mahasiswa->id}/status", [
            'is_active_user' => true,
        ])
            ->assertOk()
            ->assertJsonPath('is_active_user', true);

        $this->assertTrue($mahasiswa->fresh()->is_active_user);
    }

    public function test_asisten_cannot_update_student_status(): void
    {
        Sanctum::actingAs($this->makeMahasiswa(117250099, true, true));

        $mahasiswa = $this->makeMahasiswa(117250001, true);

        $this->putJson("/api/admin/mahasiswa/{$mahasiswa->id}/status", [
            'is_active_user' => false,
        ])->assertForbidden();

        $this->assertTrue($mahasiswa->fresh()->is_active_user);
    }

    public function test_dosen_can_reset_password_to_nim_and_revoke_tokens(): void
    {
        Sanctum::actingAs($this->makeDosen());

        $mahasiswa = $this->makeMahasiswa(117250001, true);
        $mahasiswa->update(['password' => 'password-lama']);
        $mahasiswa->createToken('client');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson("/api/admin/mahasiswa/{$mahasiswa->id}/reset-password")
            ->assertOk();

        $fresh = $mahasiswa->fresh();

        $this->assertTrue(Hash::check('117250001', $fresh->password));
        $this->assertFalse(Hash::check('password-lama', $fresh->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_asisten_cannot_reset_student_password(): void
    {
        Sanctum::actingAs($this->makeMahasiswa(117250099, true, true));

        $mahasiswa = $this->makeMahasiswa(117250001, true);
        $mahasiswa->update(['password' => 'password-lama']);

        $this->postJson("/api/admin/mahasiswa/{$mahasiswa->id}/reset-password")
            ->assertForbidden();

        $this->assertTrue(Hash::check('password-lama', $mahasiswa->fresh()->password));
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

    private function makeMahasiswa(int $nim, bool $isActive, bool $isAsisten = false): Mahasiswa
    {
        return Mahasiswa::create([
            'nama' => $isAsisten ? 'Asisten Uji' : 'Mahasiswa Uji',
            'nim' => $nim,
            'surel' => $nim.'@student.upnyk.ac.id',
            'angkatan' => 2025,
            'password' => (string) $nim,
            'is_asisten' => $isAsisten,
            'is_active_asisten' => $isAsisten,
            'is_active_user' => $isActive,
        ]);
    }
}
