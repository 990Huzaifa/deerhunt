<?php

use Illuminate\Http\Request;
use App\Http\Controllers\BotController;
use App\Http\Controllers\User\AuthController;
use App\Http\Controllers\User\HeatMapController;
use App\Http\Controllers\User\HomeController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\LeadController;
use App\Http\Controllers\User\PostController;
use App\Http\Controllers\User\PaymentController;
use App\Http\Controllers\User\PremiumController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\TweetController;
use App\Http\Controllers\User\WebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController as AdminAuth;

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




Route::post('signup',[AuthController::class,'signup']);
Route::post('signin',[AuthController::class,'signin']);
Route::post('social',[AuthController::class,'socialLoginSignup']);
Route::post('account-check',[AuthController::class,'accountCheck']);

Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::post('resend-code', [AuthController::class, 'resendCode']);

Route::get('check', [AuthController::class,'check']);

Route::delete('delete-user/{id}',[AuthController::class, 'deleteUser']);

// webhook apple
Route::post('/webhook/apple', [WebhookController::class, 'handle']);
Route::post('/webhook/google', [WebhookController::class, 'handleGoogle']);
Route::get('request-jwt',[AuthController::class,'jwt']);
Route::post('decode-apple-token',[WebhookController::class,'decodeApple']);

Route::get('state-list',[ProfileController::class,'stateList']);

Route::get('heat-map',[HeatMapController::class, 'getHeatmapData']);
Route::get('v2/heat-map',[HeatMapController::class, 'getHeatmapDatav2']);
Route::get('posts-by-county',[HeatMapController::class, 'postByCounty']);
Route::get('heatmap/posts-count',[HeatMapController::class, 'PostCount']);
Route::get('premium-hunters', [HeatMapController::class, 'premiumHunters']);

Route::get('ai-res',[BotController::class, 'testGPTres']);


Route::prefix('admin')->group(function () {
    Route::post('signin', [AdminAuth::class, 'signin'])->name('admin.signin');
});

Route::get('leaderboard', [HomeController::class, 'leaderboard']);

Route::middleware(['auth:sanctum'])->group(function () {

        Route::post('get-location', [AuthController::class,'location']);
        Route::post('listen-from', [AuthController::class, 'listenFrom']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::get('/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', [HomeController::class,'index']);
        Route::get('recent-posts', [HomeController::class,'recent']);

        Route::apiResource('/post', PostController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::get('community',[PostController::class,'communityPosts']);
        Route::get('trophy-list',[PostController::class,'trophyList']);
        Route::post('v2/post',[PostController::class,'storeV2']);
        Route::post('v3/post',[PostController::class,'storeV3']);
        Route::post('p2p/{id}',[PostController::class,'P2P']);
        Route::put('delete-post/{id}',[PostController::class,'delete']);

        Route::get('posts-like/{id}',[PostController::class,'postLike']);
        Route::get('posts-share/{id}',[PostController::class,'postShare']);
        Route::put('save-trophy/{id}',[PostController::class,'saveTrophy']);

        Route::post('post-comment/{id}',[PostController::class,'postComment']);
        Route::get('post-comment-list/{id}',[PostController::class,'postCommentList']);

        Route::controller(ProfileController::class)->group(function () {
            Route::get('profile', 'profile');

            Route::get('user-profile/{id}', 'userProfile');

            Route::post('profile', 'updateProfile');
            Route::post('upload-avatar', 'updateProfilePicture');
            Route::post('follow', 'follow');

            Route::get('preferences', 'preferences');
            Route::post('preferences', 'updatePreferences');

            Route::get('followers-following/list', 'followFollowingList');

            Route::get('outfitters-users', 'outfitters');
        });
        Route::apiResource('lead', LeadController::class)->only(['index', 'store', 'update']);
        Route::get('lead-show', [LeadController::class, 'show']);


        Route::post('verify-payment', [PaymentController::class, 'verifyApple']);
        Route::post('google/verify-payment', [PaymentController::class, 'verifyGoogle']);

        Route::controller(TweetController::class)->group(function () {
            Route::get('tweets', 'index');
            Route::post('tweet', 'store');
            Route::get('tweet-like/{id}', 'tweetLike');
            Route::get('tweet-share/{id}', 'tweetShare');
            Route::post('tweet-comment/{id}', 'tweetComment');
        });

        Route::post('report-content',[ReportController::class,'store']);

        Route::apiResource('notification', NotificationController::class)->only(['index']);
        Route::get('notification/mark-as-read/{id}', [NotificationController::class, 'markAsRead']);
        Route::delete('notification/{id}', [NotificationController::class, 'destroy']);


        // premium subscription
        Route::post('google/verify-premium', [PremiumController::class, 'verifyGoogle']);
        Route::post('apple/verify-premium', [PremiumController::class, 'verifyApple']);








        Route::prefix('admin')->group(function () {

            Route::middleware('admin')->group(function () {

                // dashboard routes here
                Route::post('generate-bots',[BotController::class,'generateBot']);
                Route::get('bots',[BotController::class,'index']);
                Route::get('bots/{id}',[BotController::class,'show']);
                Route::post('update-bots/{id}',[BotController::class,'update']);
                Route::get('bot-activity-log',[BotController::class,'BotActivityLog']);
                Route::post('upload-bot-avatars',[BotController::class,'uploadBotAvatars']);
                Route::get('max-bot-create',[BotController::class,'MaxBotCreate']);
                Route::delete('delete-bot/{id}',[BotController::class,'deleteBot']);

            });
        });
});


Route::post('cancel-sub',[PaymentController::class,'googleCancel']);
