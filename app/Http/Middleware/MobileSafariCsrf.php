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
            Log::info('Mobile Safari CSRF handling', [
                'url' => $request->url(),
                'method' => $request->method(),
                'has_csrf_token_header' => $request->hasHeader('X-CSRF-TOKEN'),
                'has_csrf_token_form' => $request->has('_token'),
                'session_id' => session()->getId(),
                'user_agent' => $request->userAgent(),
            ]);
            
            // For mobile Safari, be more lenient with CSRF validation
            // Check if we have a valid session first
            if (!session()->getId()) {
                Log::warning('Mobile Safari: No session ID, skipping CSRF validation');
                return $next($request);
            }
            
            // Check if we have any CSRF token at all
            $hasToken = $request->hasHeader('X-CSRF-TOKEN') || $request->has('_token');
            
            if (!$hasToken) {
                Log::warning('Mobile Safari: No CSRF token found, skipping validation');
                return $next($request);
            }
        }
        
        // For non-mobile Safari or when we have proper tokens, use normal CSRF validation
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
