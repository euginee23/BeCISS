<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="scroll-behavior: smooth; scroll-padding-top: 64px;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'BeCISS') }} - Barangay e-Connect & Intelligent Service Scheduling</title>
    <meta name="description" content="BeCISS is a web-based barangay management system that digitalizes resident records, certificate issuance, and service scheduling.">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-white dark:bg-zinc-950 min-h-screen">
    {{-- Decorative Background Elements --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-0 right-0 w-64 sm:w-96 h-64 sm:h-96 bg-emerald-200 dark:bg-emerald-900/20 rounded-full blur-3xl opacity-30 -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-64 sm:w-96 h-64 sm:h-96 bg-lime-200 dark:bg-lime-900/20 rounded-full blur-3xl opacity-30 translate-y-1/2 -translate-x-1/2"></div>
        <div class="absolute top-1/2 left-1/2 w-48 h-48 bg-teal-200 dark:bg-teal-900/10 rounded-full blur-3xl opacity-20 -translate-x-1/2 -translate-y-1/2"></div>
    </div>

    {{-- Navigation --}}
    <nav class="fixed top-0 left-0 right-0 z-50 border-b bg-white/80 dark:bg-zinc-900/80 backdrop-blur-lg border-zinc-200 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                {{-- Logo --}}
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 shadow-lg shadow-emerald-500/20">
                        <x-app-logo-icon class="size-6 text-white" />
                    </div>
                    <div>
                        <span class="text-lg font-bold text-zinc-900 dark:text-white">BeCISS</span>
                        <span class="hidden sm:inline text-xs text-zinc-500 dark:text-zinc-400 ml-2">Barangay e-Connect</span>
                    </div>
                </div>

                {{-- Nav Links --}}
                <div class="hidden md:flex items-center gap-1">
                    <a href="#features" class="px-3 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-emerald-600 dark:hover:text-emerald-400 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                        Features
                    </a>
                    <a href="#services" class="px-3 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-emerald-600 dark:hover:text-emerald-400 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                        Services
                    </a>
                    <a href="#about" class="px-3 py-2 text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-emerald-600 dark:hover:text-emerald-400 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                        About
                    </a>
                </div>

                {{-- Auth Buttons --}}
                @if (Route::has('login'))
                    <div class="flex items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 rounded-lg shadow-lg shadow-emerald-500/20 transition-all hover:shadow-xl hover:shadow-emerald-500/30">
                                Dashboard
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">
                                Log in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 rounded-lg shadow-lg shadow-emerald-500/20 transition-all hover:shadow-xl hover:shadow-emerald-500/30">
                                    Register
                                </a>
                            @endif
                        @endauth
                    </div>
                @endif
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <section class="relative pt-32 pb-20 sm:pt-40 sm:pb-28 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-4xl mx-auto">
                {{-- Badge --}}
                <div class="inline-flex items-center gap-2 px-4 py-2 mb-6 text-sm font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/30 rounded-full border border-emerald-200 dark:border-emerald-800">
                    <svg class="size-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    Digitalizing Barangay Services
                </div>

                {{-- Heading --}}
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-zinc-900 dark:text-white leading-tight mb-6">
                    Barangay
                    <span class="text-transparent bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text">e-Connect</span>
                    & Intelligent Service Scheduling
                </h1>

                {{-- Subheading --}}
                <p class="text-lg sm:text-xl text-zinc-600 dark:text-zinc-400 mb-10 max-w-2xl mx-auto leading-relaxed">
                    A modern web-based platform designed to streamline barangay operations, manage resident records, handle certificate requests, and provide online service scheduling — all in one place.
                </p>

                {{-- CTA Buttons --}}
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 text-base font-semibold text-white bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 rounded-xl shadow-xl shadow-emerald-500/20 transition-all hover:shadow-2xl hover:shadow-emerald-500/30 hover:-translate-y-0.5">
                            Go to Dashboard
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 text-base font-semibold text-white bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 rounded-xl shadow-xl shadow-emerald-500/20 transition-all hover:shadow-2xl hover:shadow-emerald-500/30 hover:-translate-y-0.5">
                            Get Started
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                        <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 text-base font-semibold text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 hover:border-emerald-300 dark:hover:border-emerald-700 rounded-xl shadow-sm transition-all hover:shadow-md hover:-translate-y-0.5">
                            Sign In
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </section>

    {{-- Stats Section --}}
    <section class="py-16 bg-zinc-50 dark:bg-zinc-900/50 border-y border-zinc-200 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="text-3xl sm:text-4xl font-bold text-transparent bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text">24/7</div>
                    <p class="mt-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">Online Access</p>
                </div>
                <div class="text-center">
                    <div class="text-3xl sm:text-4xl font-bold text-transparent bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text">100%</div>
                    <p class="mt-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">Paperless Process</p>
                </div>
                <div class="text-center">
                    <div class="text-3xl sm:text-4xl font-bold text-transparent bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text">&lt;5min</div>
                    <p class="mt-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">Request Time</p>
                </div>
                <div class="text-center">
                    <div class="text-3xl sm:text-4xl font-bold text-transparent bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text">Secure</div>
                    <p class="mt-2 text-sm font-medium text-zinc-600 dark:text-zinc-400">Data Protection</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Features Section --}}
    <section id="features" class="py-20 sm:py-28">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-zinc-900 dark:text-white mb-4">
                    Everything Your Barangay Needs
                </h2>
                <p class="text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                    Comprehensive digital solutions designed specifically for barangay operations and community services.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- Feature 1: Resident Management --}}
                <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent dark:from-emerald-950/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative">
                        <div class="w-14 h-14 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <svg class="size-7 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-2">Resident Management</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Centralized database for all resident records, household information, and demographic data.
                        </p>
                    </div>
                </div>

                {{-- Feature 2: Certificate Requests --}}
                <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-teal-50 to-transparent dark:from-teal-950/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative">
                        <div class="w-14 h-14 bg-teal-100 dark:bg-teal-900/30 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <svg class="size-7 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-2">Certificate Requests</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Online request and issuance of barangay clearance, indigency, residency, and other certificates.
                        </p>
                    </div>
                </div>

                {{-- Feature 3: Service Scheduling --}}
                <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-lime-50 to-transparent dark:from-lime-950/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative">
                        <div class="w-14 h-14 bg-lime-100 dark:bg-lime-900/30 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <svg class="size-7 text-lime-600 dark:text-lime-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-2">Service Scheduling</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Book appointments online and avoid long queues with intelligent scheduling system.
                        </p>
                    </div>
                </div>

                {{-- Feature 4: Digital Records --}}
                <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent dark:from-emerald-950/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative">
                        <div class="w-14 h-14 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <svg class="size-7 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-2">Digital Records</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            Secure storage and easy retrieval of all barangay documents and transaction history.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Services Section --}}
    <section id="services" class="py-20 sm:py-28 bg-zinc-50 dark:bg-zinc-900/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-zinc-900 dark:text-white mb-4">
                    Available Barangay Certificates
                </h2>
                <p class="text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                    Request these certificates online and pick them up at your convenience.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                {{-- Barangay Clearance --}}
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center mb-4">
                        <svg class="size-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white mb-2">Barangay Clearance</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">General purpose clearance for employment, business, and other transactions</p>
                </div>

                {{-- Certificate of Indigency --}}
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center mb-4">
                        <svg class="size-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white mb-2">Certificate of Indigency</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">For medical assistance, scholarship applications, and social services</p>
                </div>

                {{-- Certificate of Residency --}}
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center mb-4">
                        <svg class="size-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white mb-2">Certificate of Residency</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Proof of residence for various government and private transactions</p>
                </div>

                {{-- Business Permit Clearance --}}
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center mb-4">
                        <svg class="size-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white mb-2">Business Permit Clearance</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Required for business permit applications within the barangay</p>
                </div>

                {{-- Certificate of Good Moral --}}
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center mb-4">
                        <svg class="size-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white mb-2">Certificate of Good Moral</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Character reference for employment, school, and other purposes</p>
                </div>

                {{-- First Time Job Seeker --}}
                <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-6 hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center mb-4">
                        <svg class="size-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white mb-2">First Time Job Seeker</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Certificate for first-time job seekers under RA 11261</p>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA Section --}}
    <section class="py-20 sm:py-28">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="bg-gradient-to-br from-emerald-600 to-teal-700 rounded-3xl p-10 sm:p-16 relative overflow-hidden">
                {{-- Decorative elements --}}
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/10 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>
                
                <div class="relative">
                    <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">
                        Ready to Experience Modern Barangay Services?
                    </h2>
                    <p class="text-lg text-emerald-100 mb-8 max-w-xl mx-auto">
                        Join BeCISS today and enjoy hassle-free access to barangay services from anywhere, anytime.
                    </p>
                    @guest
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-8 py-4 text-base font-semibold text-emerald-700 bg-white hover:bg-emerald-50 rounded-xl shadow-xl transition-all hover:shadow-2xl hover:-translate-y-0.5">
                            Create Your Account
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                    @endguest
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer id="about" class="bg-zinc-950 text-white py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
                {{-- Brand --}}
                <div class="lg:col-span-2">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex items-center justify-center size-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700">
                            <x-app-logo-icon class="size-6 text-white" />
                        </div>
                        <span class="text-xl font-bold">BeCISS</span>
                    </div>
                    <p class="text-zinc-400 text-sm leading-relaxed max-w-md">
                        Barangay e-Connect & Intelligent Service Scheduling (BeCISS) — A web-based barangay management system that digitalizes resident records, certificate issuance, and service scheduling to improve efficiency and reduce manual processes.
                    </p>
                </div>

                {{-- Quick Links --}}
                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-zinc-400 mb-4">Quick Links</h4>
                    <ul class="space-y-3">
                        <li><a href="#features" class="text-sm text-zinc-400 hover:text-emerald-400 transition-colors">Features</a></li>
                        <li><a href="#services" class="text-sm text-zinc-400 hover:text-emerald-400 transition-colors">Services</a></li>
                        @guest
                            <li><a href="{{ route('login') }}" class="text-sm text-zinc-400 hover:text-emerald-400 transition-colors">Sign In</a></li>
                            <li><a href="{{ route('register') }}" class="text-sm text-zinc-400 hover:text-emerald-400 transition-colors">Register</a></li>
                        @endguest
                    </ul>
                </div>

                {{-- Contact --}}
                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-zinc-400 mb-4">Contact</h4>
                    <ul class="space-y-3 text-sm text-zinc-400">
                        <li>Barangay Hall</li>
                        <li>Your Municipality, Province</li>
                        <li>contact@beciss.local</li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-zinc-800 pt-8 text-center">
                <p class="text-sm text-zinc-500">
                    &copy; {{ date('Y') }} BeCISS. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
    {{-- Scroll to Top Button --}}
    <button
        id="scroll-to-top"
        onclick="window.scrollTo({ top: 0, behavior: 'smooth' })"
        class="fixed bottom-6 right-6 z-50 flex items-center justify-center size-12 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:shadow-emerald-500/40 transition-all hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 opacity-0 pointer-events-none"
        style="transition: opacity 0.3s, transform 0.3s;"
        aria-label="Scroll to top"
    >
        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
        </svg>
    </button>

    <script>
        (function () {
            var btn = document.getElementById('scroll-to-top');
            window.addEventListener('scroll', function () {
                if (window.scrollY > 400) {
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                    btn.style.transform = 'translateY(0)';
                } else {
                    btn.style.opacity = '0';
                    btn.style.pointerEvents = 'none';
                    btn.style.transform = 'translateY(1rem)';
                }
            }, { passive: true });
        })();
    </script>
</body>
</html>
