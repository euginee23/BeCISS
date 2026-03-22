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
     * - No resident record → allow through (first-login info modal on dashboard)
     * - Pending → redirect to pending-approval page
     * - Rejected → allow through (can resubmit on dashboard)
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

        if (! $resident) {
            return $next($request);
        }

        if ($resident->isPending()) {
            return redirect()->route('pending-approval');
        }

        return $next($request);
    }
}
