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
        Route::post('/login', [AuthController::class, 'admin_login']);
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
                Route::post('/profile/update', [MemberController::class, 'update_profile']);
                Route::post('/profile/update/password', [MemberController::class, 'update_password']);
                Route::post('/profile/upload/profile-picture', [MemberController::class, 'upload_profile_picture']);

                // Notifications
                Route::get('/get/all/notifications', [MemberController::class, 'get_all_notifications']);
                Route::get('/get/all/unread/notifications', [MemberController::class, 'get_all_unread_notifications']);
                Route::get('/count/unread/notifications', [MemberController::class, 'count_unread_notifications']);
                Route::post('/read/notification', [MemberController::class, 'read_notification']);
                Route::post('/delete/notification', [MemberController::class, 'delete_notification']);

                // Support
                Route::get('/supports', [MemberController::class, 'supports'])->name('supports');
                Route::post('/create/support/ticket', [MemberController::class, 'create_support_ticket'])->name('create.support.ticket');
                Route::get('/view/support/ticket/{room_id}', [MemberController::class, 'view_support_ticket'])->name('view.support.ticket');
                Route::get('/close/support/ticket/{room_id}', [MemberController::class, 'close_support_ticket'])->name('close.support.ticket');
                Route::post('/send/support/message/{room_id}', [MemberController::class, 'send_support_message'])->name('send.support.message');
                Route::get('/download/support/attachment/{id}', [MemberController::class, 'download_support_attachment'])->name('download.support.attachment');
        
                // Payment
                Route::get('/payments', [MemberController::class, 'payments'])->name('payments');
                Route::get('/payment/callback', [MemberController::class, 'handleGatewayCallback'])->name('handleGatewayCallback');
                Route::post('/upload/manual/receipt/{id}', [MemberController::class, 'uploadReceipt'])->name('upload.manual.receipt');
        
                // Manage Payments
                Route::get('/payments/approved', [MemberController::class, 'payments_approved'])->name('payments.approved');
                Route::get('/payments/pending', [MemberController::class, 'payments_pending'])->name('payments.pending');
        
                // Announcements
                Route::get('/announcements', [MemberController::class, 'announcements'])->name('announcements');
        
                // Contact Us
                Route::get('/messages', [MemberController::class, 'messages'])->name('messages');
                Route::post('/send/messages', [MemberController::class, 'send_messages'])->name('send.messages');
            }
        );

        // Admin authentication routes
        Route::middleware(['auth', 'isAdmin'])->group(function () {
            Route::prefix('/admin')->group(function () {
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

            });
        });
    });
});
