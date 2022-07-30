<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ApiAuthController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\ApiUserController;
use App\Http\Controllers\ApiPermissionController;
use App\Http\Controllers\ApiRoleController;
use App\Http\Controllers\ApiAddressController;
use App\Http\Controllers\ApiKycController;
use App\Http\Controllers\ApiInvoiceController;
use App\Http\Controllers\ApiReferralController;
use App\Http\Controllers\ApiDashboardController;
use App\Http\Controllers\ApiSettingController;
use App\Http\Controllers\ApiForgotPasswordController;
use App\Http\Controllers\ApiResetPasswordController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['cors', 'json.response']], function () {

});

// public routes
Route::post('/login', [ApiAuthController::class, 'login'])->name('login.api');
Route::post('/register',[ApiAuthController::class, 'register'])->name('register.api');

Route::post('password/reset', [ApiResetPasswordController::class, 'reset']);
Route::post('password/email', [ApiForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');


Route::middleware('auth:api', 'verified')->group(function () {
    // our routes to be protected will go in here
    Route::post('logout', [ApiAuthController::class, 'logout'])->name('logout.api');
    Route::post('changepwd', [ApiAuthController::class, 'changePwd']);
    Route::post('updateprofile', [ApiAuthController::class, 'updateProfile']);
    // Permissions
    Route::get('permissions/all', [ApiPermissionController::class, 'all']);
    Route::apiResource('permissions', ApiPermissionController::class);

    // Roles
    Route::get('roles/all', [ApiRoleController::class, 'all']);
    Route::apiResource('roles', ApiRoleController::class);

    // Users
    Route::get('users/search/{uid}', [ApiUserController::class, 'search'])->middleware('referral');
    Route::get('users/{user}/verify', [ApiUserController::class, 'verify']);
    Route::apiResource('users', ApiUserController::class);

    // addresses
    Route::get('addresses/{address}/primary', [ApiAddressController::class, 'setAsPrimary']);
    Route::apiResource('addresses', ApiAddressController::class);

    // kyc
    Route::get('kyc', [ApiKycController::class, 'index']);
    Route::get('kyc/user/me', [ApiKycController::class, 'getKyc']);
    Route::get('kyc/user/{user}', [ApiKycController::class, 'getKycByUser']);
    Route::post('kyc/bvn', [ApiKycController::class, 'updateBvn']);
    Route::post('kyc/doc', [ApiKycController::class, 'uploadDoc']);
    Route::delete('kyc/doc/{document}', [ApiKycController::class, 'destroyDoc']);
    Route::get('kyc/{kyc}/approve', [ApiKycController::class, 'approve']);
    Route::get('kyc/{kyc}/reject', [ApiKycController::class, 'reject']);

    // Invoice
    Route::post('invoices/{invoice}/pay', [ApiInvoiceController::class, 'pay']);
    Route::apiResource('invoices', ApiInvoiceController::class);

    // Referral
    Route::get('referrals/resend/{referral}', [ApiReferralController::class, 'resend']);
    Route::apiResource('referrals', ApiReferralController::class);

    // Dashboard
    Route::get('generalreport', [ApiDashboardController::class, 'generalReport']);
    Route::get('salesreport', [ApiDashboardController::class, 'salesReport']);
    Route::get('statreport', [ApiDashboardController::class, 'statReport']);
    Route::get('invoicespending', [ApiDashboardController::class, 'invoicesPending']);

    // Setting
    Route::get('settings/{key}', [ApiSettingController::class, 'getSettingByKey']);
    Route::post('settings/{key}', [ApiSettingController::class, 'setSettingByKey']);
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
