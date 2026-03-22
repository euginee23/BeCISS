<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Pending Approval')]
#[Layout('layouts::auth')]
class extends Component {
}; ?>

<div class="flex flex-col items-center gap-6 text-center">
    {{-- Icon --}}
    <div class="flex items-center justify-center size-20 rounded-2xl bg-gradient-to-br from-amber-100 to-amber-200 dark:from-amber-900/30 dark:to-amber-800/20">
        <flux:icon name="clock" class="size-10 text-amber-600 dark:text-amber-400" />
    </div>

    {{-- Heading --}}
    <div>
        <flux:heading size="xl" class="text-zinc-900 dark:text-white">
            {{ __('Registration Pending Approval') }}
        </flux:heading>
        <flux:text class="mt-3 text-zinc-600 dark:text-zinc-400 max-w-sm leading-relaxed">
            {{ __('Thank you for submitting your information. Our barangay staff is reviewing your registration. You will be able to access the system once your account has been approved.') }}
        </flux:text>
    </div>

    {{-- Status Badge --}}
    <div class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 rounded-full border border-amber-200 dark:border-amber-800">
        <span class="relative flex size-2">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
            <span class="relative inline-flex size-2 rounded-full bg-amber-500"></span>
        </span>
        {{ __('Awaiting Staff Review') }}
    </div>

    {{-- Logout --}}
    <form method="POST" action="{{ route('logout') }}" class="mt-2">
        @csrf
        <flux:button variant="primary" type="submit" class="cursor-pointer">
            {{ __('Log out') }}
        </flux:button>
    </form>
</div>
