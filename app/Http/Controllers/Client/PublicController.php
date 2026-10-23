<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\PraktikumJadwalResource;
use App\Http\Resources\PraktikumResource;
use App\Models\Dosen;
use App\Models\Praktikum;
use App\Models\PraktikumJadwal;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

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

    public function akademik(): JsonResponse
    {
        $data = Praktikum::query()
            ->where('is_active', true)
            ->orderBy('semester')
            ->orderBy('praktikum_label')
            ->get(['id', 'praktikum_label', 'praktikum_slug', 'semester'])
            ->map(function (Praktikum $praktikum) {
                $slug = $praktikum->praktikum_slug;

                return [
                    'id' => $praktikum->id,
                    'label' => $praktikum->praktikum_label,
                    'slug' => $slug,
                    'semester' => $praktikum->semester,
                    'dosen' => Dosen::query()
                        ->where('is_active', true)
                        ->whereJsonContains('pengampu_slug', $slug)
                        ->orderBy('nama')
                        ->pluck('nama')
                        ->all(),
                    'jadwal' => $praktikum->jadwal()
                        ->where('is_active', true)
                        ->orderBy('hari')
                        ->orderBy('jam_mulai')
                        ->get(['plug', 'hari', 'jam_mulai', 'jam_selesai']),
                    'materi' => $praktikum->kurikulums()
                        ->where('is_active', true)
                        ->orderBy('materi_id')
                        ->get(['materi_id', 'materi_nama', 'deskripsi']),
                ];
            });

        return response()->json(['data' => $data]);
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
