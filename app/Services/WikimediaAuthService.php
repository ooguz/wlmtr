<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WikimediaAuthService
{
    private const WIKIMEDIA_OAUTH_URL = 'https://meta.wikimedia.org/w/index.php';
    private const WIKIMEDIA_API_URL = 'https://meta.wikimedia.org/w/api.php';

    /**
     * Get the OAuth authorization URL.
     */
    public function getAuthorizationUrl(): string
    {
        $params = [
            'title' => 'Special:OAuth/authorize',
            'oauth_consumer_key' => config('services.wikimedia.client_id'),
            'oauth_nonce' => $this->generateNonce(),
            'oauth_signature' => $this->generateSignature(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $this->getRequestToken(),
            'oauth_version' => '1.0',
            'state' => Str::random(40),
        ];

        return self::WIKIMEDIA_OAUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Handle the OAuth callback and get user information.
     */
    public function handleCallback(array $callbackData): ?array
    {
        try {
            // Extract user information from the callback
            $userData = $this->getUserInfo($callbackData);
            
            if ($userData) {
                return [
                    'wikimedia_id' => $userData['userid'] ?? null,
                    'username' => $userData['name'] ?? null,
                    'real_name' => $userData['realname'] ?? null,
                    'email' => $userData['email'] ?? null,
                    'groups' => $userData['groups'] ?? [],
                    'rights' => $userData['rights'] ?? [],
                    'edit_count' => $userData['editcount'] ?? 0,
                    'registration_date' => $userData['registration'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Wikimedia OAuth callback error', [
                'error' => $e->getMessage(),
                'callback_data' => $callbackData,
            ]);
        }

        return null;
    }

    /**
     * Get user information from Wikimedia API.
     */
    private function getUserInfo(array $callbackData): ?array
    {
        $response = Http::get(self::WIKIMEDIA_API_URL, [
            'action' => 'query',
            'meta' => 'userinfo',
            'format' => 'json',
            'uiprop' => 'groups|rights|editcount|registration',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['query']['userinfo'] ?? null;
        }

        return null;
    }

    /**
     * Generate a unique nonce for OAuth requests.
     */
    private function generateNonce(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * Generate OAuth signature.
     */
    private function generateSignature(): string
    {
        // Simplified signature generation
        // In production, you would need to implement proper OAuth 1.0a signature
        $baseString = config('services.wikimedia.client_id') . config('services.wikimedia.client_secret') . time();
        return hash_hmac('sha1', $baseString, config('services.wikimedia.client_secret'));
    }

    /**
     * Get request token for OAuth flow.
     */
    private function getRequestToken(): string
    {
        // In a real implementation, you would make a request to get the request token
        // For now, we'll return a placeholder
        return Str::random(32);
    }

    /**
     * Verify if a user has edit permissions on Wikimedia Commons.
     */
    public function hasCommonsEditPermission(string $username): bool
    {
        try {
            $response = Http::get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'meta' => 'userinfo',
                'format' => 'json',
                'uiprop' => 'groups|rights',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $userInfo = $data['query']['userinfo'] ?? [];
                
                // Check if user is logged in and has edit permissions
                return !empty($userInfo['name']) && 
                       in_array('edit', $userInfo['rights'] ?? []);
            }
        } catch (\Exception $e) {
            Log::error('Error checking Commons edit permission', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Get user's upload history on Wikimedia Commons.
     */
    public function getUserUploadHistory(string $username): array
    {
        try {
            $response = Http::get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'list' => 'usercontribs',
                'ucuser' => $username,
                'ucnamespace' => 6, // File namespace
                'uclimit' => 50,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['query']['usercontribs'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('Error getting user upload history', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }
} 