<?php

namespace App\Http\Controllers;

use App\Http\Requests\PhotoUploadRequest;
use App\Models\Monument;
use App\Services\WikimediaCommonsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PhotoUploadController extends Controller
{
    public function __construct(
        private WikimediaCommonsService $commonsService
    ) {}

    /**
     * Upload a photo to Wikimedia Commons.
     */
    public function upload(PhotoUploadRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Debug CSRF token for Safari
            Log::info('Photo upload request debug', [
                'user_id' => $user?->id,
                'authenticated' => auth()->check(),
                'csrf_token_from_header' => $request->header('X-CSRF-TOKEN'),
                'csrf_token_from_form' => $request->input('_token'),
                'session_id' => session()->getId(),
                'user_agent' => $request->userAgent(),
                'is_mobile_safari' => preg_match('/Mobile\/.*Safari/', $request->userAgent()) &&
                                    ! preg_match('/CriOS|FxiOS|EdgiOS/', $request->userAgent()),
            ]);

            // Check if user is authenticated
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Giriş yapmanız gerekiyor.',
                ], 401);
            }

            $monument = Monument::findOrFail($request->monument_id);

            // Check if user has a valid access token in session
            $accessToken = $user->getWikimediaAccessToken();

            if (! $accessToken) {
                Log::error('User has no wikimedia access token in session', [
                    'user_id' => $user->id,
                    'wikimedia_id' => $user->wikimedia_id,
                    'wikimedia_username' => $user->wikimedia_username,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Wikimedia erişim anahtarınız yok. Lütfen çıkış yapıp tekrar giriş yapın.',
                    'debug' => config('app.debug') ? [
                        'has_wikimedia_id' => ! empty($user->wikimedia_id),
                        'wikimedia_username' => $user->wikimedia_username,
                    ] : null,
                ], 401);
            }

            // Check if token is expired
            if ($user->isWikimediaTokenExpired()) {
                Log::warning('User wikimedia token expired', [
                    'user_id' => $user->id,
                    'expires_at' => $user->getWikimediaTokenExpiresAt(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Wikimedia oturumunuz sona erdi. Lütfen çıkış yapıp tekrar giriş yapın.',
                ], 401);
            }

            // Build description with templates
            $description = $this->buildDescription($monument, $request->description);

            // Prepare categories
            $categories = $this->prepareCategories($monument, $request->categories);

            // Upload to Commons
            $result = $this->commonsService->uploadPhoto(
                accessToken: $accessToken,
                user: $user,
                photo: $request->file('photo'),
                title: $request->title,
                description: $description,
                date: $request->date,
                categories: $categories,
                monument: $monument
            );

            if ($result['success']) {
                Log::info('Photo uploaded to Commons', [
                    'user_id' => $user->id,
                    'monument_id' => $monument->id,
                    'filename' => $result['filename'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Fotoğraf başarıyla Wikimedia Commons\'a yüklendi!',
                    'data' => $result,
                ]);
            }

            Log::warning('Photo upload failed', [
                'user_id' => $user->id,
                'monument_id' => $monument->id,
                'error' => $result['error'] ?? 'Unknown error',
                'details' => $result['details'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Yükleme sırasında bir hata oluştu.',
                'details' => $result['details'] ?? null,
            ], 500);

        } catch (\Exception $e) {
            Log::error('Photo upload error', [
                'user_id' => $request->user()?->id,
                'monument_id' => $request->monument_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Yükleme sırasında bir hata oluştu: '.$e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Build photo description with Wikidata and app templates.
     */
    private function buildDescription(Monument $monument, ?string $userDescription): string
    {
        $description = $userDescription ?? $monument->primary_name;

        // Add Wikidata template if available
        if ($monument->wikidata_id) {
            $description .= "\n{{on Wikidata|{$monument->wikidata_id}}}";
            $description .= "\n{{Load via app WLM.tr|year=".date('Y').'|source='.(request()->userAgent() && str_contains(strtolower(request()->userAgent()), 'mobile') ? 'mobile' : 'desktop').'}}';
        }

        return $description;
    }

    /**
     * Prepare categories array.
     */
    private function prepareCategories(Monument $monument, ?array $userCategories): array
    {
        $categories = [];

        // Add monument's commons category if available
        if ($monument->commons_category) {
            $categories[] = $monument->commons_category;
        }

        // Add location-based category
        if ($monument->province) {
            $categories[] = $monument->province;
        } elseif ($monument->location_hierarchy_tr) {
            $locationParts = explode(',', $monument->location_hierarchy_tr);
            if (! empty($locationParts[0])) {
                $categories[] = trim($locationParts[0]);
            }
        }

        // Add user-provided categories
        if ($userCategories) {
            $categories = array_merge($categories, $userCategories);
        }

        // Remove duplicates and empty values
        return array_unique(array_filter($categories));
    }
}
