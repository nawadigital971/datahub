<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets MCP clients that cannot send headers pass their token in the URL (`/mcp?token=...`).
 * The token is moved into the Authorization header so Sanctum checks it exactly as usual.
 */
class AcceptMcpTokenFromUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('token');

        if ($request->bearerToken() === null && is_string($token) && $token !== '') {
            $request->headers->set('Authorization', "Bearer {$token}");
        }

        return $next($request);
    }
}
