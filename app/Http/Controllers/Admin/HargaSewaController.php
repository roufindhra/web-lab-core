<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HargaSewa;
use App\Services\CrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HargaSewaController extends Controller
{
    public function __construct(
        private readonly CrudService $crud,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $hargaSewa = HargaSewa::query()
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query
                ->where('nama_alat', 'ilike', "%{$search}%"))
            ->orderBy('nama_alat')
            ->paginate(perPage: $validated['per_page'] ?? 15, page: $validated['page'] ?? null);

        return $this->crud->paginated($hargaSewa);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateHarga($request);

        $hargaSewa = HargaSewa::create($data);

        return response()->json($hargaSewa, 201);
    }

    public function update(Request $request, HargaSewa $hargaSewa): JsonResponse
    {
        $data = $this->validateHarga($request, $hargaSewa);

        $hargaSewa->update($data);

        return response()->json($hargaSewa->fresh());
    }

    public function destroy(HargaSewa $hargaSewa): JsonResponse
    {
        $hargaSewa->delete();

        return response()->json(null, 204);
    }

    private function validateHarga(Request $request, ?HargaSewa $hargaSewa = null): array
    {
        return $request->validate([
            'nama_alat' => ['required', 'string', 'max:150',
                Rule::unique('harga_sewa', 'nama_alat')->ignore($hargaSewa?->id),
            ],
            'tarif_per_hari' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
