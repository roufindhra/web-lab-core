<?php

namespace App\Http\Middleware;

use App\Models\Praktikum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAmpuPlug
{
    public function handle(Request $request, Closure $next, string $param = 'plug'): Response
    {
        $user = $request->user();

        if ($user->role === 'dosen') {
            return $next($request);
        }

        $praktikum = $request->route('praktikum');
        $slug = $praktikum instanceof Praktikum ? $praktikum->praktikum_slug : $praktikum;
        $plug = $request->route($param);

        if (! is_string($slug) || ! is_string($plug)
            || ! in_array($plug, $user->pengampu_plug[$slug] ?? [], true)) {
            abort(403, 'Anda hanya dapat mengakses plug yang Anda ampu.');
        }

        return $next($request);
    }
}
