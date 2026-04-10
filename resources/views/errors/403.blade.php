<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="antialiased bg-emerald-50 dark:bg-zinc-950 min-h-screen">

        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-0 right-0 w-64 sm:w-96 h-64 sm:h-96 bg-emerald-200 dark:bg-emerald-900/20 rounded-full blur-3xl opacity-30 -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-64 sm:w-96 h-64 sm:h-96 bg-emerald-100 dark:bg-emerald-900/10 rounded-full blur-3xl opacity-30 translate-y-1/2 -translate-x-1/2"></div>
        </div>

        <div class="relative min-h-screen flex flex-col">
            <header class="border-b border-emerald-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-lg">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center h-16 lg:h-20">
                        <a href="{{ url('/') }}" class="flex items-center gap-2 sm:gap-4 group">
                            <div class="flex aspect-square size-9 sm:size-12 items-center justify-center rounded-xl sm:rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg group-hover:scale-105 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" class="size-5 sm:size-7 fill-current text-white">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M20 2L4 14v22a2 2 0 002 2h28a2 2 0 002-2V14L20 2zm0 4.5L32 16v18H8V16l12-9.5z"/>
                                    <circle cx="20" cy="24" r="6"/>
                                    <circle cx="12" cy="20" r="2.5"/>
                                    <circle cx="28" cy="20" r="2.5"/>
                                    <circle cx="20" cy="32" r="2.5"/>
                                    <path d="M14.5 21.5L17 23M23 23L25.5 21.5M20 27v3" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-lg sm:text-2xl font-black text-zinc-900 dark:text-white tracking-tight">BeCISS</h1>
                                <p class="text-[10px] sm:text-xs text-emerald-600 dark:text-emerald-400 font-medium tracking-widest uppercase">Barangay e-Community System</p>
                            </div>
                        </a>
                    </div>
                </div>
            </header>

            <main class="flex-1 flex items-center justify-center px-4 py-16 sm:py-24">
                <div class="text-center max-w-lg mx-auto">
                    <div class="relative inline-block mb-6">
                        <span class="text-[8rem] sm:text-[10rem] font-black leading-none text-emerald-100 dark:text-zinc-800 select-none">403</span>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl sm:rounded-3xl rotate-6 shadow-2xl flex items-center justify-center">
                                <svg class="w-10 h-10 sm:w-12 sm:h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-black text-zinc-900 dark:text-white mb-3">Access Denied</h2>
                    <p class="text-zinc-500 dark:text-zinc-400 text-sm sm:text-base leading-relaxed mb-8">
                        {{ $exception->getMessage() ?: "You don't have permission to access this page. Please contact your administrator if you believe this is an error." }}
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}"
                           class="inline-flex items-center gap-2 px-5 py-2.5 bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 rounded-xl font-semibold text-sm hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Go Back
                        </a>
                        <a href="{{ url('/') }}"
                           class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl font-semibold text-sm hover:from-emerald-600 hover:to-emerald-700 transition-colors shadow-md shadow-emerald-200 dark:shadow-emerald-900/30">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            Go Home
                        </a>
                    </div>
                </div>
            </main>

            <footer class="border-t border-emerald-200 dark:border-zinc-800 py-6 text-center text-xs text-zinc-400 dark:text-zinc-600">
                &copy; {{ date('Y') }} BeCISS &mdash; Barangay e-Community Information &amp; Services System
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
