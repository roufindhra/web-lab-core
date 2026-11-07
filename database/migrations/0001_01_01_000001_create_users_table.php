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
        Schema::create('user_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('nama');
            $table->bigInteger('nim')->unique();
            $table->string('surel')->unique();
            $table->integer('angkatan');
            $table->jsonb('praktikum')->nullable();
            $table->jsonb('praktikum_plug')->nullable();
            $table->jsonb('praktikum_kelompok')->nullable();
            $table->boolean('is_asisten')->default(false);
            $table->jsonb('pengampu_praktikum')->nullable();
            $table->jsonb('pengampu_plug')->nullable();
            $table->boolean('is_active_asisten')->default(false);
            $table->string('password');
            $table->boolean('is_active_user')->default(false);
        });
        Schema::create('user_dosen', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('nama');
            $table->string('nip')->unique();
            $table->string('surel')->unique()->nullable();
            $table->jsonb('kepakaran');
            $table->jsonb('pengampu');
            $table->jsonb('pengampu_slug');
            $table->string('password');
            $table->boolean('is_active')->default(false);
        });
        Schema::create('mahasiswa_import', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->jsonb('failures')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mahasiswa_import');
        Schema::dropIfExists('user_mahasiswa');
        Schema::dropIfExists('user_dosen');
    }
};
