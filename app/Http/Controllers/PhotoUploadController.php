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

            // Check if user is authenticated
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Giriş yapmanız gerekiyor.',
                ], 401);
            }

            $monument = Monument::findOrFail($request->monument_id);

            // Check if user has a valid access token
            if (! $user->wikimedia_access_token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wikimedia bağlantınız yok. Lütfen çıkış yapıp tekrar giriş yapın.',
                ], 401);
            }

            // Check if token is expired
            if ($user->isWikimediaTokenExpired()) {
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

            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Yükleme sırasında bir hata oluştu.',
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
            $description .= "\n{{Load via app WLM.tr|year=".date('Y').'|source=quickupload}}';
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
