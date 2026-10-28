<?php

namespace App\Services;

use App\Models\AdminAlatToken;
use App\Models\Dosen;
use Illuminate\Http\Request;

class AlatPermissionService
{
    /** @return string[] */
    public function permissionsFor(Request $request): array
    {
        if ($request->user() instanceof Dosen) {
            return ['*'];
        }

        return AdminAlatToken::query()
            ->where('mahasiswa_id', $request->user()->id)
            ->whereNotNull('claimed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('claimed_at')
            ->value('permissions') ?? [];
    }

    public function can(Request $request, string $permission): bool
    {
        $permissions = $this->permissionsFor($request);

        return in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }
}
