<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlatTokenResource;
use App\Models\AdminAlatToken;
use App\Models\Mahasiswa;
use App\Services\CrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AlatTokenController extends Controller
{
    private const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(
        private readonly CrudService $crud,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tokens = AdminAlatToken::query()
            ->with('mahasiswa:id,nim,nama')
            ->orderByDesc('created_at')
            ->paginate(perPage: $request->integer('per_page', 20));

        return $this->crud->paginated($tokens, AlatTokenResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mahasiswa_id' => [
                'required', 'integer',
                Rule::exists('user_mahasiswa', 'id')->where('is_asisten', true),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['in:'.implode(',', AdminAlatToken::PERMISSIONS)],
            'duration' => ['required', 'in:'.implode(',', array_keys(AdminAlatToken::DURATIONS))],
        ]);

        $token = AdminAlatToken::create([
            'mahasiswa_id' => $validated['mahasiswa_id'],
            'dosen_id' => $request->user()->id,
            'token' => $this->generateCode(),
            'permissions' => array_values($validated['permissions']),
            'expires_at' => now()->addMinutes(AdminAlatToken::DURATIONS[$validated['duration']]),
        ]);

        return response()->json(new AlatTokenResource($token), 201);
    }

    public function revoke(Request $request, AdminAlatToken $token): JsonResponse
    {
        if ($token->revoked_at === null) {
            $token->update(['revoked_at' => now()]);
        }

        return response()->json(new AlatTokenResource($token->fresh()));
    }

    public function claim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:16'],
        ]);

        $token = AdminAlatToken::where('token', strtoupper(trim($validated['token'])))->first();

        if ($token === null) {
            return response()->json(['message' => 'Token tidak ditemukan.'], 404);
        }

        if ($token->mahasiswa_id !== $request->user()->id) {
            return response()->json(['message' => 'Token ini bukan untuk akun Anda.'], 403);
        }

        if ($token->revoked_at !== null) {
            return response()->json(['message' => 'Token sudah dicabut.'], 422);
        }

        if ($token->expires_at->isPast()) {
            return response()->json(['message' => 'Token sudah kedaluwarsa.'], 422);
        }

        if ($token->claimed_at === null) {
            $token->update(['claimed_at' => now()]);
        }

        return response()->json([
            'permissions' => $token->permissions,
            'expires_at' => $token->expires_at->toISOString(),
        ]);
    }

    private function generateCode(): string
    {
        do {
            $code = 'SGG-'.substr(str_shuffle(self::CODE_ALPHABET), 0, 8);
        } while (AdminAlatToken::where('token', $code)->exists());

        return $code;
    }
}
