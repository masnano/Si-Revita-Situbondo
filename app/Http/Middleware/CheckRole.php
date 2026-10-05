<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Root has access to everything
        if ($user->isRoot()) {
            return $next($request);
        }

        if (in_array($user->role->name ?? '', $roles)) {
            return $next($request);
        }

        abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk membuka halaman ini.');
    }
}

