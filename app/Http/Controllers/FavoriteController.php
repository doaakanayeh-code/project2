<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\ProviderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FavoriteController extends Controller
{
    /**
     * إضافة أو إزالة من المفضلة (Toggle)
     */
    public function toggle($providerServiceId)
    {
        try {
            // التحقق من وجود الخدمة أولاً
            ProviderService::findOrFail($providerServiceId);

            $favorite = Favorite::where([
                'user_id'             => Auth::id(),
                'provider_service_id' => $providerServiceId,
            ])->first();

            if ($favorite) {
                $favorite->delete();
                return response()->json([
                    'status'      => true,
                    'message'     => __('messages.favorite_removed'),
                    'is_favorite' => false,
                ], 200);
            }

            Favorite::create([
                'user_id'             => Auth::id(),
                'provider_service_id' => $providerServiceId,
            ]);

            return response()->json([
                'status'      => true,
                'message'     => __('messages.favorite_added'),
                'is_favorite' => true,
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => false,
                'message' => __('messages.service_not_found')
            ], 404);
        } catch (\Exception $e) {
            Log::error('[TOGGLE FAVORITE ERROR] ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => __('messages.error_processing_favorite')
            ], 500);
        }
    }

    /**
     * التحقق اللحظي
     */
    public function check($providerServiceId)
    {
        try {
            $exists = Favorite::where('user_id', Auth::id())
                ->where('provider_service_id', $providerServiceId)
                ->exists();

            return response()->json([
                'status'      => true,
                'is_favorite' => $exists
            ], 200);

        } catch (\Exception $e) {
            Log::error('[CHECK FAVORITE ERROR] ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => __('messages.general_error')
            ], 500);
        }
    }

    /**
     * عرض قائمة مفضلاتي بتنسيق موحد
     */
    public function index()
    {
        try {
            $favorites = Favorite::with([
                'providerService.service',
                'providerService.location.city',
                'providerService.images'
            ])
            ->where('user_id', Auth::id())
            ->whereHas('providerService')
            ->latest()
            ->get();

            return response()->json([
                'status'  => true,
                'message' => __('messages.favorites_fetched_success'),
                'data'    => $favorites
            ], 200);

        } catch (\Exception $e) {
            Log::error('[FETCH FAVORITES ERROR] ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => __('messages.general_error')
            ], 500);
        }
    }
}