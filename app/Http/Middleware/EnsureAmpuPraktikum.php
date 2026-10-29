<?php

namespace App\Http\Middleware;

use App\Models\Praktikum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAmpuPraktikum
{
    public function handle(Request $request, Closure $next, string $param = 'praktikum'): Response
    {
        $user = $request->user();

        if ($user->role === 'dosen') {
            return $next($request);
        }

        $value = $request->route($param);
        $slug = $value instanceof Praktikum ? $value->praktikum_slug : $value;

        if (! is_string($slug) || ! in_array($slug, $user->pengampu_praktikum ?? [], true)) {
            abort(403, 'Anda hanya dapat mengakses praktikum yang Anda ampu.');
        }

        return $next($request);
    }
}
