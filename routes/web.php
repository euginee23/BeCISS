<?php

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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('pending-approval', 'pages::pending-approval')->name('pending-approval');
    Route::livewire('complete-profile', 'pages::complete-profile')->name('complete-profile');

    Route::middleware(['resident.approved'])->group(function () {
        Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

        // Certificate Download (all authenticated roles)
        Route::get('certificates/{certificate}/download', CertificateDownloadController::class)->name('certificates.download');

        // Admin-only routes
        Route::middleware(['role:admin'])->group(function () {
            Route::livewire('admin/settings', 'pages::admin.settings.barangay')->name('admin.settings.barangay');
        });

        // Resident-only routes
        Route::middleware(['role:resident'])->group(function () {
            Route::livewire('my/certificates/create', 'pages::resident.certificates.create')->name('resident.certificates.create');
            Route::livewire('my/certificates', 'pages::resident.certificates.index')->name('resident.certificates.index');
            Route::livewire('my/appointments/create', 'pages::resident.appointments.create')->name('resident.appointments.create');
            Route::livewire('my/appointments', 'pages::resident.appointments.index')->name('resident.appointments.index');
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
        });
    });
});

require __DIR__.'/settings.php';
