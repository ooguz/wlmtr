<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WikimediaAuthService
{
    private const COMMONS_OAUTH_URL = 'https://commons.wikimedia.org/w/index.php';

    private const COMMONS_API_URL = 'https://commons.wikimedia.org/w/api.php';

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

        return self::COMMONS_OAUTH_URL.'?'.http_build_query($params);
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
            Log::error('Wikimedia Commons OAuth callback error', [
                'error' => $e->getMessage(),
                'callback_data' => $callbackData,
            ]);
        }

        return null;
    }

    /**
     * Get user information from Wikimedia Commons API.
     */
    private function getUserInfo(array $callbackData): ?array
    {
        $response = Http::get(self::COMMONS_API_URL, [
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
        $baseString = config('services.wikimedia.client_id').config('services.wikimedia.client_secret').time();

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
        // For development, return true for mock users
        if (app()->environment('local')) {
            return true;
        }

        try {
            $response = Http::get(self::COMMONS_API_URL, [
                'action' => 'query',
                'meta' => 'userinfo',
                'format' => 'json',
                'uiprop' => 'groups|rights',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $userInfo = $data['query']['userinfo'] ?? [];

                // Check if user is logged in and has edit permissions
                return ! empty($userInfo['name']) &&
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
        // For development, return mock data
        if (app()->environment('local')) {
            return [
                [
                    'title' => 'File:WLM_Turkey_Test_1.jpg',
                    'timestamp' => now()->subDays(5)->toISOString(),
                    'comment' => 'Test upload for Wiki Loves Monuments Turkey',
                    'size' => 2048576,
                ],
                [
                    'title' => 'File:WLM_Turkey_Test_2.jpg',
                    'timestamp' => now()->subDays(10)->toISOString(),
                    'comment' => 'Another test upload for WLM Turkey',
                    'size' => 1536000,
                ],
                [
                    'title' => 'File:Monument_Test_Photo.jpg',
                    'timestamp' => now()->subDays(15)->toISOString(),
                    'comment' => 'Test monument photo upload',
                    'size' => 3072000,
                ],
            ];
        }

        try {
            $response = Http::get(self::COMMONS_API_URL, [
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

    /**
     * Get user's Commons-specific statistics.
     */
    public function getUserCommonsStats(string $username): array
    {
        // For development, return mock stats
        if (app()->environment('local')) {
            return [
                'total_uploads' => 45,
                'total_views' => 12500,
                'featured_pictures' => 2,
                'quality_images' => 5,
                'valued_images' => 8,
                'last_upload' => now()->subDays(2)->toISOString(),
            ];
        }

        try {
            // Get user's upload count
            $response = Http::get(self::COMMONS_API_URL, [
                'action' => 'query',
                'list' => 'usercontribs',
                'ucuser' => $username,
                'ucnamespace' => 6,
                'uclimit' => 1,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $contribs = $data['query']['usercontribs'] ?? [];

                return [
                    'total_uploads' => count($contribs),
                    'last_upload' => $contribs[0]['timestamp'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error getting user Commons stats', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }
}
