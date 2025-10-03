<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WikimediaAuthService;
use App\Services\WikimediaCommonsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class WikimediaAuthController extends Controller
{
    public function __construct(
        private WikimediaAuthService $wikimediaAuth,
        private WikimediaCommonsService $commonsService
    ) {}

    /**
     * Show the Wikimedia login page.
     */
    public function showLogin(): View
    {
        return view('auth.wikimedia-login');
    }

    /**
     * Redirect to Wikimedia OAuth.
     */
    public function redirectToWikimedia(Request $request): RedirectResponse
    {
        try {
            // Store the intended URL if provided
            if ($request->has('return_url')) {
                session()->put('url.intended', $request->input('return_url'));
            }

            // Store user agent for mobile Safari detection
            session()->put('user_agent', $request->userAgent());
            session()->put('oauth_started_at', now()->timestamp);

            return Socialite::driver('wikimedia')
                ->with([
                    // Hint login UI language explicitly
                    'uselang' => 'tr',
                    // OIDC-compatible language hint; some IdPs respect this
                    'ui_locales' => 'tr',
                ])
                ->redirect();
        } catch (\Exception $e) {
            Log::error('Wikimedia OAuth redirect error', [
                'error' => $e->getMessage(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('auth.login')
                ->withErrors(['wikimedia' => 'Wikimedia authentication is currently unavailable.']);
        }
    }

    /**
     * Handle the Wikimedia OAuth callback.
     */
    public function handleWikimediaCallback(Request $request): RedirectResponse
    {
        try {
            // Check for mobile Safari and handle accordingly
            $userAgent = session()->get('user_agent', $request->userAgent());
            $isMobileSafari = $this->isMobileSafari($userAgent);
            
            if ($isMobileSafari) {
                Log::info('Mobile Safari OAuth callback detected', [
                    'user_agent' => $userAgent,
                    'oauth_started' => session()->get('oauth_started_at'),
                ]);
            }

            $socialiteUser = Socialite::driver('wikimedia')->user();

            Log::info('Wikimedia OAuth callback - Socialite user received', [
                'id' => $socialiteUser->getId(),
                'nickname' => $socialiteUser->getNickname(),
                'has_token' => ! empty($socialiteUser->token),
                'token_length' => strlen($socialiteUser->token ?? ''),
                'has_refresh_token' => ! empty($socialiteUser->refreshToken),
                'expires_in' => $socialiteUser->expiresIn ?? 'null',
            ]);

            // Wikimedia may not share email. Generate a stable synthetic email to satisfy DB constraints.
            $resolvedEmail = $socialiteUser->getEmail();
            if (empty($resolvedEmail)) {
                $usernameFallback = $socialiteUser->getNickname() ?? $socialiteUser->getName() ?? 'user';
                $idFallback = $socialiteUser->getId() ?: uniqid('wm_', true);
                $resolvedEmail = strtolower($usernameFallback.'+'.(string) $idFallback.'@users.noreply.wikimedia.local');
            }

            $userData = [
                'wikimedia_id' => $socialiteUser->getId(),
                'username' => $socialiteUser->getNickname() ?? $socialiteUser->getName(),
                'real_name' => $socialiteUser->getName(),
                'email' => $resolvedEmail,
                'groups' => [],
                'rights' => [],
                'edit_count' => 0,
                'registration_date' => null,
            ];

            // Store tokens in session (not database for security)
            $accessToken = $socialiteUser->token;
            $refreshToken = $socialiteUser->refreshToken;
            $tokenExpiresAt = $socialiteUser->expiresIn ? now()->addSeconds($socialiteUser->expiresIn) : null;

            if (empty($accessToken)) {
                Log::error('No access token received from Wikimedia OAuth', [
                    'user_data' => $userData,
                ]);
            }

            if (! $userData) {
                return redirect()->route('auth.login')
                    ->withErrors(['wikimedia' => 'Failed to authenticate with Wikimedia.']);
            }

            // Find or create user
            $user = $this->findOrCreateUser($userData);

            // Log in the user
            Auth::login($user);

            // Store tokens securely in session
            session([
                'wikimedia_access_token' => $accessToken,
                'wikimedia_refresh_token' => $refreshToken,
                'wikimedia_token_expires_at' => $tokenExpiresAt?->toISOString(),
            ]);

            // Update user's Wikimedia data (without tokens)
            $this->updateUserWikimediaData($user, $userData);

            Log::info('User logged in via Wikimedia', [
                'user_id' => $user->id,
                'wikimedia_username' => $user->wikimedia_username,
                'session_id' => session()->getId(),
                'is_mobile_safari' => $isMobileSafari,
            ]);

            // For mobile Safari, use a different approach - pass auth token in URL
            if ($isMobileSafari) {
                // Generate a temporary auth token for mobile Safari
                $authToken = hash('sha256', $user->id . time() . random_bytes(16));
                
                // Store the auth token temporarily (5 minutes) with user data and access token
                $userData = [
                    'user_id' => $user->id,
                    'wikimedia_access_token' => session('wikimedia_access_token'),
                    'wikimedia_refresh_token' => session('wikimedia_refresh_token'),
                    'wikimedia_token_expires_at' => session('wikimedia_token_expires_at'),
                ];
                
                cache()->put('mobile_safari_auth_' . $authToken, $userData, 300);
                
                Log::info('Mobile Safari auth token generated with access token', [
                    'user_id' => $user->id,
                    'auth_token' => $authToken,
                    'has_access_token' => !empty($userData['wikimedia_access_token']),
                    'session_id' => session()->getId(),
                ]);
                
                // Redirect with auth token for mobile Safari
                return redirect()->to(route('monuments.map') . '?auth_token=' . $authToken)
                    ->with('success', 'Successfully logged in with Wikimedia!')
                    ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0');
            }

            return redirect()->intended(route('monuments.map'))
                ->with('success', 'Successfully logged in with Wikimedia!');

        } catch (\Exception $e) {
            Log::error('Wikimedia OAuth callback error', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'callback_data' => $request->all(),
                'user_agent' => $request->userAgent(),
                'has_state' => $request->has('state'),
                'has_code' => $request->has('code'),
                'session_id' => session()->getId(),
            ]);

            return redirect()->route('auth.login')
                ->withErrors(['wikimedia' => 'Authentication failed. Please try again.']);
        }
    }

    /**
     * Logout the user.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('monuments.map')
            ->with('success', 'Successfully logged out.');
    }

    /**
     * Check if the user agent is mobile Safari.
     */
    private function isMobileSafari(string $userAgent): bool
    {
        return preg_match('/Mobile\/.*Safari/', $userAgent) && 
               !preg_match('/CriOS|FxiOS|EdgiOS/', $userAgent);
    }

    /**
     * Find or create a user based on Wikimedia data.
     */
    private function findOrCreateUser(array $userData): User
    {
        // Try to find user by Wikimedia ID first
        if (! empty($userData['wikimedia_id'])) {
            $user = User::where('wikimedia_id', $userData['wikimedia_id'])->first();
            if ($user) {
                return $user;
            }
        }

        // Try to find user by Wikimedia username
        if (! empty($userData['username'])) {
            $user = User::where('wikimedia_username', $userData['username'])->first();
            if ($user) {
                return $user;
            }
        }

        // Create new user
        return User::create([
            'name' => $userData['real_name'] ?? $userData['username'] ?? 'Wikimedia User',
            'email' => $userData['email'] ?? null,
            'wikimedia_id' => $userData['wikimedia_id'],
            'wikimedia_username' => $userData['username'],
            'wikimedia_real_name' => $userData['real_name'],
            'wikimedia_groups' => $userData['groups'],
            'wikimedia_rights' => $userData['rights'],
            'wikimedia_edit_count' => $userData['edit_count'],
            'wikimedia_registration_date' => $userData['registration_date'],
            'has_commons_edit_permission' => $this->wikimediaAuth->hasCommonsEditPermission($userData['username']),
            'last_wikimedia_sync' => now(),
        ]);
    }

    /**
     * Update user's Wikimedia data.
     */
    private function updateUserWikimediaData(User $user, array $userData): void
    {
        $updateData = [
            'wikimedia_real_name' => $userData['real_name'] ?? $user->wikimedia_real_name,
            'wikimedia_groups' => $userData['groups'] ?? $user->wikimedia_groups,
            'wikimedia_rights' => $userData['rights'] ?? $user->wikimedia_rights,
            'wikimedia_edit_count' => $userData['edit_count'] ?? $user->wikimedia_edit_count,
            'wikimedia_registration_date' => $userData['registration_date'] ?? $user->wikimedia_registration_date,
            'has_commons_edit_permission' => $this->wikimediaAuth->hasCommonsEditPermission($userData['username']),
            'last_wikimedia_sync' => now(),
        ];

        Log::info('Updating user Wikimedia data', [
            'user_id' => $user->id,
            'wikimedia_username' => $userData['username'],
        ]);

        $user->update($updateData);
    }

    /**
     * Show user profile with Wikimedia information.
     */
    public function profile(): View
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        // Fetch live Commons data if user is connected
        $commonsUserInfo = null;
        $recentUploads = [];

        if ($user->isWikimediaConnected() && $user->wikimedia_username) {
            $commonsUserInfo = $this->commonsService->getUserInfo($user->wikimedia_username);
            $recentUploads = $this->commonsService->getUserUploads($user->wikimedia_username, 12);
        }

        return view('auth.profile', compact('user', 'commonsUserInfo', 'recentUploads'));
    }

    /**
     * Sync user's Wikimedia data.
     */
    public function syncWikimediaData(): RedirectResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->isWikimediaConnected()) {
            return redirect()->route('auth.profile')
                ->withErrors(['wikimedia' => 'User is not connected to Wikimedia.']);
        }

        try {
            $userData = [
                'wikimedia_id' => $user->wikimedia_id,
                'username' => $user->wikimedia_username,
                'real_name' => $user->wikimedia_real_name,
                'email' => $user->email,
                'groups' => $user->wikimedia_groups ?? [],
                'rights' => $user->wikimedia_rights ?? [],
                'edit_count' => $user->wikimedia_edit_count ?? 0,
                'registration_date' => $user->wikimedia_registration_date,
            ];

            if ($userData) {
                $this->updateUserWikimediaData($user, $userData);

                return redirect()->route('auth.profile')
                    ->with('success', 'Wikimedia data synced successfully.');
            }

            return redirect()->route('auth.profile')
                ->withErrors(['wikimedia' => 'Failed to sync Wikimedia data.']);

        } catch (\Exception $e) {
            Log::error('Wikimedia data sync error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('auth.profile')
                ->withErrors(['wikimedia' => 'Failed to sync Wikimedia data.']);
        }
    }
}
