<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $resource  The resource to check permission for (e.g., 'residents', 'certificates')
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->hasPermission($resource)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
