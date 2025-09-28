<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuthHorizon
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $username = env('BASICAUTH_USERNAME', 'admin');
        $password = env('BASICAUTH_PASSWORD', 'password');

        if ($request->getUser() !== $username || $request->getPassword() !== $password) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
