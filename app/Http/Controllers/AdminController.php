<?php
namespace App\Http\Controllers;
use App\Exports\TransactionsExport;
use App\Models\ContactMessage; 
use App\Models\Event;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel; // تأكدي أن هذه موجودة في أعلى الملف مع الـ uses

class AdminController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    //عرض كل المستخدمين
public function index()
{
    $users = User::
    // where('is_blocked', false)
                 where('role', 'user') 
                 ->select(
                     'id',
                     'username',
                     'email',
                     'phone',
                     'role',
                     'is_blocked',
                     'created_at'
                 )
                 ->orderBy('id', 'asc')
                 ->get();
    
    return response()->json([
        'users' => $users
    ], 200);
}

/// تعديل بيانات مستخدم من قبل الأدمن
public function updateuser(Request $request, $id)
{
    $user = User::where('role', 'user')->findOrFail($id);

    $data = $request->validate([
        'phone' => 'nullable|string|unique:users,phone,' . $user->id,
        'role'  => 'required|in:user,provider,admin',
    ]);

    if (
        ($data['phone'] ?? $user->phone) == $user->phone &&
        $data['role'] == $user->role
    ) {
        return response()->json([
            'message' => __('admin.no_changes')
        ], 400);
    }

    $user->update([
        'phone' => $data['phone'] ?? $user->phone,
        'role'  => $data['role'],
    ]);

    return response()->json([
        'message' => __('admin.user_updated'),
        'user' => [
            'id'       => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'phone'    => $user->phone,
        ]
    ]);
}

/// حذف مستخدم نهائياً من المنصة
public function destroy($id)
{
    $user = User::withTrashed()->where('role', 'user')->find($id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => __('admin.user_not_found')
        ], 404);
    }

    if (auth()->id() == $user->id) {
        return response()->json([
            'success' => false,
            'message' => __('admin.cannot_delete_self')
        ], 400);
    }

    if ($user->trashed()) {
        return response()->json([
            'success' => false,
            'message' => __('admin.already_deleted')
        ], 400);
    }

    $user->tokens()->delete();
    $user->forceDelete();

    return response()->json([
        'success' => true,
        'message' => __('admin.user_deleted')
    ], 200);
}

/// حذف ناعم للمستخدم من المنصة (Soft Delete)
public function softDelete($id)
{
    $user = User::where('role', 'user')->findOrFail($id);

    if (auth()->id() == $user->id) {
        return response()->json([
            'success' => false,
            'message' => __('admin.cannot_delete_self')
        ], 400);
    }

    $user->tokens()->delete();
    $user->delete();

    return response()->json([
        'success' => true,
        'message' => __('admin.user_soft_deleted')
    ], 200);
}

// استرجاع الحساب من سلة المحذوفات
public function restoreUser($id)
{
    // التأكد من استرجاع الحسابات المحذوفة التي تملك رول 'user' فقط
    $user = User::onlyTrashed()->where('role', 'user')->findOrFail($id);
    
    $user->restore();

    return response()->json([
        'success' => true,
        'message' => __('admin.user_restored'),
        'user'    => $user
    ], 200);
}
//حظر مستخدم
public function blockUser($id)
{
    $user = User::findOrFail($id);

    if (auth()->id() == $user->id) {
        return response()->json([
            'success' => false,
            'message' => __('admin.cannot_block_self')
        ], 400);
    }

    $user->update([
        'is_blocked' => true
    ]);

    $user->tokens()->delete();

    return response()->json([
        'success' => true,
        'message' => __('admin.user_blocked'),
        'user' => $user
    ], 200);
}
//الغاء الحظر
public function unblock($id)
{
    $user = User::withTrashed()->findOrFail($id);

    $user->is_blocked = false;
    $user->save();

    return response()->json([
        'success' => true,
        'message' => __('admin.user_unblocked')
    ]);
}

//تقارير
public function getUsersStatistics()
{
$activeUsers = User::where('role', 'user')->where('is_blocked', false)->count(); 
$blockedUsers = User::where('role', 'user')->where('is_blocked', true)->count(); 
$deletedUsers = User::where('role', 'user')->onlyTrashed()->count(); 
$totalUsers = User::where('role', 'user')->withTrashed()->count();
$totalProviders = User::where('role', 'provider')->withTrashed()->count();
$activeProviders = User::where('role', 'provider')->where('is_blocked', false)->count();
$blockedProviders = User::where('role', 'provider')->where('is_blocked', true)->count();
$deletedProviders = User::where('role', 'provider')->onlyTrashed()->count();

    return response()->json([
        'success' => true,
        'users_stats' => [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'blocked' => $blockedUsers,
            'deleted' => $deletedUsers,
        ],
        'providers_stats' => [
            'total' => $totalProviders,
            'active' => $activeProviders,
            'blocked' => $blockedProviders,
            'deleted' => $deletedProviders,
        ]
    ], 200);
}
//عرض مزودي الخدمة
    public function showProviders()
    {
    
        $providers = User::where('role', 'provider')->get();

        return response()->json($providers);
    }

    
//حذف مزود الخدمة
    /**
     * حذف المزود
     */
public function destroyProvider($id)
{
    $provider = User::where('role', 'provider')->find($id);

    if (!$provider) {
        return response()->json([
            'message' => __('admin.provider_not_found')
        ], 404);
    }

    $provider->forceDelete();

    return response()->json([
        'message' => __('admin.provider_deleted')
    ]);
}

//تعديل مزود الخدمة
    public function updateProvider(Request $request, $id)
{
    $provider = User::where('role', 'provider')->findOrFail($id);

    $currentColumn = !empty($provider->email) ? 'email' : 'phone';
    $currentValue = $provider->$currentColumn;

    $rules = [
        'username' => 'required|string|max:255',
    ];

    if ($currentColumn === 'email') {
        $rules['identifier'] = 'required|email|in:' . $currentValue;
    } else {
        $rules['identifier'] = 'required|string|unique:users,phone,' . $id;
    }

    if ($request->has('username') && $request->username !== $provider->username) {
        $rules['id_img_front'] = 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
        $rules['id_img_back']  = 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
    } else {
        $rules['id_img_front'] = 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
        $rules['id_img_back']  = 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048';
    }

    $validated = $request->validate($rules);

    if ($provider->username === $validated['username'] && 
        $currentValue === $validated['identifier'] && 
        !$request->hasFile('id_img_front') && 
        !$request->hasFile('id_img_back')) {
        
        return response()->json([
            'message' => __('admin.provider_no_changes')
        ], 200);
    }

    $updateData = [
        'username' => $validated['username']
    ];

    if ($currentColumn === 'phone') {
        $updateData['phone'] = $validated['identifier'];
    }

    if ($request->hasFile('id_img_front')) {
        $updateData['id_img_front'] = $request->file('id_img_front')->store('ids/front', 'public');
    }
    if ($request->hasFile('id_img_back')) {
        $updateData['id_img_back'] = $request->file('id_img_back')->store('ids/back', 'public');
    }

    $provider->update($updateData);

    return response()->json([
        'message' => __('admin.provider_updated'),
        'data'    => $provider->only(['id', 'username', 'email', 'phone', 'id_img_front', 'id_img_back', 'role'])
    ]);
}

