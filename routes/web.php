<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

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

require __DIR__.'/settings.php';
