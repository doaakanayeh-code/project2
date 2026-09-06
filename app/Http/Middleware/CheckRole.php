<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
/**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
 public function handle($request, Closure $next, $role = 'admin')
{
if (!auth()->check() || auth()->user()->role !== $role) {
return response()->json(['message' => 'Unauthorized! You do not have this role.'], 403);
 }

return $next($request);
}
}