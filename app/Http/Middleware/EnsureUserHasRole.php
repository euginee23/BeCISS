<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles  Allowed roles (e.g., 'admin', 'staff', 'resident')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (! $request->user()->hasRole($roles)) {
            abort(403, 'Unauthorized. You do not have the required role to access this resource.');
        }

        return $next($request);
    }
}
