<?php

namespace App\Http\Controllers;

use App\Models\EventItem;
use App\Models\Review;
use App\Models\ProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    /**
     * عرض جميع تقييمات مقدم خدمة معين (عامة)
     */
    public function getProviderReviews($providerServiceId)
    {
        try {
            $reviews = Review::with('user')
                ->where('provider_service_id', $providerServiceId)
                ->latest()
                ->paginate(10);

            $average = Review::where('provider_service_id', $providerServiceId)->avg('rating') ?? 0;
            $count = Review::where('provider_service_id', $providerServiceId)->count();


              // ✅ توزيع النجوم (كم تقييم لكل درجة من 1 لـ 5)
        $ratingBreakdown = Review::where('provider_service_id', $providerServiceId)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating'); // بيرجع collection: [5 => 3, 4 => 1, ...]

        $breakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $breakdown[$i] = $ratingBreakdown[$i] ?? 0;
        }



            // ✅ تجهيز البيانات يدوياً (بدون Resource)
            $reviewsData = $reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                    'user' => $review->user ? [
                        'id' => $review->user->id,
                        'name' => $review->user->name,
                        'profile_image' => $review->user->profile_image ?? null,
                    ] : null,
                ];
            });

            return response()->json([
                'status' => true,
                'data' => [
                    'average_rating' => round($average, 1),
                    'total_reviews' => $count,
                    'reviews' => $reviewsData,
                    'rating_breakdown' => $breakdown,
                    'pagination' => [
                        'current_page' => $reviews->currentPage(),
                        'last_page' => $reviews->lastPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $reviews->total(),
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('[GET REVIEWS ERROR] ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب التقييمات'
            ], 500);
        }
    }

    /**
     * إضافة تقييم جديد (معدل لتحديث ProviderService)
     */
    public function store(Request $request)
    {
        $request->validate([
            'event_item_id' => 'required|exists:event_items,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $booking = EventItem::with('event')
                ->findOrFail($request->event_item_id);

            // 1. التأكد أن الحجز يخص المستخدم
            if ($booking->event->user_id !== Auth::id()) {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكنك تقييم هذا الحجز'
                ], 403);
            }

            // 2. يجب أن تكون الحالة 'completed'
            if ($booking->status !== 'completed') {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكنك تقييم خدمة لم تكتمل بعد'
                ], 422);
            }

            // 3. منع التقييم مرتين
            if ($booking->review) {
                return response()->json([
                    'status' => false,
                    'message' => 'لقد قمت بتقييم هذه الخدمة مسبقاً'
                ], 422);
            }

            // 4. حفظ التقييم
            $review = Review::create([
                'user_id' => Auth::id(),
                'provider_service_id' => $booking->provider_service_id,
                'event_item_id' => $booking->id,
                'rating' => $request->rating,
                'comment' => $request->comment
            ]);

            // ============================================================
            // ✅ تحديث rating و review_count في ProviderService
            // ============================================================
            $providerService = ProviderService::find($booking->provider_service_id);
            if ($providerService) {
                $providerService->updateRatings();
            }
            // ============================================================

            // ✅ إرجاع البيانات بدون استخدام Resource
            return response()->json([
                'status' => true,
                'message' => 'تم إرسال تقييمك بنجاح',
                'data' => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('[STORE REVIEW ERROR] ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء إرسال التقييم'
            ], 500);
        }
    }



}