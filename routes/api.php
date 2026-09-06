<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AIController;


// استدعاء الكنترولرز (Controllers)
use App\Http\Controllers\EventController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProviderApprovalController;
use App\Http\Controllers\ProviderServiceController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShowTicketController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\ContactMessageController;

use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// =========================================================================
// 1. مسارات المصادقة والحسابات (عامة للجميع)
// =========================================================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login2']); // ✅ الآن login2 موجودة
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);

Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-forgot-otp', [AuthController::class, 'verifyForgotOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);



// =========================================================================
// 2. مسارات الزائر العامة (Public Routes) 🔓
// =========================================================================
Route::get('/events', [EventController::class, 'index']);
Route::get('/services/filter', [EventController::class, 'filterServices']);
Route::post('/stripe/webhook', [PaymentController::class, 'stripeWebhook']);
Route::get('/provider-services/{providerServiceId}/reviews', [ReviewController::class, 'getProviderReviews']);

// =========================================================================
// 3. المسارات المحمية بـ التوكن العام (auth:sanctum)
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {

    // ---- الملف الشخصي والبيانات العامة ----
    Route::get('/profile', [AuthController::class, 'showProfile']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/update-web', [AuthController::class, 'updateProfile']); 
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-device-token', [AuthController::class, 'updateDeviceToken']);
    Route::get('/providers/services', [EventController::class, 'servicesByProvider']);

    // ---- مسارات الفعاليات والحجوزات للمستخدم ----
    Route::post('/events', [EventController::class, 'createEvent']);
    Route::put('/events/{id}', [EventController::class, 'updateEvent']);
    Route::put('/bookings/{booking}', [EventController::class, 'updateBooking']);
    Route::get('/events/{id}', [EventController::class, 'showEvent']);
    Route::get('/my-events', [EventController::class, 'myEvents']);
    Route::patch('/events/{id}/cancel', [EventController::class, 'cancelWholeEvent']);
    Route::post('/book-service', [EventController::class, 'bookService'])->name('booking.store');
    Route::get('/my-bookings', [EventController::class, 'myBookings']);
    Route::patch('/bookings/{booking}/cancel', [EventController::class, 'cancelBooking']);

    // ---- مسارات الدفع واسترجاع الأموال ----
    Route::post('/events/payweb', [PaymentController::class, 'payweb']);
    Route::post('/event-items/{item}/pay', [PaymentController::class, 'pay']);
    Route::post('/events/pay', [PaymentController::class, 'payEvent']);
    Route::post('/wallet/pay-event', [PaymentController::class, 'payWallet']);
    Route::post('/refund/{item}', [PaymentController::class, 'refund']);
    Route::post('/wallet/pay-with-cash', [PaymentController::class, 'payWithCash']);
    Route::post('/payment/create-intent', [PaymentController::class, 'createMobilePaymentIntent']);

    // ✅ المسار المعدل لجلب التذكرة (متوافق مع الفرونت)
    Route::get('/ticket/{itemId}', [ShowTicketController::class, 'showTicket']);

    // ---- مسارات المفضلة ----
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/toggle/{providerServiceId}', [FavoriteController::class, 'toggle']);
    Route::get('/favorites/check/{providerServiceId}', [FavoriteController::class, 'check']);

    // ---- مسارات التقييم ----
    Route::post('/reviews', [ReviewController::class, 'store']);

    // ---- مسارات المحفظة الشخصية ----
    Route::get('/wallet', [WalletController::class, 'balance']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('/wallet/deposit', [WalletController::class, 'deposit']);

    Route::get('/filter-services', [EventController::class, 'filterServices']);
});



//انا الويب ضفتهن ^_^
Route::middleware(['auth:api'])->group(function () {
    Route::get('/provider/booking-modifications', [ProviderApprovalController::class, 'pendingModifications']);
    }); 

// =========================================================================
// 4. مسارات لوحة تحكم مزود الخدمة (بناءً على الصلاحية)
// =========================================================================
Route::middleware(['auth:sanctum', 'check.role:provider'])->group(function () {

    Route::get('/test-provider', function () {
        return response()->json([
            'status'  => true,
            'message' => 'Success! Your Middleware is working. You are recognized as a Provider.',
        ]);
    });

    Route::post('provider/services', [ProviderServiceController::class, 'store']);
    Route::put('provider/services/{id}/update', [ProviderServiceController::class, 'update']);
    Route::delete('provider/services/{id}', [ProviderServiceController::class, 'destroy']);
    Route::get('/allbookings', [ProviderApprovalController::class, 'index'])->name('provider.bookings.index');
      Route::get('/provider/bookings', [EventController::class, 'providerBookings']);
});

// =========================================================================
// 5. مسارات قبول ورفض الحجوزات لمزودي الخدمة (بواسطة auth:api)
// =========================================================================
Route::middleware(['auth:api'])->prefix('provider/bookings')->group(function () {
    Route::put('/{id}/accept', [ProviderApprovalController::class, 'accept']);
    Route::put('/{id}/reject', [ProviderApprovalController::class, 'reject']);
    Route::get('/{id}', [ProviderApprovalController::class, 'show']);
    Route::post('/{itemId}/accept-modification', [ProviderApprovalController::class, 'acceptModification']);
    Route::post('/{itemId}/reject-modification', [ProviderApprovalController::class, 'rejectModification']);
    Route::post('/verify-qr', [ProviderApprovalController::class, 'verifyQrCode']);
});

// مسارات إضافية خارج المجموعات
Route::get('/cities', [EventController::class, 'index']);
Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->name('verification.send');
Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])->name('verification.verify');
Route::post('/forgot2-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.reset');
Route::post('/store', [ContactMessageController::class, 'store']);

