<?php

namespace Tests\Feature\Admin;

use App\Jobs\ImportMahasiswaCsv;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\MahasiswaImport;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MahasiswaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_dispatches_background_job(): void
    {
        Queue::fake();
        Storage::fake('local');
        Sanctum::actingAs($this->makeDosen());

        $response = $this->postJson('/api/admin/mahasiswa/import', [
            'file' => UploadedFile::fake()->createWithContent(
                'mahasiswa.csv',
                "nama,nim\nBudi Santoso,117250019\n",
            ),
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('total_rows', 0);

        Queue::assertPushed(ImportMahasiswaCsv::class);
        $this->assertDatabaseHas('mahasiswa_import', ['status' => 'queued']);
    }

    public function test_upload_rejects_csv_without_required_columns(): void
    {
        Queue::fake();
        Storage::fake('local');
        Sanctum::actingAs($this->makeDosen());

        $response = $this->postJson('/api/admin/mahasiswa/import', [
            'file' => UploadedFile::fake()->createWithContent(
                'mahasiswa.csv',
                "name,identity\nBudi,117250019\n",
            ),
        ]);

        $response->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_job_imports_new_students_and_updates_existing_ones(): void
    {
        Storage::fake('local');

        $existing = Mahasiswa::create([
            'nama' => 'Nama Lama',
            'nim' => 117250019,
            'surel' => '117250019@student.upnyk.ac.id',
            'angkatan' => 2025,
            'password' => '117250019',
            'is_asisten' => false,
            'is_active_user' => true,
        ]);

        $csv = implode("\n", [
            'nama,nim',
            'Nama Baru,117250019',
            'Mahasiswa Baru,117250020',
            'Tanpa Nim,',
            'Nim Salah,12345',
            'Duplikat A,117250021',
            'Duplikat B,117250021',
            '',
        ]);

        $path = 'imports/mahasiswa.csv';
        Storage::disk('local')->put($path, $csv);

        $import = MahasiswaImport::create([
            'original_filename' => 'mahasiswa.csv',
            'file_path' => $path,
            'status' => 'queued',
        ]);

        (new ImportMahasiswaCsv($import->id))->handle();

        $import->refresh();

        $this->assertSame('completed', $import->status);
        $this->assertSame(6, $import->total_rows);
        $this->assertSame(6, $import->processed_rows);
        $this->assertSame(2, $import->imported);
        $this->assertSame(1, $import->updated);
        $this->assertSame(2, $import->failed_count);
        $this->assertCount(2, $import->failures);
        $this->assertNotNull($import->finished_at);
        Storage::disk('local')->assertMissing($path);

        $this->assertSame('Nama Baru', $existing->fresh()->nama);
        $this->assertTrue(Hash::check('117250019', $existing->fresh()->password));

        $baru = Mahasiswa::where('nim', 117250020)->firstOrFail();
        $this->assertSame('Mahasiswa Baru', $baru->nama);
        $this->assertSame('117250020@student.upnyk.ac.id', $baru->surel);
        $this->assertSame(2025, $baru->angkatan);
        $this->assertTrue($baru->is_active_user);
        $this->assertFalse($baru->is_asisten);
        $this->assertTrue(Hash::check('117250020', $baru->password));

        $this->assertSame('Duplikat B', Mahasiswa::where('nim', 117250021)->firstOrFail()->nama);
    }

    public function test_import_with_only_invalid_rows_completes_with_failures(): void
    {
        Storage::fake('local');

        $path = 'imports/invalid.csv';
        Storage::disk('local')->put($path, "nama,nim\nTanpa Nim,\nNim Salah,123\n");

        $import = MahasiswaImport::create([
            'original_filename' => 'invalid.csv',
            'file_path' => $path,
            'status' => 'queued',
        ]);

        (new ImportMahasiswaCsv($import->id))->handle();

        $import->refresh();

        $this->assertSame('completed', $import->status);
        $this->assertSame(2, $import->failed_count);
        $this->assertSame(2, $import->processed_rows);
        $this->assertSame(0, $import->imported);
        $this->assertDatabaseCount('user_mahasiswa', 0);
    }

    public function test_login_rehashes_low_cost_imported_password(): void
    {
        $mahasiswa = Mahasiswa::create([
            'nama' => 'Nama Mahasiswa',
            'nim' => 117250019,
            'surel' => '117250019@student.upnyk.ac.id',
            'angkatan' => 2025,
            'password' => Hash::make('117250019', ['rounds' => 4]),
            'is_asisten' => false,
            'is_active_user' => true,
        ]);

        $this->app['config']->set('hashing.bcrypt.rounds', 12);
        Hash::forgetDrivers();

        $this->assertTrue(Hash::needsRehash($mahasiswa->fresh()->password));

        $this->app->make(AuthService::class)->loginClient(117250019, '117250019');

        $hash = $mahasiswa->fresh()->password;

        $this->assertFalse(Hash::needsRehash($hash));
        $this->assertSame(12, password_get_info($hash)['options']['cost']);
        $this->assertTrue(Hash::check('117250019', $hash));
    }

    public function test_import_status_and_latest_endpoints_return_progress(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->makeDosen());

        $import = MahasiswaImport::create([
            'original_filename' => 'mahasiswa.csv',
            'file_path' => 'imports/mahasiswa.csv',
            'status' => 'queued',
            'total_rows' => 10,
            'processed_rows' => 4,
            'imported' => 2,
            'updated' => 2,
        ]);

        $this->getJson("/api/admin/mahasiswa/import/{$import->id}")
            ->assertOk()
            ->assertJsonPath('id', $import->id)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('total_rows', 10)
            ->assertJsonPath('processed_rows', 4);

        $this->getJson('/api/admin/mahasiswa/import/latest')
            ->assertOk()
            ->assertJsonPath('id', $import->id);
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
}
