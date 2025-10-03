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
        
        // For mobile Safari, also encode verifier in state to prevent session loss
        if ($state) {
            $stateData = json_decode(base64_decode($state), true) ?? [];
            $stateData['pkce_verifier'] = $verifier;
            $fields['state'] = base64_encode(json_encode($stateData));
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
            $stateData = json_decode(base64_decode(request()->input('state')), true);
            $verifier = $stateData['pkce_verifier'] ?? null;
            
            \Log::info('PKCE verifier recovered from state parameter', [
                'has_state' => request()->has('state'),
                'has_verifier' => !empty($verifier),
                'user_agent' => request()->userAgent(),
            ]);
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
}
