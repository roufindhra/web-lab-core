<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PraktikumJadwalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plug' => $this->plug,
            'hari' => $this->hari,
            'jam_mulai' => $this->jam_mulai?->format('H:i'),
            'jam_selesai' => $this->jam_selesai?->format('H:i'),
            'is_active' => $this->is_active,
            'mahasiswa_count' => $this->mahasiswa_count ?? 0,
            'praktikum' => new PraktikumResource($this->whenLoaded('praktikum')),
        ];
    }
}
