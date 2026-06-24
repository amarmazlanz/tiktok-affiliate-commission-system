<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHasBeenChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'affiliate' && $request->user()->must_change_password) {
            return redirect()
                ->route('affiliate.password.edit')
                ->with('warning', 'You must change your temporary password before continuing.');
        }

        return $next($request);
    }
}
