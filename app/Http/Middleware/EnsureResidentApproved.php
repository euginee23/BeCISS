<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResidentApproved
{
    /**
     * Handle an incoming request.
     *
     * Ensures resident-role users have an approved profile before accessing protected routes.
     * - No resident record → redirect to complete-profile page
     * - Pending → redirect to pending-approval page
     * - Rejected → redirect to complete-profile page (can resubmit)
     * - Approved → allow through
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isResident()) {
            return $next($request);
        }

        $resident = $user->resident;

        if (! $resident || $resident->isRejected()) {
            if ($request->routeIs('complete-profile')) {
                return $next($request);
            }

            return redirect()->route('complete-profile');
        }

        if ($resident->isPending()) {
            return redirect()->route('pending-approval');
        }

        return $next($request);
    }
}
