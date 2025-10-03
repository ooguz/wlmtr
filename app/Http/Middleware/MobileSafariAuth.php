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
        
        if ($isMobileSafari && $request->has('auth_token') && !Auth::check()) {
            $authToken = $request->input('auth_token');
            
            // Check if auth token exists in cache
            $userId = cache()->get('mobile_safari_auth_' . $authToken);
            
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
                    ]);
                    
                    // Redirect without the auth token to clean up the URL
                    return redirect()->to($request->url());
                }
            }
        }
        
        return $next($request);
    }
}
