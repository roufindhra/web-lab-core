<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KeperluanPeminjaman;
use App\Http\Controllers\Controller;
use App\Http\Resources\PeminjamanResource;
use App\Mail\PeminjamanDitolak;
use App\Mail\SewaDisetujui;
use App\Models\Alat;
use App\Models\Peminjaman;
use App\Services\CrudService;
use App\Services\PeminjamanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PeminjamanController extends Controller
{
    public function __construct(
        private readonly PeminjamanService $peminjaman,
        private readonly CrudService $crud,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:'.implode(',', Peminjaman::STATUS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $peminjaman = Peminjaman::query()
            ->with('items.alat')
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('nim', 'ilike', "%{$search}%")
                        ->orWhere('keperluan', 'ilike', "%{$search}%")
                        ->orWhereHas('items', fn ($query) => $query
                            ->where('inventaris', 'ilike', "%{$search}%"));
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query
                ->where('status', $status))
            ->latest()
            ->paginate(perPage: $validated['per_page'] ?? 15, page: $validated['page'] ?? null);

        return $this->crud->paginated($peminjaman, PeminjamanResource::class);
    }

    public function alatOptions(): JsonResponse
    {
        $alat = Alat::query()
            ->orderBy('nama_alat')
            ->orderBy('inventaris')
            ->get(['id', 'nama_alat', 'inventaris', 'jumlah']);

        return response()->json(['data' => $alat]);
    }

    public function revisiItems(Request $request, Peminjaman $peminjaman): JsonResponse
    {
        if ($peminjaman->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan yang masih menunggu yang dapat direvisi.'], 422);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.alat_id' => ['required', 'integer', 'exists:alat,id'],
            'items.*.jumlah' => ['required', 'integer', 'min:1'],
        ]);

        $alatList = Alat::query()->findMany(collect($validated['items'])->pluck('alat_id'))->keyBy('id');

        $errors = [];
        foreach ($validated['items'] as $i => $item) {
            $alat = $alatList[$item['alat_id']];

            if ($item['jumlah'] > $alat->jumlah) {
                $errors["items.{$i}.jumlah"] = "Jumlah {$alat->nama_alat} melebihi stok (maks {$alat->jumlah}).";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->peminjaman->revisi($peminjaman, $validated['items']);

        return response()->json(new PeminjamanResource($peminjaman->fresh(['items.alat'])));
    }

    public function setujui(Request $request, Peminjaman $peminjaman): JsonResponse
    {
        if ($peminjaman->status !== 'pending') {
            return response()->json(['message' => 'Pengajuan sudah diproses.'], 422);
        }

        $user = $request->user();

        if ($user->role === 'asisten'
            && ($peminjaman->keperluan !== KeperluanPeminjaman::Praktikum
                || ! in_array($peminjaman->praktikum_slug, $user->pengampu_praktikum ?? [], true))
        ) {
            return response()->json(['message' => 'Anda hanya dapat menyetujui peminjaman untuk praktikum yang Anda ampu.'], 403);
        }

        $this->peminjaman->setujui($peminjaman, approvedBy: $user->nama);

        if ($peminjaman->keperluan === KeperluanPeminjaman::PenyewaanKomersil && $peminjaman->email !== null) {
            try {
                Mail::to($peminjaman->email)->send(new SewaDisetujui($peminjaman->fresh(['items.alat'])));
            } catch (\Throwable $e) {
                Log::warning("Gagal mengirim email persetujuan sewa #{$peminjaman->id}: {$e->getMessage()}");
            }
        }

        return response()->json(new PeminjamanResource($peminjaman->fresh(['items.alat'])));
    }

    public function tolak(Request $request, Peminjaman $peminjaman): JsonResponse
    {
        if ($peminjaman->status !== 'pending') {
            return response()->json(['message' => 'Pengajuan sudah diproses.'], 422);
        }

        $validated = $request->validate([
            'catatan' => ['required', 'string', 'max:500'],
        ]);

        $this->peminjaman->tolak($peminjaman, $validated['catatan']);

        if ($peminjaman->email !== null) {
            try {
                Mail::to($peminjaman->email)->send(new PeminjamanDitolak($peminjaman->fresh(['items.alat'])));
            } catch (\Throwable $e) {
                Log::warning("Gagal mengirim email penolakan #{$peminjaman->id}: {$e->getMessage()}");
            }
        }

        return response()->json(new PeminjamanResource($peminjaman->fresh(['items.alat'])));
    }

    public function kembalikan(Request $request, Peminjaman $peminjaman): JsonResponse
    {
        if ($peminjaman->status !== 'disetujui' || $peminjaman->returned_at !== null) {
            return response()->json(['message' => 'Peminjaman ini tidak sedang berjalan.'], 422);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.peminjaman_alat_id' => ['required', 'integer'],
            'items.*.kondisi' => ['required', 'array', 'min:1'],
            'items.*.kondisi.*.status' => ['required', 'in:'.implode(',', Alat::KONDISI_STATUSES)],
            'items.*.kondisi.*.jumlah' => ['required', 'integer', 'min:1'],
            'items.*.kondisi.*.catatan' => ['present', 'array'],
            'items.*.kondisi.*.catatan.*.komponen' => ['required', 'string', 'max:100'],
            'items.*.kondisi.*.catatan.*.keterangan' => ['required', 'string', 'max:255'],
            'items.*.ketersediaan' => ['required', 'in:tersedia,perbaikan'],
        ]);

        $items = collect($validated['items'])->keyBy('peminjaman_alat_id');
        $pivotIds = $peminjaman->items()->pluck('id');

        if ($items->keys()->diff($pivotIds)->isNotEmpty()) {
            return response()->json(['message' => 'Item pengecekan tidak sesuai dengan peminjaman ini.'], 422);
        }

        $detail = $peminjaman->items()->with('alat')->get()->keyBy('id');
        $errors = [];

        foreach ($items as $pivotId => $item) {
            $alat = $detail[$pivotId]->alat;
            $totalKondisi = collect($item['kondisi'])->sum('jumlah');

            if ($totalKondisi !== $alat->jumlah) {
                $errors["items.{$pivotId}"] = "Total unit kondisi {$alat->nama_alat} harus sama dengan jumlah alat ({$alat->jumlah}).";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->peminjaman->kembalikan($peminjaman, $validated['items']);

        return response()->json(new PeminjamanResource($peminjaman->fresh(['items.alat'])));
    }
}
