<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\BannerController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\FaqController;
use App\Http\Controllers\Api\Admin\PackageManagementController;
use App\Http\Controllers\Api\Admin\ProcedureController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\WebsiteSettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PublicSiteController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\WhatsAppController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Lapisan akses (jangan dilewati, otorisasi harus selalu di backend):
|   1. Publik      : tanpa login (dibatasi rate limit)
|   2. Login       : auth:sanctum + active
|   3. Staff       : role:admin,employee   (operasional laundry dan akun sendiri)
|   4. Admin saja  : role:admin            (laporan, website, karyawan)
|
| Throttle: format throttle:maks,menit,AWALAN. Tanpa awalan, semua route yang memakai
| throttle:N,M berbagi satu hitungan per pengguna/IP, sehingga satu route bisa
| menghabiskan jatah route lain. Karena itu setiap throttle diberi awalan sendiri.
*/

// 1. Publik
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:30,1,login')
        ->name('login');

    // Logout sengaja tanpa "active" agar akun nonaktif tetap bisa mengakhiri sesinya.
    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('logout');
});

Route::prefix('public')->name('public.')->group(function () {
    // Konten website publik (bisnis, kontak, visi misi, warna, paket, prosedur, FAQ)
    Route::get('site', [PublicSiteController::class, 'show'])
        ->middleware('throttle:60,1,public-site')
        ->name('site');

    // Logo dan favicon. Nama file: 40 karakter acak + ekstensi. Format lain langsung 404.
    Route::get('media/{file}', [MediaController::class, 'show'])
        ->where('file', '[A-Za-z0-9]{40}\.(?:png|jpg|jpeg|webp|ico)')
        ->middleware('throttle:240,1,public-media')
        ->name('media');

    // Token tracking: 40 karakter alfanumerik. Format lain langsung 404.
    Route::get('tracking/{token}', [TrackingController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->middleware('throttle:tracking')
        ->name('tracking.show');

    Route::get('tracking/{token}/qr', [TrackingController::class, 'qr'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->middleware('throttle:tracking-qr')
        ->name('tracking.qr');
});

// 2. Harus login dan akun aktif
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    // 3. Admin dan Karyawan
    Route::middleware('role:admin,employee')->group(function () {
        Route::get('packages', [PackageController::class, 'index'])->name('packages.index');

        // Dashboard dan notifikasi (pemasukan hanya dikirim untuk admin)
        Route::get('dashboard', [DashboardController::class, 'show'])
            ->middleware('throttle:120,1,dashboard')
            ->name('dashboard');
        Route::get('notifications', [DashboardController::class, 'notifications'])
            ->middleware('throttle:120,1,notifications')
            ->name('notifications');

        // Harus didefinisikan SEBELUM apiResource agar tidak dianggap {transaction}.
        Route::get('transactions/statuses', [TransactionController::class, 'statuses'])
            ->name('transactions.statuses');
        Route::get('transactions/{transaction}/qr', [QrCodeController::class, 'show'])
            ->name('transactions.qr');

        // WhatsApp: GET = pratinjau (tanpa efek samping), POST = catat "WhatsApp dibuka"
        Route::get('transactions/{transaction}/whatsapp', [WhatsAppController::class, 'preview'])
            ->name('transactions.whatsapp.preview');
        Route::post('transactions/{transaction}/whatsapp', [WhatsAppController::class, 'opened'])
            ->middleware('throttle:60,1,whatsapp-open')
            ->name('transactions.whatsapp.opened');

        Route::post('transactions/{transaction}/status', [TransactionController::class, 'changeStatus'])
            ->name('transactions.change-status');
        Route::apiResource('transactions', TransactionController::class);

        // Verifikasi QR / kode transaksi (POST agar token tidak masuk log akses)
        Route::post('verifications/lookup', [VerificationController::class, 'lookup'])
            ->middleware('throttle:60,1,verify-lookup')
            ->name('verifications.lookup');
        Route::post('verifications/complete', [VerificationController::class, 'complete'])
            ->middleware('throttle:30,1,verify-complete')
            ->name('verifications.complete');

        // Pengaturan akun sendiri (tidak ada ID di URL: selalu akun yang sedang login)
        Route::prefix('account')->name('account.')->group(function () {
            Route::get('/', [AccountController::class, 'show'])->name('show');
            Route::put('profile', [AccountController::class, 'updateProfile'])
                ->middleware('throttle:30,1,account-profile')
                ->name('profile.update');
            Route::put('password', [AccountController::class, 'updatePassword'])
                ->middleware('throttle:5,1,account-password')
                ->name('password.update');
            Route::post('avatar', [AccountController::class, 'uploadAvatar'])
                ->middleware('throttle:10,1,account-avatar')
                ->name('avatar.upload');
            Route::delete('avatar', [AccountController::class, 'deleteAvatar'])->name('avatar.delete');
        });

        // Foto profil disajikan hanya kepada staff yang login.
        Route::get('avatars/{file}', [AvatarController::class, 'show'])
            ->where('file', '[A-Za-z0-9]{40}\.png')
            ->name('avatars.show');
    });

    // 4. Hanya Admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        // Laporan pemasukan
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'show'])->name('show');
            Route::get('transactions', [ReportController::class, 'transactions'])->name('transactions');
            Route::get('print', [ReportController::class, 'print'])->name('print');
            Route::get('export', [ReportController::class, 'export'])
                ->middleware('throttle:10,1,report-export')
                ->name('export');
        });

        // Pengaturan website
        Route::prefix('website')->name('website.')->group(function () {
            Route::get('/', [WebsiteSettingController::class, 'show'])->name('show');
            Route::put('settings', [WebsiteSettingController::class, 'update'])->name('settings.update');

            Route::post('images/{kind}', [WebsiteSettingController::class, 'uploadImage'])
                ->where('kind', 'logo|favicon')
                ->middleware('throttle:20,1,website-images')
                ->name('images.upload');
            Route::delete('images/{kind}', [WebsiteSettingController::class, 'deleteImage'])
                ->where('kind', 'logo|favicon')
                ->name('images.delete');

            Route::put('colors', [WebsiteSettingController::class, 'updateColors'])->name('colors.update');
            Route::post('colors/reset', [WebsiteSettingController::class, 'resetColors'])->name('colors.reset');

            // Banner promosi PNG (gambar dibuat di browser; backend menyediakan ukuran dan mencatat ekspor)
            Route::get('banner/presets', [BannerController::class, 'presets'])->name('banner.presets');
            Route::post('banner/exports', [BannerController::class, 'exported'])
                ->middleware('throttle:30,1,banner-export')
                ->name('banner.exports');
        });

        // Rute "reorder" dan "restore" harus SEBELUM apiResource.
        Route::post('packages/reorder', [PackageManagementController::class, 'reorder'])->name('packages.reorder');
        Route::post('packages/{id}/restore', [PackageManagementController::class, 'restore'])
            ->where('id', '[0-9]+')
            ->name('packages.restore');
        Route::apiResource('packages', PackageManagementController::class)->except(['show']);

        Route::post('procedures/reorder', [ProcedureController::class, 'reorder'])->name('procedures.reorder');
        Route::apiResource('procedures', ProcedureController::class)->except(['show']);

        Route::post('faqs/reorder', [FaqController::class, 'reorder'])->name('faqs.reorder');
        Route::apiResource('faqs', FaqController::class)->except(['show']);

        // Kelola karyawan. {employee} hanya cocok dengan akun berole karyawan.
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('employees', [EmployeeController::class, 'store'])
            ->middleware('throttle:30,1,employee-create')
            ->name('employees.store');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::patch('employees/{employee}/status', [EmployeeController::class, 'changeStatus'])->name('employees.status');
        Route::post('employees/{employee}/password', [EmployeeController::class, 'resetPassword'])
            ->middleware('throttle:20,1,employee-password')
            ->name('employees.password');
        Route::get('employees/{employee}/activities', [EmployeeController::class, 'activities'])->name('employees.activities');
    });
});