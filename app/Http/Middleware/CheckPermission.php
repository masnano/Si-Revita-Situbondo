<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Root always bypasses permission checks
        if ($user->isRoot()) {
            return $next($request);
        }

        if ($user->hasPermission($permission)) {
            return $next($request);
        }

        abort(403, "Akses ditolak: Anda tidak memiliki izin [{$permission}] untuk modul ini.");
    }
}

