<?php

namespace App\Http\Middleware;

use Closure;

class DemoBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!config('demo.auth_enabled')) {
            return $next($request);
        }

        $expectedUser = (string) config('demo.auth_user');
        $expectedPassword = (string) config('demo.auth_password');

        $providedUser = $request->getUser();
        $providedPassword = $request->getPassword();

        if (
            is_string($providedUser) &&
            is_string($providedPassword) &&
            hash_equals($expectedUser, $providedUser) &&
            hash_equals($expectedPassword, $providedPassword)
        ) {
            return $next($request);
        }

        return response('Authentication required.', 401)
            ->header('WWW-Authenticate', 'Basic realm="' . config('demo.auth_realm') . '"');
    }
}
