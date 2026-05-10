<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->role !== $role) {
            $route = $user->role === 'admin' ? 'admin.dashboard' : 'affiliate.dashboard';

            return redirect()
                ->route($route)
                ->with('error', 'Anda tidak dibenarkan mengakses halaman tersebut.');
        }

        return $next($request);
    }
}
