<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\PraktikumJadwalResource;
use App\Http\Resources\PraktikumResource;
use App\Models\Praktikum;
use App\Models\PraktikumJadwal;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicController extends Controller
{
    public function praktikum(): AnonymousResourceCollection
    {
        return PraktikumResource::collection(
            Praktikum::query()
                ->where('is_active', true)
                ->with(['jadwal' => fn ($q) => $q
                    ->where('is_active', true)
                    ->orderBy('hari')
                    ->orderBy('jam_mulai')])
                ->orderBy('semester')
                ->orderBy('praktikum_label')
                ->get()
        );
    }

    public function agenda(): AnonymousResourceCollection
    {
        $urutanHari = ['Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6, 'Minggu' => 7];

        $jadwal = PraktikumJadwal::query()
            ->where('is_active', true)
            ->whereHas('praktikum', fn ($q) => $q->where('is_active', true))
            ->with('praktikum:id,praktikum_label,praktikum_slug,semester,is_active')
            ->get()
            ->sortBy([
                fn ($a, $b) => ($urutanHari[$a->hari] ?? 99) <=> ($urutanHari[$b->hari] ?? 99),
                fn ($a, $b) => $a->jam_mulai <=> $b->jam_mulai,
            ])
            ->values();

        return PraktikumJadwalResource::collection($jadwal);
    }
}
