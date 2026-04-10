<?php

use App\Http\Controllers\Auth\ForgotPasswordCodeController;
use App\Http\Controllers\Auth\VerifyEmailCodeController;
use App\Http\Controllers\BlotterDownloadController;
use App\Http\Controllers\CertificateDownloadController;
use App\Models\BarangayProfile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'barangay' => BarangayProfile::first(),
    ]);
})->name('home');

// Redirect login and register routes to home page (modals will handle auth)
Route::get('/login', function () {
    return redirect()->route('home');
})->name('login');

Route::get('/register', function () {
    return redirect()->route('home');
})->name('register');

// Forgot password (6-digit code flow)
Route::middleware('guest')->group(function () {
    Route::get('forgot-password', [ForgotPasswordCodeController::class, 'showRequestForm'])->name('password.request');
    Route::get('forgot-password/verify', [ForgotPasswordCodeController::class, 'showVerifyForm'])->name('password.verify-code');
    Route::get('forgot-password/reset', [ForgotPasswordCodeController::class, 'showResetForm'])->name('password.reset-form');

    // Throttle only state-changing POST requests
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('forgot-password', [ForgotPasswordCodeController::class, 'sendCode'])->name('password.email');
        Route::post('forgot-password/verify', [ForgotPasswordCodeController::class, 'verifyCode'])->name('password.verify-code.store');
        Route::post('forgot-password/reset', [ForgotPasswordCodeController::class, 'resetPassword'])->name('password.reset-update');
    });
});

Route::post('email/verify/code', VerifyEmailCodeController::class)
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.code');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('pending-approval', 'pages::pending-approval')->name('pending-approval');
    Route::livewire('complete-profile', 'pages::complete-profile')->name('complete-profile');

    Route::middleware(['resident.approved'])->group(function () {
        Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

        // Certificate Download (all authenticated roles)
        Route::get('certificates/{certificate}/download', CertificateDownloadController::class)->name('certificates.download');

        // Blotter Download (all authenticated roles)
        Route::get('blotters/{blotter}/download', BlotterDownloadController::class)->name('blotters.download');

        // Admin-only routes
        Route::middleware(['role:admin'])->group(function () {
            Route::livewire('admin/settings', 'pages::admin.settings.barangay')->name('admin.settings.barangay');
            Route::livewire('admin/settings/service-fees', 'pages::admin.settings.service-fees')->name('admin.settings.service-fees');
        });

        // Resident-only routes
        Route::middleware(['role:resident'])->group(function () {
            Route::livewire('my/certificates/create', 'pages::resident.certificates.create')->name('resident.certificates.create');
            Route::livewire('my/certificates', 'pages::resident.certificates.index')->name('resident.certificates.index');
            Route::livewire('my/appointments/create', 'pages::resident.appointments.create')->name('resident.appointments.create');
            Route::livewire('my/appointments', 'pages::resident.appointments.index')->name('resident.appointments.index');
            Route::livewire('my/blotters/create', 'pages::resident.blotters.create')->name('resident.blotters.create');
            Route::livewire('my/blotters', 'pages::resident.blotters.index')->name('resident.blotters.index');
            Route::livewire('my/notifications', 'pages::resident.notifications')->name('resident.notifications');
        });

        // Management Routes (admin and staff only)
        Route::middleware(['role:admin,staff'])->group(function () {
            // Residents Management
            Route::livewire('residents', 'pages::residents.index')->name('residents.index');
            Route::livewire('residents/create', 'pages::residents.create')->name('residents.create');
            Route::livewire('residents/{resident}', 'pages::residents.show')->name('residents.show');
            Route::livewire('residents/{resident}/edit', 'pages::residents.edit')->name('residents.edit');

            // Certificates Management
            Route::livewire('certificates', 'pages::certificates.index')->name('certificates.index');
            Route::livewire('certificates/create', 'pages::certificates.create')->name('certificates.create');
            Route::livewire('certificates/{certificate}', 'pages::certificates.show')->name('certificates.show');
            Route::livewire('certificates/{certificate}/edit', 'pages::certificates.edit')->name('certificates.edit');

            // Appointments Management
            Route::livewire('appointments', 'pages::appointments.index')->name('appointments.index');
            Route::livewire('appointments/create', 'pages::appointments.create')->name('appointments.create');
            Route::livewire('appointments/{appointment}', 'pages::appointments.show')->name('appointments.show');
            Route::livewire('appointments/{appointment}/edit', 'pages::appointments.edit')->name('appointments.edit');

            // Blotters Management
            Route::livewire('blotters', 'pages::blotters.index')->name('blotters.index');
            Route::livewire('blotters/create', 'pages::blotters.create')->name('blotters.create');
            Route::livewire('blotters/{blotter}', 'pages::blotters.show')->name('blotters.show');
            Route::livewire('blotters/{blotter}/edit', 'pages::blotters.edit')->name('blotters.edit');
        });
    });
});

require __DIR__.'/settings.php';
