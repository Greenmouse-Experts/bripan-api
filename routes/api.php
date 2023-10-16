<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
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
        'message' => 'You are now on membership API endpoints'
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

                // Zone
                Route::prefix('/zone')->group(function () {
                    Route::post('/create', [AdminController::class, 'create_zone']);
                    Route::post('/update', [AdminController::class, 'update_zone']);
                    Route::post('/action', [AdminController::class, 'action_zone']);
                });

                // Fleet Manager
                Route::prefix('/fleet-manager')->group(function () {
                    Route::post('/create', [AdminController::class, 'create_fleet_manager']);
                });
                // Field Operator
                Route::prefix('/field-operator')->group(function () {
                    Route::post('/create', [AdminController::class, 'create_field_operator']);
                });
                // Waste Manager
                Route::prefix('/waste-manager')->group(function () {
                    Route::post('/create', [AdminController::class, 'create_waste_manager']);
                }); 

                // Shop
                Route::prefix('/shop')->group(function () {
                    Route::post('/create/category', [AdminController::class, 'create_category']);
                    Route::get('/get/categories', [AdminController::class, 'get_categories']);
                    Route::post('/update/category', [AdminController::class, 'update_category']);
                    Route::post('/action/category', [AdminController::class, 'action_category']);

                    Route::post('/create/product', [AdminController::class, 'create_product']);
                    Route::get('/get/products', [AdminController::class, 'get_products']);
                    Route::post('/update/product', [AdminController::class, 'update_product']);
                    Route::post('/action/product', [AdminController::class, 'action_product']);
                    Route::post('/destroy/product', [AdminController::class, 'destroy_product']);

                    Route::post('/add/product/images', [AdminController::class, 'add_product_images']);
                    Route::post('/destroy/product/image', [AdminController::class, 'destroy_product_image']);
                });

                // Application Rating
                Route::prefix('/ratings')->group(function () {
                    Route::post('/list', [AdminController::class, 'rating_list']);
                    Route::get('/view', [AdminController::class, 'view_application_rating']);
                });

                Route::get('/get/users/transaction/histories', [AdminController::class, 'get_users_transaction_histories']);
                Route::any('/special/request/flat/rate', [AdminController::class, 'special_request_flat_rate']);
                Route::any('/get/all/application/rating', [AdminController::class, 'get_all_application_rating']);

                Route::prefix('/schedule-requests')->group(function () {
                    Route::get('/get', [AdminController::class, 'get_schedule_requests']);
                    Route::get('/view/residence', [AdminController::class, 'view_residence']);
                });

                Route::prefix('/special-requests')->group(function () {
                    Route::get('/get', [AdminController::class, 'get_special_requests']);
                });
            });
        });
    });
});