//عرض خدمات مزود الخدمة 
public function getProviderServices($provider_id)
{
    $provider = User::where('role', 'provider')->find($provider_id);

    if (!$provider) {
        return response()->json([
            'message' => __('admin.provider_services_not_found')
        ], 404);
    }

    $services = $provider->services; 

    if ($services->isEmpty()) {
        return response()->json([
            'message' => __('admin.no_services_found'),
            'provider_name' => $provider->username,
            'data' => []
        ], 200);
    }

    return response()->json([
        'message' => __('admin.services_fetched'),
        'provider_name' => $provider->username,
        'data' => $services
    ], 200);
}
//عرض الاعدادات
public function getSettings()
{
    $settings = Setting::pluck('value', 'key');

    $getLogoUrl = function($path) {
        if (!$path) return asset('Royal.png'); 
        return filter_var($path, FILTER_VALIDATE_URL) ? $path : asset('storage/' . $path);
    };

    $getBannerUrl = function($path) {
        if (!$path) return asset('defaults/banner.png'); 
        return filter_var($path, FILTER_VALIDATE_URL) ? $path : asset('storage/' . $path);
    };

    return response()->json([
        'success' => true,
        'settings' => [
            'site_name'  => $settings['site_name'] ?? 'Royal Moments',
            'site_logo'  => $getLogoUrl($settings['site_logo'] ?? null),
            'hero_image' => $getBannerUrl($settings['hero_image'] ?? null),
        ]
    ], 200);
}
//تعديل الاعدادات
public function updateSettings(Request $request)
{
    $request->validate([
        'site_name'  => 'nullable|string|max:255',
        'site_logo'  => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
        'hero_image' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
    ]);

    if ($request->has('site_name')) {
        Setting::updateOrCreate(['key' => 'site_name'], ['value' => $request->site_name]);
    }

    if ($request->hasFile('site_logo')) {
        $oldLogo = Setting::where('key', 'site_logo')->value('value');
        if ($oldLogo) Storage::disk('public')->delete($oldLogo);

        $logoPath = $request->file('site_logo')->store('settings', 'public');
        Setting::updateOrCreate(['key' => 'site_logo'], ['value' => $logoPath]);
    }

    if ($request->hasFile('hero_image')) {
        $oldBanner = Setting::where('key', 'hero_image')->value('value');
        if ($oldBanner) Storage::disk('public')->delete($oldBanner);

        $bannerPath = $request->file('hero_image')->store('settings', 'public');
        Setting::updateOrCreate(['key' => 'hero_image'], ['value' => $bannerPath]);
    }
    return $this->getSettings();
}
//عرض كل التعليقات+التقييمات
// public function getAllComments()
// {
//     $comments = Comment::whereHas('user', function ($query) {
//         $query->where('role', 'user');
//     })
//     ->with(['user:id,username'])
//     ->orderBy('created_at', 'desc')
//     ->get();

//     return response()->json([
//         'success' => true,
//         'comments' => $comments
//     ], 200);
// }
//حذف التعليقات 
// public function destroyComment($id)
// {
//     $comment = Comment::whereHas('user', function ($query) {
//         $query->where('role', 'user');
//     })->find($id);

//     if (!$comment) {
//         return response()->json([
//             'success' => false,
//             'message' => __('admin.comment_not_found')
//         ], 404);
//     }

//     $comment->delete();

//     return response()->json([
//         'success' => true,
//         'message' => __('admin.comment_deleted')
//     ], 200);
// }
//التفاعل مع التعليقات 
// التفاعل مع التعليقات أو التقييمات


public function toggleCommentLike(Request $request, $id)
{
    $request->validate([
        'type' => 'required|in:like,dislike,love'
    ]);

    $review = Review::find($id);

    if (!$review) {
        return response()->json([
            'success' => false,
            'message' => __('admin.comment_not_found')
        ], 404);
    }

    $userId = auth()->id() ?? 1; // معرّف مؤقت للتجربة
    $type = $request->type;
    
    // مفتاح الكاش المميز
    $cacheKey = "mock_reaction_review_{$id}_user_{$userId}";

    // جلب التفاعل الحالي
    $existingReaction = Cache::get($cacheKey);

    if ($existingReaction === $type) {
        // إلغاء التفاعل عند الضغط مجدداً على نفس النوع
        Cache::forget($cacheKey);
        $message = __('admin.like_removed') ?? 'تمت إزالة التفاعل';
        $action = 'removed';
        $currentReaction = null;
    } else {
        // حفظ/تحديث التفاعل الجديد لمدة ساعة
        Cache::put($cacheKey, $type, now()->addHour());
        $message = __('admin.like_added') ?? 'تمت إضافة التفاعل';
        $action = $existingReaction ? 'updated' : 'added';
        $currentReaction = $type;
    }

    return response()->json([
        'success' => true,
        'message' => $message,
        'data'    => [
            'action'           => $action,            // 'added', 'removed', or 'updated'
            'current_reaction' => $currentReaction,   // 'like', 'dislike', 'love', or null
            'review_id'        => (int) $id,
            'user_id'          => $userId
        ]
    ], 200);
}
//انشاء تقييم او تعليق
// public function storeComment(Request $request)
// {
//     $request->validate([
//         'provider_id' => 'required|exists:users,id',
//         'content'     => 'required|string|max:500',
//     ]);

//     $userId = auth()->id();

//     $hasCompletedBooking = Booking::where('user_id', $userId)
//                                   ->where('provider_id', $request->provider_id)
//                                   ->where('status', 'completed')
//                                   ->exists();

//     if (!$hasCompletedBooking) {
//         return response()->json([
//             'success' => false,
//             'message' => __('admin.booking_required')
//         ], 403);
//     }

//     $alreadyCommented = Comment::where('user_id', $userId)
//                                ->where('provider_id', $request->provider_id)
//                                ->exists();

//     if ($alreadyCommented) {
//         return response()->json([
//             'success' => false,
//             'message' => __('admin.comment_already_exists')
//         ], 400);
//     }

//     $comment = Comment::create([
//         'user_id'     => $userId,
//         'provider_id' => $request->provider_id,
//         'content'     => $request->content,
//     ]);

//     return response()->json([
//         'success' => true,
//         'message' => __('admin.comment_published'),
//         'comment' => $comment
//     ], 201);


