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

    /**
     * Get the authentication URL for the provider.
     */
    protected function buildAuthUrlFromBase($url, $state)
    {
        $query = http_build_query([
            'title' => 'Special:OAuth/authorize',
            'oauth_consumer_key' => $this->clientId,
            'oauth_nonce' => $this->getNonce(),
            'oauth_signature' => $this->getSignature(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $this->getRequestToken(),
            'oauth_version' => '1.0',
            'state' => $state,
        ]);

        return $url.'?'.$query;
    }

    /**
     * Get the request token for the application.
     */
    protected function getRequestToken()
    {
        $response = $this->getHttpClient()->post($this->tokenUrl, [
            'form_params' => [
                'title' => 'Special:OAuth/initiate',
                'oauth_consumer_key' => $this->clientId,
                'oauth_nonce' => $this->getNonce(),
                'oauth_signature' => $this->getSignature(),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => time(),
                'oauth_version' => '1.0',
            ],
        ]);

        parse_str($response->getBody(), $result);

        return $result['oauth_token'] ?? null;
    }

    /**
     * Get the access token for the given code.
     */
    public function getAccessToken($code)
    {
        $response = $this->getHttpClient()->post($this->tokenUrl, [
            'form_params' => [
                'title' => 'Special:OAuth/token',
                'oauth_consumer_key' => $this->clientId,
                'oauth_nonce' => $this->getNonce(),
                'oauth_signature' => $this->getSignature(),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => time(),
                'oauth_token' => $code,
                'oauth_verifier' => $this->request->get('oauth_verifier'),
                'oauth_version' => '1.0',
            ],
        ]);

        parse_str($response->getBody(), $result);

        return $result['oauth_token'] ?? null;
    }

    /**
     * Get a unique nonce for the request.
     */
    protected function getNonce()
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * Get the signature for the request.
     */
    protected function getSignature()
    {
        // This is a simplified implementation
        // In a real implementation, you would need to properly sign the request
        return hash_hmac('sha1', $this->getSignatureBaseString(), $this->clientSecret);
    }

    /**
     * Get the signature base string.
     */
    protected function getSignatureBaseString()
    {
        // This is a simplified implementation
        // In a real implementation, you would need to build the proper signature base string
        return $this->clientId.$this->clientSecret.time();
    }
}
