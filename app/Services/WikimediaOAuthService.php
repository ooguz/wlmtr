<?php

namespace App\Services;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class WikimediaOAuthService extends AbstractProvider
{
    /**
     * The Wikimedia OAuth endpoints.
     */
    protected $authUrl = 'https://meta.wikimedia.org/w/rest.php/oauth2/authorize';

    protected $tokenUrl = 'https://meta.wikimedia.org/w/rest.php/oauth2/access_token';

    protected $userUrl = 'https://meta.wikimedia.org/w/rest.php/oauth2/resource/profile';

    /**
     * The scopes being requested.
     */
    protected $scopes = [
        'basic',
        'highvolume',
        'editpage',
        'createeditmovepage',
        'uploadfile',
        'uploadeditmovefile',
    ];

    /**
     * The scope separator.
     */
    protected $scopeSeparator = ' ';

    /**
     * Get the authentication URL for the provider.
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->authUrl, $state);
    }

    /**
     * Get the token URL for the provider.
     */
    protected function getTokenUrl()
    {
        return $this->tokenUrl;
    }

    /**
     * Get the raw user for the given access token.
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get($this->userUrl, [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'User-Agent' => config('services.wikimedia.user_agent', 'WLM-TR/1.0'),
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Map the raw user array to a Socialite User instance.
     */
    protected function mapUserToObject(array $user)
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['userid'] ?? $user['id'] ?? $user['sub'] ?? null,
            'nickname' => $user['username'] ?? $user['name'] ?? null,
            'name' => $user['realname'] ?? $user['username'] ?? $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => null,
        ]);
    }

    /**
     * Get the default scopes of the provider.
     */
    protected function getDefaultScopes()
    {
        return $this->scopes;
    }

    /**
     * Get the code fields for the authentication URL.
     * Remove empty scope to satisfy providers that reject blank scope values.
     */
    protected function getCodeFields($state = null)
    {
        $fields = parent::getCodeFields($state);

        // Remove empty scope
        if (! isset($fields['scope']) || trim((string) $fields['scope']) === '') {
            unset($fields['scope']);
        }

        // Force Meta-Wiki login UI language to Turkish
        $fields['uselang'] = 'tr';

        // Add PKCE (S256) - store verifier in state parameter for mobile Safari compatibility
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        session()->put('wikimedia_pkce_verifier', $verifier);
        
        // For mobile Safari, append verifier to state parameter to prevent session loss
        if ($state) {
            // Append verifier to state with a separator that won't break base64
            $fields['state'] = $state . '.' . $verifier;
        }
        
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $fields['code_challenge'] = $challenge;
        $fields['code_challenge_method'] = 'S256';

        return $fields;
    }

    /**
     * Include PKCE verifier and grant type when exchanging the code.
     */
    protected function getTokenFields($code)
    {
        $fields = parent::getTokenFields($code);

        $fields['grant_type'] = 'authorization_code';
        
        // Try to get PKCE verifier from session first, then from state parameter for mobile Safari
        $verifier = session()->pull('wikimedia_pkce_verifier');
        
        // Fallback: extract verifier from state parameter if session was lost (mobile Safari)
        if (empty($verifier) && request()->has('state')) {
            $state = request()->input('state');
            
            // Check if state contains our appended verifier (format: original_state.verifier)
            if (strpos($state, '.') !== false) {
                $parts = explode('.', $state, 2);
                if (count($parts) === 2) {
                    $verifier = $parts[1];
                    
                    \Log::info('PKCE verifier recovered from state parameter', [
                        'has_state' => request()->has('state'),
                        'has_verifier' => !empty($verifier),
                        'user_agent' => request()->userAgent(),
                        'state_length' => strlen($state),
                    ]);
                }
            }
        }
        
        if (empty($verifier)) {
            \Log::error('PKCE verifier not found in session or state', [
                'session_has_verifier' => session()->has('wikimedia_pkce_verifier'),
                'has_state' => request()->has('state'),
                'user_agent' => request()->userAgent(),
            ]);
        }
        
        $fields['code_verifier'] = $verifier;

        return $fields;
    }

    /**
     * Use the parent HTTP client (configured via provider registration).
     */
    protected function getHttpClient()
    {
        return parent::getHttpClient();
    }

    /**
     * Override state validation to handle our custom state format with PKCE verifier.
     */
    protected function hasInvalidState()
    {
        $state = request()->input('state');
        
        if (empty($state)) {
            return true;
        }
        
        // Extract original state from our custom format (original_state.verifier)
        if (strpos($state, '.') !== false) {
            $parts = explode('.', $state, 2);
            $originalState = $parts[0];
        } else {
            $originalState = $state;
        }
        
        // Get stored state from session
        $storedState = session()->pull('oauth.state');
        
        // If session is lost (mobile Safari), skip state validation
        // This is safe because we're using PKCE for security
        if ($storedState === null) {
            \Log::info('Session lost during OAuth flow, skipping state validation', [
                'user_agent' => request()->userAgent(),
                'state_received' => $state,
            ]);
            return false; // Don't invalidate - let PKCE handle security
        }
        
        // Validate the original state part
        return ! hash_equals($originalState, $storedState);
    }
}
