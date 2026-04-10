<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Under Maintenance &mdash; BeCISS</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,900" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-emerald-50 dark:bg-zinc-950 min-h-screen" style="font-family: 'Instrument Sans', sans-serif;">

        <div style="position:fixed;inset:0;overflow:hidden;pointer-events:none;">
            <div style="position:absolute;top:0;right:0;width:24rem;height:24rem;background:rgba(167,243,208,0.3);border-radius:9999px;filter:blur(64px);transform:translate(50%,-50%);"></div>
            <div style="position:absolute;bottom:0;left:0;width:24rem;height:24rem;background:rgba(209,250,229,0.3);border-radius:9999px;filter:blur(64px);transform:translate(-50%,50%);"></div>
        </div>

        <div class="relative min-h-screen flex flex-col">
            <header class="border-b border-emerald-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-lg">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center h-16 lg:h-20">
                        <div class="flex items-center gap-2 sm:gap-4">
                            <div class="flex aspect-square size-9 sm:size-12 items-center justify-center rounded-xl sm:rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-lg">
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
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 flex items-center justify-center px-4 py-16 sm:py-24">
                <div class="text-center max-w-lg mx-auto">
                    <div class="relative inline-block mb-6">
                        <span class="text-[8rem] sm:text-[10rem] font-black leading-none text-emerald-100 dark:text-zinc-800 select-none">503</span>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl sm:rounded-3xl rotate-6 shadow-2xl flex items-center justify-center">
                                <svg class="w-10 h-10 sm:w-12 sm:h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-black text-zinc-900 dark:text-white mb-3">Under Maintenance</h2>
                    <p class="text-zinc-500 dark:text-zinc-400 text-sm sm:text-base leading-relaxed mb-8">
                        The system is currently undergoing scheduled maintenance. We'll be back shortly. Thank you for your patience.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <button onclick="window.location.reload()"
                           class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl font-semibold text-sm hover:from-emerald-600 hover:to-emerald-700 transition-colors shadow-md shadow-emerald-200 dark:shadow-emerald-900/30 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Check Again
                        </button>
                    </div>
                </div>
            </main>

            <footer class="border-t border-emerald-200 dark:border-zinc-800 py-6 text-center text-xs text-zinc-400 dark:text-zinc-600">
                &copy; {{ date('Y') }} BeCISS &mdash; Barangay e-Community Information &amp; Services System
            </footer>
        </div>

    </body>
</html>
