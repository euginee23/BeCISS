<x-layouts::auth :title="__('Verify Reset Code')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Verify Reset Code')" :description="__('Enter the 6-digit code sent to your email address.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.verify-code.store') }}" class="flex flex-col gap-4">
            @csrf

            <input type="hidden" name="email" value="{{ old('email', $email) }}" />

            <flux:field>
                <flux:label>{{ __('Verification Code') }}</flux:label>
                <flux:input name="code" type="text" inputmode="numeric" maxlength="6" placeholder="000000" required autofocus />
                <flux:error name="code" />
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Verify Code') }}
            </flux:button>
        </form>

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <input type="hidden" name="email" value="{{ old('email', $email) }}" />
                <flux:button type="submit" variant="ghost" class="text-sm cursor-pointer">
                    {{ __('Resend code') }}
                </flux:button>
            </form>

            <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
                <span>{{ __('Or, return to') }}</span>
                <flux:link :href="route('home')" wire:navigate>{{ __('log in') }}</flux:link>
            </div>
        </div>
    </div>
</x-layouts::auth>