// }
//فلترة الحجوزات
public function getFilteredBookings(Request $request)
{
    $query = Booking::query()->with(['user:id,username', 'provider:id,username']);

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('provider_id')) {
        $query->where('provider_id', $request->provider_id);
    }

    if ($request->filled('user_id')) {
        $query->where('user_id', $request->user_id);
    }

    if ($request->filled('date')) {
        $query->whereDate('booking_date', $request->date);
    }

    if ($request->filled('date_from') && $request->filled('date_to')) {
        $query->whereBetween('booking_date', [$request->date_from, $request->date_to]);
    }

    $bookings = $query->orderBy('booking_date', 'desc')->get();

    return response()->json([
        'success' => true,
        'count' => $bookings->count(),
        'bookings' => $bookings
    ], 200);
}
//فلترة اليوزرات
public function getFilteredUsers(Request $request)
{
    $query = User::where('role', 'user');

    if ($request->filled('status')) {
        $isBlocked = $request->status === 'blocked' ? true : false;
        $query->where('is_blocked', $isBlocked);
    }

    if ($request->filled('trash') && $request->trash === 'only') {
        $query->onlyTrashed();
    } else {
        $query->withTrashed(); 
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('username', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }

    $users = $query->orderBy('id', 'asc')->get();

    return response()->json([
        'success' => true,
        'count' => $users->count(),
        'users' => $users
    ], 200);
}
//فلترة المزودين
public function getFilteredProviders(Request $request)
{
    $query = User::where('role', 'provider');

    if ($request->filled('status')) {
        $isBlocked = $request->status === 'blocked' ? true : false;
        $query->where('is_blocked', $isBlocked);
    }

    if ($request->filled('category_id')) {
        $query->where('category_id', $request->category_id);
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('username', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }

    $providers = $query->orderBy('id', 'asc')->get();

    return response()->json([
        'success' => true,
        'count' => $providers->count(),
        'providers' => $providers
    ], 200);
}
//فلترة التعليقات
public function getFilteredComments(Request $request)
{
    $query = Comment::whereHas('user', function ($q) {
        $q->where('role', 'user');
    })->with(['user:id,username', 'provider:id,username']);

    if ($request->filled('rating')) {
        $query->where('rating', $request->rating);
    }

    if ($request->filled('provider_id')) {
        $query->where('provider_id', $request->provider_id);
    }

    if ($request->filled('type')) {
        if ($request->type === 'text_only') {
            $query->whereNotNull('content');
        } elseif ($request->type === 'rating_only') {
            $query->whereNull('content');
        }
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('content', 'LIKE', "%{$search}%")
              ->orWhereHas('user', function ($u) use ($search) {
                  $u->where('username', 'LIKE', "%{$search}%");
              });
        });
    }

    $comments = $query->orderBy('created_at', 'desc')->get();

    return response()->json([
        'success' => true,
        'count' => $comments->count(),
        'comments' => $comments
    ], 200);
}
// اضافة زبون
public function addUser(Request $request)
{
    $request->validate([
        'username'     => 'required|string|max:255',
        'identifier'   => 'required|string',
        'password'     => 'required|string|min:8',
        'id_img_front' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        'id_img_back'  => 'required|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    $identifier = $request->identifier;

    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $email = $identifier;
        $phone = null;
        
        if (User::where('email', $email)->exists()) {
            return response()->json(['success' => false, 'message' => __('admin.email_already_used')], 422);
        }
    } else {
        $phone = $identifier;
        $email = null;
        
        if (User::where('phone', $phone)->exists()) {
            return response()->json(['success' => false, 'message' => __('admin.phone_already_used')], 422);
        }
    }

    $frontImgPath = null;
    $backImgPath = null;

    if ($request->hasFile('id_img_front')) {
        $frontImgPath = $request->file('id_img_front')->store('settings', 'public');
    }
    if ($request->hasFile('id_img_back')) {
        $backImgPath = $request->file('id_img_back')->store('settings', 'public');
    }

    $user = User::create([
        'username'     => $request->username,
        'email'        => $email,
        'phone'        => $phone,
        'password'     => Hash::make($request->password),
        'role'         => 'user', 
        'is_blocked'   => false,  
        'id_img_front' => $frontImgPath, 
        'id_img_back'  => $backImgPath,
    ]);

    return response()->json([
        'success' => true,
        'message' => __('admin.user_added_successfully'),
        'user'    => $user
    ], 201);
}

// اضافة مزود خدمة 
public function addProvider(Request $request)
{
    $request->validate([
        'username'     => 'required|string|max:255',
        'identifier'   => 'required|string',
        'password'     => 'required|string|min:8',
        'id_img_front' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        'id_img_back'  => 'required|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    $identifier = $request->identifier;

    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $email = $identifier;
        $phone = null;
        
        if (User::where('email', $email)->exists()) {
            return response()->json(['success' => false, 'message' => __('admin.email_already_used')], 422);
        }
    } else {
        $phone = $identifier;
        $email = null;
        
        if (User::where('phone', $phone)->exists()) {
            return response()->json(['success' => false, 'message' => __('admin.phone_already_used')], 422);
        }
    }

    $frontImgPath = null;
    $backImgPath = null;

    if ($request->hasFile('id_img_front')) {
        $frontImgPath = $request->file('id_img_front')->store('settings', 'public');
    }

    if ($request->hasFile('id_img_back')) {
        $backImgPath = $request->file('id_img_back')->store('settings', 'public');
    }

    $user = User::create([
        'username'     => $request->username,
        'email'        => $email,
        'phone'        => $phone,
        'password'     => Hash::make($request->password),
        'role'         => 'provider', 
        'is_blocked'   => false,  
        'id_img_front' => $frontImgPath, 
        'id_img_back'  => $backImgPath,
    ]);

    return response()->json([
        'success' => true,
        'message' => __('admin.provider_added_successfully'),
        'user'    => $user
    ], 201);
}

// عرض كل الحجوزات
// public function getAllBookings(Request $request)
// {
//     $query = Booking::query()->with(['user:id,username', 'provider:id,username']);

//     if ($request->filled('status')) {
//         $query->where('status', $request->status);
//     }
//     if ($request->filled('provider_id')) {
//         $query->where('provider_id', $request->provider_id);
//     }
//     if ($request->filled('user_id')) {
//         $query->where('user_id', $request->user_id);
//     }
//     if ($request->filled('date')) {
//         $query->whereDate('booking_date', $request->date);
//     }
//     if ($request->filled('date_from') && $request->filled('date_to')) {
//         $query->whereBetween('booking_date', [$request->date_from, $request->date_to]);
//     }

//     $bookings = $query->orderBy('booking_date', 'desc')->get();

//     return response()->json([
//         'success' => true,
//         'count' => $bookings->count(),
//         'bookings' => $bookings
//     ], 200);
// }

// تعديل حالة أي حجز
// public function updateBookingStatus(Request $request, $id)
// {
//     $request->validate([
//         'status' => 'required|string|in:pending,confirmed,cancelled,completed'
//     ]);

//     $booking = Booking::find($id);

//     if (!$booking) {
//         return response()->json(['success' => false, 'message' => __('admin.booking_not_found')], 404);
//     }

//     if ($request->status === 'confirmed') {
//         Booking::where('provider_id', $booking->provider_id)
//             ->where('id', '!=', $booking->id)
//             ->where('booking_date', $booking->booking_date)
//             ->where('status', 'pending')
//             ->update(['status' => 'cancelled']);
//     }

//     $booking->status = $request->status;
//     $booking->save();

//     return response()->json([
//         'success' => true,
//         'message' => $request->status === 'confirmed' 
//             ? __('admin.booking_confirmed_conflicts_resolved') 
//             : __('admin.booking_status_updated'),
//         'booking' => $booking
//     ], 200);
// }

// الحذف النهائي من قاعدة البيانات
// public function forceDeleteBooking($id)
// {
//     $booking = Booking::withTrashed()->find($id);

//     if (!$booking) {
//         return response()->json(['success' => false, 'message' => __('admin.booking_not_found')], 404);
//     }

//     $booking->forceDelete();

//     return response()->json([
//         'success' => true,
//         'message' => __('admin.booking_force_deleted')
//     ], 200);
// }

public function getMessages(Request $request)
{
    $query = ContactMessage::query();

    if ($request->filled('status')) {
        $isRead = $request->status === 'read' ? true : false;
        $query->where('is_read', $isRead);
    }

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }

    $messages = $query->orderBy('created_at', 'desc')->get();

    return response()->json([
        'success' => true,
        'count' => $messages->count(),
        'messages' => $messages
    ], 200);
}

// عرض تفاصيل رسالة محددة
public function showMessage($id)
{
    $message = ContactMessage::find($id);

    if (!$message) {
        return response()->json(['success' => false, 'message' => __('admin.message_not_found')], 404);
    }

    $message->is_read = true;
    $message->save();

    return response()->json([
        'success' => true,
        'message' => $message
    ], 200);
}

// حذف الرسالة
public function destroyMessage($id)
{
    $message = ContactMessage::find($id);

    if (!$message) {
        return response()->json(['success' => false, 'message' => __('admin.message_not_found')], 404);
    }

    $message->delete();

    return response()->json([
        'success' => true,
        'message' => __('admin.message_deleted')
    ], 200);
}

// ارسال رسالة جماعية 
public function sendBroadcastMessage(Request $request)
{
    $request->validate([
        'target' => 'required|string|in:all,users,providers',
        'type'   => 'required|string|in:email,sms,both',
        'title'  => 'required|string|min:5',
        'content'=> 'required|string|min:10',
    ]);
    $lockKey = 'broadcast_lock_' . md5($request->title . $request->content);
    if (Cache::has($lockKey)) {
        return response()->json([
            'success' => false,
            'message' => __('admin.broadcast_in_progress')
        ], 429); 
    }
    Cache::put($lockKey, true, now()->addMinutes(5));
    $query = User::query();
    if ($request->target === 'users') {
        $query->where('role', 'user');
    } elseif ($request->target === 'providers') {
        $query->where('role', 'provider');
    }
    
    $recipients = $query->get();

    if ($recipients->isEmpty()) {
        Cache::forget($lockKey); 
        return response()->json(['success' => false, 'message' => __('admin.no_recipients_found')], 404);
    }

    $emailCount = 0;
    $smsCount = 0;
    $uniqueEmails = [];
    $uniquePhones = [];

    foreach ($recipients as $recipient) {
            if (($request->type === 'email' || $request->type === 'both') && !empty($recipient->email)) {
            if (!in_array($recipient->email, $uniqueEmails)) {
                $userEmail = $recipient->email;
                $title = $request->title;
                $content = $request->content;

                Mail::raw($content, function ($mail) use ($userEmail, $title) {
                    $mail->to($userEmail)
                         ->subject(__('admin.important_announcement') . ": " . $title . " - Royal Moments");
                });

                $uniqueEmails[] = $recipient->email; 
                $emailCount++;
            }
        }
        if (($request->type === 'sms' || $request->type === 'both') && !empty($recipient->phone)) {
            if (!in_array($recipient->phone, $uniquePhones)) {
                $smsText = "📢 " . $request->title . "\n" . $request->content;
                
                $smsSuccess = $this->otpService->sendMessage($recipient->phone, $smsText);
                if ($smsSuccess) {
                    $uniquePhones[] = $recipient->phone; 
                    $smsCount++;
                }
            }
        }
    }
    Cache::forget($lockKey);

    return response()->json([
        'success' => true,
        'message' => __('admin.broadcast_sent_successfully'),
        'details' => [
            'total_targets' => $recipients->count(),
            'unique_emails_sent' => $emailCount,
            'unique_sms_sent' => $smsCount
        ]
    ], 200);
}

// *******ه جلب الإيرادات والأرباح الإجمالية حسب الفترة
// public function getRevenueReport(Request $request)
// {
//     $period = $request->query('period', 'all');
//     $query = Booking::where('status', 'completed');

//     if ($period === 'daily') {
//         $query->whereDate('created_at', now()->today());
//     } elseif ($period === 'monthly') {
//         $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
//     } elseif ($period === 'yearly') {
//         $query->whereYear('created_at', now()->year);
//     }

//     return response()->json([
//         'success' => true,
//         'period'  => $period,
//         'total_revenue'   => $query->sum('total_amount'),
//         'app_net_profit'  => $query->sum('app_commission'),
//     ], 200);
// }

// جلب قائمة العمولات والمبالغ الخاصة بكل مزود خدمة
public function getProvidersCommissions()
{
    // جلب المستخدمين الذين دورهم 'provider' مع حساب معاملاتهم المالية المكتملة
    $providers = \App\Models\User::where('role', 'provider')
        ->select('id', 'username')
        ->get()
        ->map(function ($provider) {
            
            // جلب المعاملات الخاصة بهذا المزود (عبر خدماته والحجوزات المكتملة)
            $completedTransactions = \App\Models\Transaction::whereHas('eventItem.providerService', function ($q) use ($provider) {
                $q->where('user_id', $provider->id);
            })->where('status', 'completed');

            // حساب المبالغ بدقة من جدول المعاملات
            $total_collected = (float) $completedTransactions->sum('amount');
            $app_commission_earned = (float) $completedTransactions->sum('admin_commission');
            $provider_net_profit = (float) $completedTransactions->sum('provider_amount');

            return [
                'id'                    => $provider->id,
                'username'              => $provider->username,
                'total_collected'       => $total_collected,
                'app_commission_earned' => $app_commission_earned,
                'provider_net_profit'   => $provider_net_profit,
            ];
        });

    return response()->json([
        'success' => true,
        'providers_commissions' => $providers
    ], 200);
}

// بتوللللللللللللللللللللللللل
public function showBooking($id)
{
    // جلب العنصر مع العلاقات المطلوبة
    $item = \App\Models\EventItem::with(['event', 'package', 'providerService'])->find($id);

    if (!$item) {
        return response()->json(['message' => __('admin.event_item_not_found')], 404);
    }

    return response()->json([
        "id"           => $item->id,
        "event_id"     => $item->event_id,
        "event_name"   => $item->event->name ?? 'N/A',
        "package_name" => $item->package->name ?? 'N/A',
        "price"        => $item->price,
        "status"       => $item->status,
        "option"       => $item->option, // هذا مصفوفة (Array) بفضل الـ $casts
        "start_time"   => $item->start_time,
        "end_time"     => $item->end_time,
        "created_at"   => $item->created_at?->format('Y-m-d')
    ]);
}

public function indexBook(Request $request)
{
    $query = \App\Models\EventItem::with(['event.user', 'providerService.user', 'providerService.service']);

    // 1. الفلترة حسب الحالة (status)
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    // 2. الفلترة حسب المزود (provider_id)
    if ($request->filled('provider_id')) {
        $query->whereHas('providerService', function ($q) use ($request) {
            $q->where('user_id', $request->provider_id);
        });
    }
    
    // 3. الفلترة حسب اسم العميل (customer_name)
    if ($request->filled('customer_name')) {
        $query->whereHas('event.user', function ($q) use ($request) {
            $q->where('username', 'LIKE', '%' . $request->customer_name . '%');
        });
    }

    // 4. الفلترة حسب رقم الخدمة الأساسية (service_id)
    if ($request->filled('service_id')) {
        $query->whereHas('providerService', function ($q) use ($request) {
            $q->where('service_id', $request->service_id);
        });
    }

    // 5. الفلترة حسب رقم خدمة المزود المحددة (provider_service_id)
    if ($request->filled('provider_service_id')) {
        $query->where('provider_service_id', $request->provider_service_id);
    }

    $items = $query->get();

    return response()->json([
        'bookings' => $items->map(function ($item) {
            return [
                'id'                  => $item->id,
                'customer_name'       => $item->event->user->username ?? 'N/A',
                'provider_name'       => $item->providerService->user->username ?? 'N/A',
                'service_name'        => $item->providerService->service->name ?? 'N/A',
                'provider_service_id' => $item->provider_service_id, // أضفناها لتتأكدي من النتيجة
                'status'              => $item->status,
                'booking_date'        => $item->event->event_date ?? 'N/A',
                'total_price'         => $item->price,
            ];
        })
    ]);
}
public function updateStatus(Request $request, $id)
{
    // 1. التحقق من صحة البيانات المدخلة
    $request->validate([
        'status' => 'required|in:Pending,Confirmed,Completed,Cancelled',
    ]);

    // 2. البحث عن الحجز (EventItem)
    $booking = \App\Models\EventItem::find($id);

    if (!$booking) {
        return response()->json(['message' => __('admin.booking_not_found')], 404);
    }

    // 3. تحديث الحالة
    $booking->status = $request->status;
    $booking->save();

    // 4. إرجاع الرد المطلوب
    return response()->json([
        'message' => __('admin.booking_status_updated')
    ], 200);
}
public function destroyBook($id)
{
    // 1. البحث عن الحجز
    $booking = \App\Models\EventItem::find($id);

    // 2. التحقق من وجوده
    if (!$booking) {
        return response()->json(['message' => __('admin.booking_not_found')], 404);
    }

    // 3. حذف الحجز
    $booking->delete();

    // 4. إرجاع الرد المطلوب
    return response()->json([
        'message' => __('admin.booking_deleted')
    ], 200);
}

public function statistics()
{
    // حساب إجمالي الحجوزات
    $totalBookings = \App\Models\EventItem::count();

    // حساب الحجوزات حسب الحالة
    $pending = \App\Models\EventItem::where('status', 'Pending')->count();
    $confirmed = \App\Models\EventItem::where('status', 'Confirmed')->count();
    $completed = \App\Models\EventItem::where('status', 'Completed')->count();
    $cancelled = \App\Models\EventItem::where('status', 'Cancelled')->count();

    // حساب حجوزات اليوم
    $todayBookings = \App\Models\EventItem::whereDate('created_at', today())->count();

    // حساب حجوزات هذا الشهر
    $thisMonthBookings = \App\Models\EventItem::whereMonth('created_at', now()->month)
                                              ->whereYear('created_at', now()->year)
                                              ->count();
    // حساب إجمالي الأرباح (مجموع الـ price)
    $totalRevenue = \App\Models\EventItem::sum('price');

    return response()->json([
        'total_bookings'      => $totalBookings,
        'pending'             => $pending,
        'confirmed'           => $confirmed,
        'completed'           => $completed,
        'cancelled'           => $cancelled,
        'today_bookings'      => $todayBookings,
        'this_month_bookings' => $thisMonthBookings,
        'total_revenue'       => (float) $totalRevenue,
    ]);
}



// 1. دالة عرض جميع الإيفنتات
   public function indexEvents()
    {
        // نجلب الإيفنتات مع بيانات العميل (user) المرتبط بها
        $events = \App\Models\Event::with('user')->get();

        return response()->json([
            'events' => $events->map(function ($event) {
                return [
                    'id'            => $event->id,
                    'name'          => $event->name ?? 'N/A',
                    'customer_name' => $event->user->username ?? 'N/A',
                    'event_date'    => $event->event_date,
                    'location'      => $event->location ?? 'N/A',
                    'created_at'    => $event->created_at
                        ? $event->created_at->format('Y-m-d')
                        : null,
                ];
            })
        ]);
    }

    // 2. دالة حذف إيفنت
    public function deleteEvent($id)
    {
        $event = \App\Models\Event::find($id);

        // التحقق مما إذا كان الإيفنت موجوداً
        if (!$event) {
            return response()->json([
                'message' => __('admin.event_not_found')
            ], 404);
        }

        // حذف الإيفنت
        $event->delete();

        return response()->json([
            'message' => __('admin.event_deleted')
        ], 200);
    }





    /**
     * 1. عرض جميع التعليقات مع الفلاتر المطلوبة
     * GET /api/admin/comments
     */
  
public function indexComments(Request $request)
{
    $query = \App\Models\Review::with(['user', 'providerService.user', 'providerService.service']);

    // فلترة حسب التقييم (rating=5)
    if ($request->filled('rating')) {
        $query->where('rating', $request->rating);
    }

    // فلترة حسب المزود (provider_id=3)
    if ($request->filled('provider_id')) {
        $query->whereHas('providerService', function ($q) use ($request) {
            $q->where('user_id', $request->provider_id);
        });
    }

    // فلترة حسب الخدمة (service_id=6)
    if ($request->filled('service_id')) {
        $query->whereHas('providerService', function ($q) use ($request) {
            $q->where('service_id', $request->service_id);
        });
    }

    // فلترة حسب التاريخ (date=2025-07)
    if ($request->filled('date')) {
        $query->where('created_at', 'LIKE', $request->date . '%');
    }

    // فلترة حسب اسم العميل (user_name=Ahmad)
    if ($request->filled('user_name')) {
        $query->whereHas('user', function ($q) use ($request) {
            $q->where('username', 'LIKE', '%' . $request->user_name . '%');
        });
    }

    $reviews = $query->get();
    
    // جلب معرّف المستخدم
    $userId = auth()->id() ?? 1; // معرّف مؤقت للتجربة

    return response()->json([
        'comments' => $reviews->map(function ($review) use ($userId) {
            // مفتاح الكاش المميز لجلب التفاعل الحالي
            $cacheKey = "mock_reaction_review_{$review->id}_user_{$userId}";
            $currentReaction = Cache::get($cacheKey);

            return [
                'id'            => $review->id,
                'user_name'     => $review->user->username ?? 'N/A',
                'provider_name' => $review->providerService->user->username ?? 'N/A',
                'service_name'  => $review->providerService->service->name ?? 'N/A',
                'rating'        => (int) $review->rating,
                'comment'       => $review->comment ?? '',
                'current_reaction' => $currentReaction, // 'like', 'dislike', 'love', or null
                'created_at'    => $review->created_at ? $review->created_at->format('Y-m-d') : null,
            ];
        })
    ]);
}



    /**
     * 2. إحصائيات التعليقات
     * GET /api/admin/comments/statistics
     */
    public function commentStatistics()
    {
        // استخدام موديل Review الموجود في نظامك
        $totalComments = \App\Models\Review::count();
        $averageRating = \App\Models\Review::avg('rating');
        
        $todayComments = \App\Models\Review::whereDate('created_at', \Carbon\Carbon::today())->count();
        
        $thisMonthComments = \App\Models\Review::whereMonth('created_at', \Carbon\Carbon::now()->month)
                                               ->whereYear('created_at', \Carbon\Carbon::now()->year)
                                               ->count();

        return response()->json([
            'total_comments'      => $totalComments,
            'average_rating'      => $averageRating ? round($averageRating, 1) : 0, // التقريب لمرتبة عشرية واحدة
            'today_comments'      => $todayComments,
            'this_month_comments' => $thisMonthComments
        ]);
    }

    /**
     * 3. حذف تعليق
     * DELETE /api/admin/comments/{id}
     */
    public function deleteComment($id)
    {
        $review = \App\Models\Review::find($id);

        if (!$review) {
            return response()->json([
                'message' => __('admin.comment_not_found')
            ], 404);
        }

        $review->delete();

        return response()->json([
            'message' => __('admin.comment_deleted')
        ], 200);
    }

 public function indexTransactions(Request $request)
{
    // جلب المعاملات مع العلاقات اللازمة
    $query = \App\Models\Transaction::with([
        'user',
        'eventItem.providerService.user',
        'eventItem.service',
    ]);

    // 1. البحث العام
    if ($request->filled('search')) {
        $query->where(function ($q) use ($request) {
            $q->whereHas('user', function ($u) use ($request) {
                $u->where(
                    'username',
                    'LIKE',
                    '%' . $request->search . '%'
                );
            })
            ->orWhere(
                'reference_number',
                'LIKE',
                '%' . $request->search . '%'
            );
        });
    }

    // 2. الفلترة حسب المزود
    if ($request->filled('provider_id')) {
        $query->whereHas('eventItem.providerService', function ($q) use ($request) {
            $q->where('user_id', $request->provider_id);
        });
    }

    // 3. الفلترة حسب حالة الدفع
    if ($request->filled('payment_status')) {
        $query->where(
            'status',
            $request->payment_status
        );
    }

    // 4. الفلترة حسب التاريخ
    if ($request->filled('from')) {
        $query->whereDate(
            'created_at',
            '>=',
            $request->from
        );
    }

    if ($request->filled('to')) {
        $query->whereDate(
            'created_at',
            '<=',
            $request->to
        );
    }

    // Pagination
    $transactions = $query
        ->latest()
        ->paginate(15);

    // JSON
    return response()->json([
        'data' => $transactions->getCollection()->map(function ($t) {

            return [
                'id' => $t->id,

                'booking_id' => $t->event_item_id,

                'reference_number' => $t->reference_number,

                'customer' => $t->user->username ?? 'N/A',

                'provider' =>
                    $t->eventItem->providerService->user->username
                    ?? 'N/A',

                'service' =>
                    $t->eventItem->service->name
                    ?? 'N/A',

                'amount' => (float) $t->amount,

                'commission' => (float) $t->admin_commission,

                'provider_amount' => (float) $t->provider_amount,

                'payment_method' => $t->payment_method ?? 'N/A',

                'payment_status' => $t->status,

                'pdf_url' => $t->pdf_url,

                'date' => $t->created_at
                    ? $t->created_at->format('Y-m-d H:i')
                    : 'N/A',
            ];
        }),

        'current_page' => $transactions->currentPage(),

        'last_page' => $transactions->lastPage(),
    ]);
}



/**
     * 1. إحصائيات لوحة التحكم المالية (Dashboard Statistics)
     */
    public function getDashboardStats()
    {
        // لحساب الأرباح، من المنطقي أن نجمع فقط العمليات المكتملة (Completed)
        $completedTransactions = \App\Models\Transaction::where('status', 'completed');

        // حساب المبالغ
        $total_revenue    = (float) $completedTransactions->sum('amount');
        $platform_profit  = (float) $completedTransactions->sum('admin_commission');
        $providers_profit = (float) $completedTransactions->sum('provider_amount');

        // حساب أعداد الحجوزات (العمليات) حسب الحالة
        $completed_bookings = \App\Models\EventItem::where('status', 'completed')->count();
        $pending_bookings   = \App\Models\EventItem::where('status', 'pending')->count();
        // يمكنك تعديل 'failed' إلى 'cancelled' إذا كانت هي القيمة المعتمدة في قاعدة بياناتك
        $cancelled_bookings = \App\Models\EventItem::where('status', 'failed')->count(); 

        return response()->json([
            'total_revenue'      => $total_revenue,
            'platform_profit'    => $platform_profit,
            'providers_profit'   => $providers_profit,
            'completed_bookings' => $completed_bookings,
            'pending_bookings'   => $pending_bookings,
            'cancelled_bookings' => $cancelled_bookings,
        ]);
    }

    /**
     * 2. عرض تفاصيل عملية مالية محددة
     */
    public function showTransaction($id)
    {
        // جلب العملية مع العلاقات
        $t = \App\Models\Transaction::with(['user', 'eventItem.providerService.user', 'eventItem.service'])->find($id);

        if (!$t) {
            return response()->json(['message' => __('admin.transaction_not_found')], 404);
        }

        return response()->json([
            'id'               => $t->id,
            'booking_id'       => $t->event_item_id,
            'reference_number' => $t->reference_number, // مهم في التفاصيل
            'customer'         => $t->user->username ?? 'N/A',
            'provider'         => $t->eventItem->providerService->user->username ?? 'N/A',
            'service'          => $t->eventItem->service->name ?? 'N/A',
            'amount'           => (float) $t->amount,
            'commission'       => (float) $t->admin_commission,
            'provider_amount'  => (float) $t->provider_amount,
            'payment_method'   => $t->payment_method, // طريقة الدفع
            'payment_status'   => $t->status,
            'pdf_url'          => $t->pdf_url, // فاتورة إن وجدت
            'date'             => $t->created_at ? $t->created_at->format('Y-m-d H:i') : 'N/A', // أضفنا الوقت أيضاً للتفاصيل
        ]);
    }
/**
     * 3. بيانات المخطط البياني للإيرادات (Revenue Chart)
     */
    public function getChartData()
    {
        // جلب العمليات المكتملة للسنة الحالية فقط
        $transactions = \App\Models\Transaction::where('status', 'completed')
            ->whereYear('created_at', now()->year)
            ->selectRaw('MONTH(created_at) as month_num, SUM(amount) as revenue')
            ->groupBy('month_num')
            ->orderBy('month_num')
            ->get();

        // تنسيق البيانات لتطابق المطلوب تماماً
        $chartData = $transactions->map(function ($t) {
            // تحويل رقم الشهر (مثلاً 1) إلى الاسم المختصر (Jan)
            $monthName = \Carbon\Carbon::create()->month($t->month_num)->format('M');
            
            return [
                'month'   => $monthName,
                'revenue' => (float) $t->revenue,
            ];
        });

        // استخدام values() لضمان إرجاع JSON Array [...] وليس Object {...}
        return response()->json($chartData->values());
    }
/**
     * 4. أفضل المزودين (Top Providers)
     */
    public function topProviders()
    {
        // نربط جدول العمليات بجدول عناصر الفعالية، ثم بخدمات المزود، ثم بجدول المستخدمين لنجلب اسم المزود
        $topProviders = \App\Models\Transaction::where('transactions.status', 'completed')
            ->join('event_items', 'transactions.event_item_id', '=', 'event_items.id')
            ->join('provider_services', 'event_items.provider_service_id', '=', 'provider_services.id')
            ->join('users', 'provider_services.user_id', '=', 'users.id')
            ->selectRaw('users.username as provider, SUM(transactions.amount) as total')
            ->groupBy('users.id', 'users.username')
            ->orderByDesc('total')
            ->take(5) // جلب أفضل 5 مزودين (يمكنك تعديل الرقم)
            ->get()
            ->map(function ($item) {
                return [
                    'provider' => $item->provider,
                    'total'    => (float) $item->total,
                ];
            });

        return response()->json($topProviders);
    }
public function topCustomers()
{
    $topCustomers = \App\Models\Transaction::where(
            'transactions.status',
            'completed'
        )
        ->join(
            'users',
            'transactions.user_id',
            '=',
            'users.id'
        )
        ->selectRaw('
            users.username as customer,
            COUNT(transactions.id) as total_transactions,
            SUM(transactions.amount) as total
        ')
        ->groupBy('users.id', 'users.username')
        ->orderByDesc('total_transactions')
        ->take(3)
        ->get()
        ->map(function ($item) {
            return [
                'customer' => $item->customer,
                'total_transactions' => (int) $item->total_transactions,
                'total' => (float) $item->total,
            ];
        });

    return response()->json($topCustomers);
}

/**
     * 5. أكثر الخدمات مبيعاً (Top Services) - التعديل الصحيح للربط
     */
    public function topServices()
    {
        $topServices = \App\Models\Transaction::where('transactions.status', 'completed')
            ->join('event_items', 'transactions.event_item_id', '=', 'event_items.id')
            // نربط أولاً بجدول الـ provider_services
            ->join('provider_services', 'event_items.provider_service_id', '=', 'provider_services.id')
            // ثم نربط بجدول الـ services
            ->join('services', 'provider_services.service_id', '=', 'services.id')
            ->selectRaw('services.name as service, SUM(transactions.amount) as total')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('total')
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'service' => $item->service,
                    'total'   => (float) $item->total,
                ];
            });

        return response()->json($topServices);
    }
    // 1. عرض نسبة العمولة
