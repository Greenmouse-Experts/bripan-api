<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemberController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response([
        'code' => 200,
        'message' => 'You are now on Bripan API endpoints'
    ]);
});

Route::get('/login', function () {
    return response([
        'code' => 401,
        'message' => 'Token Required!'
    ]);
})->name('login');
Route::group(['middleware' => ['cors', 'json.response']], function () {
    Route::prefix('/auth')->group(function () {
        Route::post('/admin/login', [AuthController::class, 'admin_login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // Password reset routes
        Route::post('password/email',  [AuthController::class, 'forget_password']);
        Route::post('password/reset', [AuthController::class, 'reset_password']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        // Member
        Route::prefix('/member')->group(
            function () {
                Route::any('/subscription', [MemberController::class, 'subscription']);
                Route::post('/subscription/payment', [MemberController::class, 'subscription_payment']);
                Route::get('/profile', [MemberController::class, 'profile']);
                Route::middleware(['isUnsubscribed'])->group(function () {
                    Route::post('/profile/update', [MemberController::class, 'update_profile']);
                    Route::post('/profile/update/password', [MemberController::class, 'update_password']);
                    Route::post('/profile/upload/profile-picture', [MemberController::class, 'upload_profile_picture']);

                    // Notifications
                    Route::get('/get/all/notifications', [MemberController::class, 'get_all_notifications']);
                    Route::get('/get/all/unread/notifications', [MemberController::class, 'get_all_unread_notifications']);
                    Route::get('/count/unread/notifications', [MemberController::class, 'count_unread_notifications']);
                    Route::post('/read/notification', [MemberController::class, 'read_notification']);
                    Route::post('/delete/notification', [MemberController::class, 'delete_notification']);

                    // Payment
                    Route::get('/payments', [MemberController::class, 'payments']);
                    Route::post('/payment/callback', [MemberController::class, 'handleGatewayCallback']);
                    Route::post('/upload/manual/receipt', [MemberController::class, 'uploadReceipt']);
            
                    // Manage Payments
                    Route::get('/payments/approved', [MemberController::class, 'payments_approved']);
                    Route::get('/payments/pending', [MemberController::class, 'payments_pending']);
            
                    // Announcements
                    Route::get('/announcements', [MemberController::class, 'announcements']);

                    // Events
                    Route::get('/events', [MemberController::class, 'events']);
                });
            }
        );

        // Admin authentication routes
        Route::middleware(['auth', 'isAdmin'])->group(function () {
            Route::prefix('/admin')->group(function () {
                Route::get('/profile', [AdminController::class, 'profile']);
                Route::post('/verify/member', [AdminController::class, 'verify_member']);

                Route::post('/profile/update', [AdminController::class, 'update_profile']);
                Route::post('/profile/update/password', [AdminController::class, 'update_password']);
                Route::post('/profile/upload/profile-picture', [AdminController::class, 'upload_profile_picture']);
                Route::get('/get/all/user', [AdminController::class, 'get_all_user']);
                Route::post('/user/action', [AdminController::class, 'user_action']);

                Route::get('/get/all/notifications', [AdminController::class, 'get_all_notifications']);
                Route::get('/get/all/unread/notifications', [AdminController::class, 'get_all_unread_notifications']);
                Route::get('/count/unread/notifications', [AdminController::class, 'count_unread_notifications']);
                Route::post('/read/notification', [AdminController::class, 'read_notification']);
                Route::post('/delete/notification', [AdminController::class, 'delete_notification']);

                Route::get('/member/retrieve/all', [AdminController::class, 'get_all_member']);
                Route::post('/member/add', [AdminController::class, 'member_add']);
                Route::get('/member/activate', [AdminController::class, 'member_activate']);
                Route::get('/member/deactivate', [AdminController::class, 'member_deactivate']);
                Route::post('/member/update/profile', [AdminController::class, 'member_update_profile']);
                Route::post('/member/update/password', [AdminController::class, 'member_update_password']);
                Route::post('/member/update/profile-picture', [AdminController::class, 'member_update_profile_picture']);
                Route::post('/member/delete', [AdminController::class, 'member_delete']);
                Route::post('/member/resend/login/details', [AdminController::class, 'member_resend_login_details']);
                Route::get('/member/view', [AdminController::class, 'member_view']);
                Route::get('/member/view/payments', [AdminController::class, 'member_view_payments']);

                Route::get('/banks', [AdminController::class, 'banks']);
                Route::post('/bank/post', [AdminController::class, 'admin_bank_post']);
                Route::post('/bank/update', [AdminController::class, 'admin_bank_update']);
                Route::post('/bank/delete', [AdminController::class, 'admin_bank_delete']);

                // Payment Setup
                Route::get('/category', [AdminController::class, 'admin_category']);
                Route::post('/category/post', [AdminController::class, 'admin_category_post']);
                Route::post('/category/update', [AdminController::class, 'admin_category_update']);
                Route::post('/category/delete', [AdminController::class, 'admin_category_delete']);

                Route::get('/dues', [AdminController::class, 'admin_dues']);
                Route::post('/dues/post', [AdminController::class, 'admin_dues_post'])->name('admin.post.dues');
                Route::post('/dues/update', [AdminController::class, 'admin_dues_update'])->name('admin.update.dues');
                Route::post('/dues/delete', [AdminController::class, 'admin_dues_delete'])->name('admin.delete.dues');
                Route::get('/dues/view/payments', [AdminController::class, 'admin_dues_view_payments']);
                Route::get('/dues/all/payments', [AdminController::class, 'admin_dues_all_payments']);
                Route::post('/dues/update/transaction', [AdminController::class, 'admin_dues_transaction_update']);

                // Events
                Route::get('/events', [AdminController::class, 'admin_events']);
                Route::post('/event/post', [AdminController::class, 'admin_event_post']);
                Route::post('/event/update', [AdminController::class, 'admin_event_update']);
                Route::post('/event/delete', [AdminController::class, 'admin_event_delete']);

                // Announcements
                Route::get('/announcements', [AdminController::class, 'admin_announcements']);
                Route::post('/announcements/post', [AdminController::class, 'admin_announcements_post']);
                Route::post('/announcements/update', [AdminController::class, 'admin_announcements_update']);
                Route::post('/announcements/delete', [AdminController::class, 'admin_announcements_delete']);

                // Subscriptions
                Route::any('/subscription', [AdminController::class, 'subscription']);
                Route::any('/get/subscription/transactions', [AdminController::class, 'subscription_transaction']);
            });
        });
    });
});
