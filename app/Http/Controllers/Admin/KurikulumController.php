<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Praktikum;
use App\Models\PraktikumKurikulum;
use App\Services\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KurikulumController extends Controller
{
    public function __construct(private readonly HtmlSanitizer $sanitizer)
    {
    }

    public function index(Request $request, Praktikum $praktikum): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $kurikulums = $praktikum->kurikulums()
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('materi_nama', 'ilike', "%{$search}%")
                        ->orWhereRaw("regexp_replace(deskripsi, '<[^>]*>', ' ', 'g') ilike ?", ["%{$search}%"]);
                });
            })
            ->orderBy('materi_id')
            ->get();

        if (($validated['search'] ?? null) !== null) {
            $kurikulums->each(fn (PraktikumKurikulum $kurikulum) => $kurikulum->snippet = $this->snippet($kurikulum, $validated['search']));
        }

        return response()->json([
            'data' => $kurikulums,
        ]);
    }

    private function snippet(PraktikumKurikulum $kurikulum, string $search): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($kurikulum->deskripsi)));

        $pos = stripos($text, $search);

        if ($pos === false) {
            return mb_substr($text, 0, 140).(mb_strlen($text) > 140 ? '…' : '');
        }

        $start = max(0, $pos - 60);
        $snippet = mb_substr($text, $start, 140);

        return ($start > 0 ? '…' : '').$snippet.($start + 140 < mb_strlen($text) ? '…' : '');
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

    public function reorder(Request $request, Praktikum $praktikum): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $owned = $praktikum->kurikulums()
            ->whereIn('id', $data['ids'])
            ->pluck('id');

        if ($owned->count() !== count($data['ids'])) {
            return response()->json([
                'message' => 'Terdapat materi yang tidak termasuk praktikum ini.',
            ], 422);
        }

        DB::transaction(function () use ($praktikum, $data) {
            // dua tahap: unik constraint (praktikum_id, materi_id) akan dilanggar
            // jika swap langsung. Geser sebanyak max+1 sehingga nilai staging
            // selalu negatif (tak menabrak baris lain) dan tetap dalam rentang
            // smallint milik kolom materi_id.
            $shift = (int) $praktikum->kurikulums()->max('materi_id') + 1;

            foreach ($data['ids'] as $id) {
                PraktikumKurikulum::whereKey($id)->decrement('materi_id', $shift);
            }

            foreach (array_values($data['ids']) as $index => $id) {
                PraktikumKurikulum::whereKey($id)->update(['materi_id' => $index + 1]);
            }
        });

        return response()->json(['message' => 'Urutan materi diperbarui.']);
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