public function getCommission()
{
    $commission = \App\Models\Setting::where('key', 'admin_commission')->value('value') ?? 10;
    return response()->json(['commission' => (float) $commission], 200);
}

// 2. تعديل نسبة العمولة
public function updateCommission(Request $request)
{
    $request->validate(['commission' => 'required|numeric|min:0|max:100']);
    \App\Models\Setting::updateOrCreate(
        ['key' => 'admin_commission'],
        ['value' => $request->commission]
    );
    return response()->json([
        'message' => __('admin.commission_updated_successfully'),
        'commission' => (float) $request->commission
    ], 200);
}

// 3. تصدير Excel
public function exportFinancialExcel()
{
    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\TransactionsExport, 'financial_transactions.xlsx');
}
public function exportFinancialPdf()
{
    return Excel::download(
        new TransactionsExport, 
        'financial_report.pdf', 
        ExcelFormat::MPDF
    );
}
// 4. تصدير PDF
// public function exportFinancialPdf()
// {
//     $transactions = \App\Models\Transaction::with(['user', 'eventItem.providerService.user', 'eventItem.service'])->latest()->get();
//     $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.financial_report', compact('transactions'));
//     return $pdf->download('financial_report.pdf');
// }


// 1. إحصائيات الفعاليات
public function getEventStatistics()
{
    $totalEvents     = Event::count();
    $completedEvents = Event::where('status', 'Completed')->count();
    $cancelledEvents = Event::where('status', 'Cancelled')->count();
    $upcomingEvents  = Event::where('status', 'Upcoming')->count();

    return response()->json([
        'success' => true,
        'stats'   => [
            'total'     => $totalEvents,
            'completed' => $completedEvents,
            'cancelled' => $cancelledEvents,
            'upcoming'  => $upcomingEvents,
        ]
    ], 200);
}

