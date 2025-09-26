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
    protected $scopes = [];

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
}
