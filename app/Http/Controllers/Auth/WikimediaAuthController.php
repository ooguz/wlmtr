<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WikimediaAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class WikimediaAuthController extends Controller
{
    public function __construct(
        private WikimediaAuthService $wikimediaAuth
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
    public function redirectToWikimedia(): RedirectResponse
    {
        try {
            return Socialite::driver('wikimedia')->redirect();
        } catch (\Exception $e) {
            Log::error('Wikimedia OAuth redirect error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')
                ->withErrors(['wikimedia' => 'Wikimedia authentication is currently unavailable.']);
        }
    }

    /**
     * Handle the Wikimedia OAuth callback.
     */
    public function handleWikimediaCallback(Request $request): RedirectResponse
    {
        try {
            $socialiteUser = Socialite::driver('wikimedia')->stateless()->user();

            $userData = [
                'wikimedia_id' => $socialiteUser->getId(),
                'username' => $socialiteUser->getNickname() ?? $socialiteUser->getName(),
                'real_name' => $socialiteUser->getName(),
                'email' => $socialiteUser->getEmail(),
                'groups' => [],
                'rights' => [],
                'edit_count' => 0,
                'registration_date' => null,
            ];

            if (! $userData) {
                return redirect()->route('login')
                    ->withErrors(['wikimedia' => 'Failed to authenticate with Wikimedia.']);
            }

            // Find or create user
            $user = $this->findOrCreateUser($userData);

            // Log in the user
            Auth::login($user);

            // Update user's Wikimedia data
            $this->updateUserWikimediaData($user, $userData);

            Log::info('User logged in via Wikimedia', [
                'user_id' => $user->id,
                'wikimedia_username' => $user->wikimedia_username,
            ]);

            return redirect()->intended(route('monuments.map'))
                ->with('success', 'Successfully logged in with Wikimedia!');

        } catch (\Exception $e) {
            Log::error('Wikimedia OAuth callback error', [
                'error' => $e->getMessage(),
                'callback_data' => $request->all(),
            ]);

            return redirect()->route('login')
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
        $user->update([
            'wikimedia_real_name' => $userData['real_name'] ?? $user->wikimedia_real_name,
            'wikimedia_groups' => $userData['groups'] ?? $user->wikimedia_groups,
            'wikimedia_rights' => $userData['rights'] ?? $user->wikimedia_rights,
            'wikimedia_edit_count' => $userData['edit_count'] ?? $user->wikimedia_edit_count,
            'wikimedia_registration_date' => $userData['registration_date'] ?? $user->wikimedia_registration_date,
            'has_commons_edit_permission' => $this->wikimediaAuth->hasCommonsEditPermission($userData['username']),
            'last_wikimedia_sync' => now(),
        ]);
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

        return view('auth.profile', compact('user'));
    }

    /**
     * Sync user's Wikimedia data.
     */
    public function syncWikimediaData(): RedirectResponse
    {
        $user = Auth::user();

        if (! $user || ! $user->isWikimediaConnected()) {
            return redirect()->route('profile')
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

                return redirect()->route('profile')
                    ->with('success', 'Wikimedia data synced successfully.');
            }

            return redirect()->route('profile')
                ->withErrors(['wikimedia' => 'Failed to sync Wikimedia data.']);

        } catch (\Exception $e) {
            Log::error('Wikimedia data sync error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('profile')
                ->withErrors(['wikimedia' => 'Failed to sync Wikimedia data.']);
        }
    }
}
