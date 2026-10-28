<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MahasiswaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'nim' => $this->nim,
            'surel' => $this->surel,
            'angkatan' => $this->angkatan,
            'praktikum' => $this->praktikum,
            'praktikum_plug' => $this->praktikum_plug,
            'praktikum_kelompok' => $this->praktikum_kelompok,
            'is_asisten' => $this->is_asisten,
            'is_active_asisten' => $this->is_active_asisten,
            'pengampu_praktikum' => $this->pengampu_praktikum,
            'pengampu_plug' => $this->pengampu_plug,
            'role' => $this->role,
        ];
    }
}
