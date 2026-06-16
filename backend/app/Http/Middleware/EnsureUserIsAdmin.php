<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates admin routes by role (NFR-S6). Authorization is server-side; the UI is
 * never trusted to hide admin features.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin() === true, 403, 'Admin access required.');

        return $next($request);
    }
}
