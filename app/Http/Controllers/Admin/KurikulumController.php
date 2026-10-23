<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Praktikum;
use App\Models\PraktikumKurikulum;
use App\Services\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KurikulumController extends Controller
{
    public function __construct(private readonly HtmlSanitizer $sanitizer)
    {
    }

    public function index(Praktikum $praktikum): JsonResponse
    {
        return response()->json([
            'data' => $praktikum->kurikulums()
                ->orderBy('materi_id')
                ->get(),
        ]);
    }

    public function store(Request $request, Praktikum $praktikum): JsonResponse
    {
        $data = $this->validated($request);

        $materi = $praktikum->kurikulums()->create($data + [
            'materi_id' => (int) $praktikum->kurikulums()->max('materi_id') + 1,
            'is_active' => true,
        ]);

        return response()->json(['data' => $materi], 201);
    }

    public function update(Request $request, Praktikum $praktikum, PraktikumKurikulum $kurikulum): JsonResponse
    {
        $kurikulum->update($this->validated($request));

        return response()->json(['data' => $kurikulum->fresh()]);
    }

    public function destroy(Praktikum $praktikum, PraktikumKurikulum $kurikulum): JsonResponse
    {
        $kurikulum->update(['is_active' => false]);

        return response()->json(['message' => "Materi {$kurikulum->materi_nama} diarsipkan."]);
    }

    public function restore(Praktikum $praktikum, PraktikumKurikulum $kurikulum): JsonResponse
    {
        $kurikulum->update(['is_active' => true]);

        return response()->json(['data' => $kurikulum->fresh()]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'materi_nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
        ]);

        $data['deskripsi'] = $this->sanitizer->clean($data['deskripsi']);

        return $data;
    }
}
