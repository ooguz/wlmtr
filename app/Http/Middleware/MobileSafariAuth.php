<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MobileSafariAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is mobile Safari and has auth token
        $isMobileSafari = preg_match('/Mobile\/.*Safari/', $request->userAgent()) && 
                         !preg_match('/CriOS|FxiOS|EdgiOS/', $request->userAgent());
        
        \Log::info('Mobile Safari Auth Middleware', [
            'is_mobile_safari' => $isMobileSafari,
            'has_auth_token' => $request->has('auth_token'),
            'auth_check' => Auth::check(),
            'user_agent' => $request->userAgent(),
            'url' => $request->url(),
        ]);
        
        if ($isMobileSafari && $request->has('auth_token') && !Auth::check()) {
            $authToken = $request->input('auth_token');
            
            \Log::info('Attempting mobile Safari auth with token', [
                'auth_token' => $authToken,
            ]);
            
            // Check if auth token exists in cache
            $userId = cache()->get('mobile_safari_auth_' . $authToken);
            
            \Log::info('Auth token lookup result', [
                'user_id' => $userId,
                'cache_key' => 'mobile_safari_auth_' . $authToken,
            ]);
            
            if ($userId) {
                // Find the user and log them in
                $user = \App\Models\User::find($userId);
                
                if ($user) {
                    Auth::login($user);
                    
                    // Clear the auth token after use
                    cache()->forget('mobile_safari_auth_' . $authToken);
                    
                    \Log::info('Mobile Safari user logged in via auth token', [
                        'user_id' => $user->id,
                        'auth_token' => $authToken,
                        'authenticated_after' => Auth::check(),
                    ]);
                    
                    // Redirect without the auth token to clean up the URL
                    return redirect()->to($request->url());
                }
            } else {
                \Log::warning('Invalid or expired mobile Safari auth token', [
                    'auth_token' => $authToken,
                ]);
            }
        }
        
        return $next($request);
    }
}