// 2. جلب الفعاليات القادمة
public function getNextEvent()
{
    $now = Carbon::now();

    Event::whereIn('status', ['upcoming', 'Upcoming'])
        ->where(function ($query) use ($now) {
            $query->whereDate('event_date', '<', $now->toDateString())
                  ->orWhere(function ($q) use ($now) {
                      $q->whereDate('event_date', $now->toDateString())
                        ->whereTime('end_time', '<=', $now->toTimeString())
                        ->whereTime('end_time', '!=', '00:00:00');
                  });
        })->update(['status' => 'completed']);

    $nextEvents = Event::with(['user', 'location'])
        ->whereIn('status', ['upcoming', 'Upcoming'])
        ->where(function ($query) use ($now) {
            $query->whereDate('event_date', '>', $now->toDateString())
                  ->orWhere(function ($q) use ($now) {
                      $q->whereDate('event_date', $now->toDateString())
                        ->whereTime('start_time', '>=', $now->toTimeString());
                  });
        })
        ->orderBy('event_date', 'asc')
        ->orderBy('start_time', 'asc')
        ->get();

    if ($nextEvents->isEmpty()) {
        return response()->json([
            'success' => true,
            // استخدام الترجمة هنا
            'message' => __('messages.no_upcoming_events'),
            'data'    => []
        ], 200);
    }

    $formattedEvents = $nextEvents->map(function ($event) {
        return [
            'id'            => $event->id,
            'name'          => $event->name ?? 'N/A',
            'customer_name' => $event->user->username ?? 'N/A',
            // استخدام translatedFormat لترجمة اسم الشهر حسب لغة Carbon
            'event_date'    => Carbon::parse($event->event_date)->translatedFormat('d F'),
            'start_time'    => $event->start_time ? Carbon::parse($event->start_time)->format('H:i') : 'N/A',
            'end_time'      => $event->end_time ? Carbon::parse($event->end_time)->format('H:i') : 'N/A',
            'location'      => $event->location,
            'status'        => $event->status
        ];
    });

    return response()->json([
        'success' => true,
        'data'    => $formattedEvents
    ], 200);
}