// مسارات الضيوف والدعوات
Route::get('/guests', [ProviderServiceController::class, 'index1']);


// مسارات الخدمات العامة
Route::get('provider/services/{id}', [ProviderServiceController::class, 'show']);
Route::get('provider/services', [ProviderServiceController::class, 'index']);
Route::prefix('services')->group(function () {
    Route::get('/', [EventController::class, 'indexServices']);
    Route::get('/{id}', [EventController::class, 'showService']);
});

// ================================================================
// ✅ مسارات لوحة تحكم الأدمن (AdminController) - الإصدار الكامل
// ================================================================

Route::middleware(['auth:sanctum', 'check.role:admin'])->group(function () {

Route::post('/admin/users/{id}', [AdminController::class, 'updateuser']);
Route::delete('/admin/users/{id}', [AdminController::class, 'destroy']);
Route::delete('/admin/users/{id}/soft-delete', [AdminController::class, 'softDelete']);
Route::post('/admin/users/{id}/restore', [AdminController::class, 'restoreUser']);
Route::post('/admin/users/{id}/block', [AdminController::class, 'blockUser']);
Route::post('/admin/users/{id}/unblock', [AdminController::class, 'unblock']);
Route::get('/admin/settings', [AdminController::class, 'getSettings']);
Route::post('/app/settings/update', [AdminController::class, 'updateSettings']);
Route::get('/providers', [AdminController::class, 'showProviders']);
Route::post('/providers/{id}', [AdminController::class, 'updateProvider']);
Route::delete('/providers/{id}', [AdminController::class, 'destroyProvider']);
Route::get('/providers/{provider_id}/services', [AdminController::class, 'getProviderServices']);
Route::get('/admin/comments', [AdminController::class, 'getAllComments']);
Route::delete('/admin/comments/{id}', [AdminController::class, 'destroyComment']);
Route::post('/admin/comments/{id}/toggle-like', [AdminController::class, 'toggleCommentLike']);
Route::get('/admin/filtered-comments', [AdminController::class, 'getFilteredComments']);
Route::get('/admin/filtered-users', [AdminController::class, 'getFilteredUsers']);
Route::get('/admin/filtered-providers', [AdminController::class, 'getFilteredProviders']);
Route::get('/admin/users-statistics', [AdminController::class, 'getUsersStatistics']);
Route::post('admin/providers/add', [AdminController::class, 'addProvider']);
Route::post('/users/add', [AdminController::class, 'addUser']);
Route::get('/users/export', [UserController::class, 'export']);
//Route::post('/users/import', [UsersImport::class, 'import']);
Route::post('/admin/bookings/{id}/status', [AdminController::class, 'updateBookingStatus']);
Route::get('/admin/bookings', [AdminController::class, 'getAllBookings']);
Route::delete('/admin/bookings/{id}/force-delete', [AdminController::class, 'forceDeleteBooking']);
Route::get('/admin/messages', [AdminController::class, 'getMessages']);
Route::get('/admin/messages/{id}', [AdminController::class, 'showMessage']);





Route::get('/event-items/{id}', [AdminController::class, 'showBooking']);
Route::get('/admin/bookings', [AdminController::class, 'indexBook']);
Route::put('/admin/event-items/{id}/status', [AdminController::class, 'updateStatus']);
Route::delete('/admin/event-items/{id}', [AdminController::class, 'destroyBook']);
Route::get('/admin/bookings/statistics', [AdminController::class, 'statistics']);
Route::get('/admin/events', [AdminController::class, 'indexEvents']);
Route::delete('/admin/events/{id}', [AdminController::class, 'deleteEvent']);
// مسارات التعليقات (Reviews/Comments)
Route::get('/admin/comments', [AdminController::class, 'indexComments']);
Route::get('/admin/comments/statistics', [AdminController::class, 'commentStatistics']);
Route::delete('/admin/comments/{id}', [AdminController::class, 'deleteComment']);

Route::get('/transactions', [AdminController::class, 'indexTransactions']);
// 1. إحصائيات الداشبورد
    Route::get('/dashboard', [AdminController::class, 'getDashboardStats']);
    // 3. تفاصيل عملية محددة (يجب أن يكون أسفل الروتس الثابتة)
    Route::get('/transactions/{id}', [AdminController::class, 'showTransaction']);
Route::get('/chart', [AdminController::class, 'getChartData']);

// أفضل المزودين وأكثر الخدمات (المسارات الجديدة)
    Route::get('/top-providers', [AdminController::class, 'topProviders']);
    Route::get('/top-services', [AdminController::class, 'topServices']);




Route::get('/commission', [AdminController::class, 'getCommission']);
    Route::put('/commission', [AdminController::class, 'updateCommission']);
    Route::get('/export/excel', [AdminController::class, 'exportFinancialExcel']);
    Route::get('/export/pdf', [AdminController::class, 'exportFinancialPdf']);

Route::get('/statistics', [AdminController::class, 'getEventStatistics']);
    Route::get('/next', [AdminController::class, 'getNextEvent']);
    Route::post('/eve', [AdminController::class, 'storeEvent']);
    Route::put('/{id}', [AdminController::class, 'updateEvent']);



Route::post('/admin/messages/{id}/reply', [AdminController::class, 'replyToMessage']);
Route::delete('/admin/messages/{id}', [AdminController::class, 'destroyMessage']);
Route::post('/admin/send-broadcast', [AdminController::class, 'sendBroadcastMessage']);
Route::get('/admin/financial/revenue', [AdminController::class, 'getRevenueReport']);
Route::get('/admin/financial/commissions', [AdminController::class, 'getProvidersCommissions']);
Route::post('/messages/{id}/reply', [ContactMessageController::class, 'reply']);
Route::post('/contact-messages/{id}/reply', [ContactMessageController::class, 'updateReply']);
Route::delete('/contact-messages/{id}/reply', [ContactMessageController::class, 'deleteReply']);
Route::get('/top-customers', [AdminController::class, 'topCustomers']);

});
Route::get('/save-speech-text', [AIController::class, 'store']);
Route::post('/stripe/webhook', [PaymentController::class, 'strip']);
 Route::get('/provider-services/{id}/booked-slots', [EventController::class, 'getBookedSlots']);


Route::get('/admin/users', [AdminController::class, 'index']);