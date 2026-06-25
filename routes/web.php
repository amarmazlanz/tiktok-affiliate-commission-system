<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Admin\AffiliateController;
use App\Http\Controllers\Admin\AffiliateHierarchyController;
use App\Http\Controllers\Admin\AffiliateImportController;
use App\Http\Controllers\Admin\AffiliateLoginReportController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\CommissionRateSettingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderImportController;
use App\Http\Controllers\Admin\TiktokAccountController;
use App\Http\Controllers\Affiliate\DashboardController as AffiliateDashboardController;
use App\Http\Controllers\Affiliate\CommissionController as AffiliateCommissionController;
use App\Http\Controllers\Affiliate\InviteController as AffiliateInviteController;
use App\Http\Controllers\Affiliate\PasswordController as AffiliatePasswordController;
use App\Http\Controllers\Affiliate\TeamController as AffiliateTeamController;
use App\Http\Controllers\Affiliate\TiktokAccountController as AffiliateTiktokAccountController;
use App\Http\Controllers\PublicAffiliateRegistrationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    return Auth::user()->role === 'admin'
        ? redirect()->route('admin.dashboard')
        : redirect()->route('affiliate.dashboard');
});

Route::get('/register/ref/{referralCode}', [PublicAffiliateRegistrationController::class, 'show'])
    ->where('referralCode', '[A-Za-z0-9-]+')
    ->name('public.affiliate-registration.show');
Route::post('/register/ref/{referralCode}', [PublicAffiliateRegistrationController::class, 'store'])
    ->where('referralCode', '[A-Za-z0-9-]+')
    ->middleware('throttle:5,1')
    ->name('public.affiliate-registration.store');
Route::get('/application/submitted/{applicationReference}', [PublicAffiliateRegistrationController::class, 'success'])
    ->where('applicationReference', 'APP-[0-9]{4}-[0-9]{6}')
    ->name('public.affiliate-registration.submitted');

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::post('/login', function (Request $request) {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($data['login']);
        $loginField = str_contains($login, '@') ? 'email' : 'affiliate_code';
        $login = $loginField === 'affiliate_code' ? strtoupper($login) : $login;

        if (! Auth::attempt([$loginField => $login, 'password' => $data['password']], $request->boolean('remember'))) {
            return back()
                ->withErrors(['login' => 'Maklumat login tidak sah.'])
                ->onlyInput('login');
        }

        $request->session()->regenerate();
        Auth::user()->forceFill(['last_login_at' => now()])->save();

        if (Auth::user()->role === 'admin') {
            return redirect()->intended(route('admin.dashboard'));
        }

        return Auth::user()->must_change_password
            ? redirect()->route('affiliate.password.edit')
            : redirect()->intended(route('affiliate.dashboard'));
    })->name('login.store');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('affiliates/hierarchy', AffiliateHierarchyController::class)->name('affiliates.hierarchy');
    Route::get('affiliates/login-report', [AffiliateLoginReportController::class, 'show'])->name('affiliates.login-report');
    Route::post('affiliates/login-report/generate', [AffiliateLoginReportController::class, 'confirm'])->name('affiliates.login-report.generate');
    Route::post('affiliates/login-report/generate/confirm', [AffiliateLoginReportController::class, 'generate'])->name('affiliates.login-report.generate.confirm');
    Route::get('affiliates/import', [AffiliateImportController::class, 'create'])->name('affiliates.import.create');
    Route::post('affiliates/import', [AffiliateImportController::class, 'store'])->name('affiliates.import.store');
    Route::post('affiliates/{affiliate}/reset-password', [AffiliateController::class, 'resetPassword'])->name('affiliates.reset-password');
    Route::patch('affiliates/{affiliate}/referral', [AffiliateController::class, 'updateReferral'])->name('affiliates.referral.update');

    Route::resource('affiliates', AffiliateController::class);
    Route::post('affiliates/{affiliate}/tiktok-accounts', [TiktokAccountController::class, 'store'])
        ->name('affiliates.tiktok-accounts.store');
    Route::delete('affiliates/{affiliate}/tiktok-accounts/{tiktokAccount}', [TiktokAccountController::class, 'destroy'])
        ->name('affiliates.tiktok-accounts.destroy');

    Route::get('orders/upload', [OrderImportController::class, 'create'])->name('orders.upload');
    Route::post('orders/upload', [OrderImportController::class, 'store'])->name('orders.import');

    Route::get('commissions', [CommissionController::class, 'index'])->name('commissions.index');
    Route::post('commissions', [CommissionController::class, 'store'])->name('commissions.store');
    Route::get('commissions/{commission}', [CommissionController::class, 'show'])->name('commissions.show');

    Route::resource('commission-rate-settings', CommissionRateSettingController::class)
        ->except(['show', 'destroy']);
});

Route::middleware(['auth', 'role:affiliate'])->prefix('affiliate')->name('affiliate.')->group(function () {
    Route::get('/password', [AffiliatePasswordController::class, 'edit'])->name('password.edit');
    Route::post('/password', [AffiliatePasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/', fn () => redirect()->route('affiliate.dashboard'));
        Route::get('/dashboard', AffiliateDashboardController::class)->name('dashboard');
        Route::get('/commission', AffiliateCommissionController::class)->name('commission');
        Route::get('/team', AffiliateTeamController::class)->name('team');
        Route::get('/tiktok-accounts', AffiliateTiktokAccountController::class)->name('tiktok-accounts');
        Route::get('/invite', AffiliateInviteController::class)->name('invite');
        Route::get('/settings', [AffiliatePasswordController::class, 'edit'])->name('settings');
    });
});
