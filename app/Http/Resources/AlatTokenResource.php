<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlatTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'mahasiswa_id' => $this->mahasiswa_id,
            'mahasiswa_nama' => $this->mahasiswa?->nama,
            'mahasiswa_nim' => $this->mahasiswa?->nim,
            'permissions' => $this->permissions,
            'expires_at' => $this->expires_at?->toISOString(),
            'claimed_at' => $this->claimed_at?->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
            'status' => $this->status(),
        ];
    }
}