// 3. إضافة فعالية من الأدمن
public function storeEvent(Request $request)
{
    $validated = $request->validate([
        'user_id'      => 'nullable|exists:users,id', 
        'name'         => 'required|string|max:255',
        'event_date'   => 'required|date|after_or_equal:today',
        'start_time'   => 'required|date_format:H:i',
        'end_time'     => 'required|date_format:H:i|after:start_time',
        'location'     => 'nullable|string|max:255',
        'address_name' => 'nullable|string|max:255',
        'latitude'     => 'nullable|numeric|between:-90,90',
        'longitude'    => 'nullable|numeric|between:-180,180',
    ]);

    $ownerId = $request->filled('user_id') ? $validated['user_id'] : auth()->id();

    // 1. إنشاء الحدث أولاً
    $event = Event::create([
        'user_id'    => $ownerId,
        'name'       => $validated['name'],
        'event_date' => $validated['event_date'],
        'start_time' => $validated['start_time'],
        'end_time'   => $validated['end_time'],
        'location'   => $validated['location'] ?? null,
        'status'     => 'upcoming',
    ]);

    // 2. إنشاء الموقع مع تمرير city_id افتراضي (مثلاً 1) لتجنب خطأ قاعدة البيانات
    if ($request->hasAny(['address_name', 'latitude', 'longitude'])) {
        $location = \App\Models\Location::create([
            'city_id'      => 1, // <--- قيمة افتراضية مؤقتة لكي لا يرفضها الجدول
            'address_name' => $request->address_name,
            'latitude'     => $request->latitude,
            'longitude'    => $request->longitude,
        ]);

        // 3. ربط الموقع بالحدث وحفظ الإحداثيات
        $event->location_id = $location->id;
        $event->latitude = $location->latitude;
        $event->longitude = $location->longitude;
        $event->save();
    }

    // 4. تحميل العلاقة في الاستجابة
    $event->load('location');

    return response()->json([
        'success' => true,
        'message' => __('messages.event_created_successfully'),
        'event'   => $event
    ], 201);
}

