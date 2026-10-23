<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('praktikum_nama', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('praktikum_label');
            $table->string('praktikum_slug');
            $table->string('semester');
            $table->boolean('is_active')->default(false);
        });
        Schema::create('praktikum_jadwal', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('praktikum_id')->constrained('praktikum_nama')->cascadeOnDelete();
            $table->string('plug', 50);
            $table->string('hari', 10);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('praktikum_kurikulum', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('praktikum_id')->constrained('praktikum_nama')->cascadeOnDelete();
            $table->smallInteger('materi_id');
            $table->string('materi_nama');
            $table->longText('deskripsi');
            $table->boolean('is_active')->default(true);
            $table->unique(['praktikum_id', 'materi_id']);
        });
        Schema::create('praktikum_pertemuan', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('praktikum_id')->constrained('praktikum_nama')->cascadeOnDelete();
            $table->foreignId('materi_id')->constrained('praktikum_kurikulum')->restrictOnDelete();
            $table->smallInteger('nomor');
            $table->date('tanggal')->nullable();
            $table->smallInteger('bobot');
            $table->jsonb('parameter');
        });
        Schema::create('praktikum_nilai', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('pertemuan_id')->constrained('praktikum_pertemuan')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('user_mahasiswa')->cascadeOnDelete();
            $table->jsonb('nilai');
            $table->unique(['pertemuan_id', 'mahasiswa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('praktikum_nilai');
        Schema::dropIfExists('praktikum_pertemuan');
        Schema::dropIfExists('praktikum_kurikulum');
        Schema::dropIfExists('praktikum_jadwal');
        Schema::dropIfExists('praktikum_nama');
    }
};
