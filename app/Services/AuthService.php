<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @return array{0: Mahasiswa, 1: string}
     */
    public function loginClient(int $nim, string $password): array
    {
        $mahasiswa = Mahasiswa::where('nim', $nim)->first();

        if (! $mahasiswa || ! Hash::check($password, $mahasiswa->password)) {
            $this->throwInvalidCredentials('NIM');
        }

        $this->rehashPasswordIfNeeded($mahasiswa, $password);

        if (! $mahasiswa->is_active_user) {
            $this->throwInactiveAccount();
        }

        return [$mahasiswa, $mahasiswa->createToken('client')->plainTextToken];
    }

    /**
     * @return array{0: Dosen|Mahasiswa, 1: string}
     */
    public function loginAdmin(int $nomorInduk, string $password): array
    {
        $dosen = Dosen::where('nip', $nomorInduk)->first();

        if ($dosen) {
            if (! Hash::check($password, $dosen->password)) {
                $this->throwInvalidCredentials('Nomor induk');
            }

            if (! $dosen->is_active) {
                $this->throwInactiveAccount();
            }

            return [$dosen, $dosen->createToken('admin')->plainTextToken];
        }

        $mahasiswa = Mahasiswa::where('nim', $nomorInduk)->first();

        if ($mahasiswa && Hash::check($password, $mahasiswa->password)) {
            $this->rehashPasswordIfNeeded($mahasiswa, $password);

            if (! $mahasiswa->is_active_user) {
                $this->throwInactiveAccount();
            }

            if (! ($mahasiswa->is_asisten && $mahasiswa->is_active_asisten)) {
                throw ValidationException::withMessages([
                    'nomor_induk' => 'Anda tidak memiliki akses ke panel admin.',
                ]);
            }

            return [$mahasiswa, $mahasiswa->createToken('admin')->plainTextToken];
        }

        $this->throwInvalidCredentials('Nomor induk');
    }

    /**
     * Naikkan biaya hash password yang dibuat saat import CSV ke cost default.
     */
    private function rehashPasswordIfNeeded(Mahasiswa $mahasiswa, string $password): void
    {
        if (! Hash::needsRehash($mahasiswa->password)) {
            return;
        }

        $mahasiswa->update(['password' => Hash::make($password)]);
    }

    public function logout(Dosen|Mahasiswa $user): void
    {
        $user->currentAccessToken()->delete();
    }

    private function throwInvalidCredentials(string $label): never
    {
        throw ValidationException::withMessages([
            'identity' => "{$label} atau password salah.",
        ]);
    }

    private function throwInactiveAccount(): never
    {
        throw ValidationException::withMessages([
            'identity' => 'Akun Anda tidak aktif. Hubungi admin lab.',
        ]);
    }
}
