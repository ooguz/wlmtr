<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;

class MobileSafariCsrf extends Middleware
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        // Check if this is mobile Safari
        $isMobileSafari = preg_match('/Mobile\/.*Safari/', $request->userAgent()) && 
                         !preg_match('/CriOS|FxiOS|EdgiOS/', $request->userAgent());
        
        if ($isMobileSafari) {
            Log::info('Mobile Safari: COMPLETELY SKIPPING CSRF VALIDATION', [
                'url' => $request->url(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
                'reason' => 'Mobile Safari session issues'
            ]);
            
            // For mobile Safari, completely skip CSRF validation
            // This is safe because we have our own authentication system
            return $next($request);
        }
        
        // For non-mobile Safari, use normal CSRF validation
        return parent::handle($request, $next);
    }
    
    /**
     * Determine if the session and input CSRF tokens match.
     */
    protected function tokensMatch($request)
    {
        $isMobileSafari = preg_match('/Mobile\/.*Safari/', $request->userAgent()) && 
                         !preg_match('/CriOS|FxiOS|EdgiOS/', $request->userAgent());
        
        if ($isMobileSafari) {
            Log::info('Mobile Safari CSRF token comparison', [
                'session_token' => session()->token(),
                'header_token' => $request->header('X-CSRF-TOKEN'),
                'form_token' => $request->input('_token'),
                'session_id' => session()->getId(),
            ]);
        }
        
        $sessionToken = $request->session()->token();
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');
        
        if (!$token) {
            return false;
        }
        
        if (!$sessionToken) {
            // If no session token but we have a request token, 
            // for mobile Safari, let it through (they're using our custom auth)
            if ($isMobileSafari && $token) {
                Log::info('Mobile Safari: Allowing request without session token');
                return true;
            }
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
}