// 4. تعديل الفعالية
// 4. تعديل الفعالية
public function updateEvent(Request $request, $id)
{
    $event = Event::find($id);

    if (!$event) {
        return response()->json([
            'success' => false,
            'message' => __('messages.event_not_found')
        ], 404);
    }

    // ✅ 1. أضيفي هنا التحقق من صحة بيانات الموقع والمدينة لضمان الأمان
    $request->validate([
        'city_id'      => 'sometimes|nullable|exists:cities,id',
        'address_name' => 'sometimes|nullable|string|max:255',
        'latitude'     => 'sometimes|nullable|numeric',
        'longitude'    => 'sometimes|nullable|numeric',
        'name'         => 'sometimes|string|max:255',
        'event_date'   => 'sometimes|date',
        'start_time'   => 'sometimes|date_format:H:i',
        'end_time'     => 'sometimes|date_format:H:i|after:start_time',
        'location'     => 'nullable|string|max:255',
        'status'       => 'sometimes|in:Upcoming,Completed,Cancelled,upcoming,completed,cancelled',
    ]);

    // ✅ تعديل جزء الموقع فقط
    if ($request->hasAny(['city_id', 'address_name', 'latitude', 'longitude'])) {
        $location = $event->location;
        if (!$location) {
            $location = \App\Models\Location::create([
                'city_id'      => $request->city_id,
                'address_name' => $request->address_name,
                'latitude'     => $request->latitude,
                'longitude'    => $request->longitude,
            ]);
            $event->location_id = $location->id;
        } else {
            $location->update([
                'city_id'      => $request->input('city_id', $location->city_id),
                'address_name' => $request->input('address_name', $location->address_name),
                'latitude'     => $request->input('latitude', $location->latitude),
                'longitude'    => $request->input('longitude', $location->longitude),
            ]);
        }

        // ✅ تحديث الحقول في جدول events عشان تظهر في الـ response
        $event->latitude = $location->latitude;
        $event->longitude = $location->longitude;
        $event->save(); // حفظ التعديلات على الـ event
    }

    // (بما أننا قمنا بالـ validate بالأعلى، سنستخدم المتغير $validated أو نحدث الحدث مباشرة)
    $event->update($request->only(['name', 'event_date', 'start_time', 'end_time', 'location', 'status']));

    // ✅ إعادة تحميل العلاقات عشان تظهر محدثة
    $event->load('location');

    return response()->json([
        'success' => true,
        'message' => __('messages.event_updated_successfully'),
        'event'   => $event
    ], 200);
}

}