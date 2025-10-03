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
        
        // Check for persistent cookie first
        if ($isMobileSafari && !Auth::check() && $request->hasCookie('mobile_safari_auth')) {
            $persistentToken = $request->cookie('mobile_safari_auth');
            
            \Log::info('Checking mobile Safari persistent cookie', [
                'persistent_token' => $persistentToken,
            ]);
            
            $userId = cache()->get('mobile_safari_persistent_' . $persistentToken);
            
            if ($userId) {
                $user = \App\Models\User::find($userId);
                
                if ($user) {
                    Auth::login($user, true);
                    
                    \Log::info('Mobile Safari user logged in via persistent cookie', [
                        'user_id' => $user->id,
                        'persistent_token' => $persistentToken,
                        'authenticated_after' => Auth::check(),
                    ]);
                }
            } else {
                \Log::info('Invalid or expired mobile Safari persistent token', [
                    'persistent_token' => $persistentToken,
                ]);
            }
        }
        
        // Check for one-time auth token
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
                    Auth::login($user, true); // Remember the user
                    
                    // For mobile Safari, also store a persistent auth token in cache
                    $persistentToken = hash('sha256', $user->id . time() . random_bytes(16));
                    cache()->put('mobile_safari_persistent_' . $persistentToken, $user->id, 86400); // 24 hours
                    
                    // Clear the one-time auth token after use
                    cache()->forget('mobile_safari_auth_' . $authToken);
                    
                    \Log::info('Mobile Safari user logged in via auth token', [
                        'user_id' => $user->id,
                        'auth_token' => $authToken,
                        'persistent_token' => $persistentToken,
                        'authenticated_after' => Auth::check(),
                    ]);
                    
                    // Redirect without the auth token but with a persistent token cookie
                    $response = redirect()->to($request->url());
                    
                    // Set a persistent cookie for mobile Safari
                    $cookie = cookie(
                        'mobile_safari_auth',
                        $persistentToken,
                        86400, // 24 hours
                        '/',
                        null,
                        true, // secure
                        true, // httpOnly
                        false, // raw
                        'none' // sameSite
                    );
                    
                    return $response->withCookie($cookie);
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
