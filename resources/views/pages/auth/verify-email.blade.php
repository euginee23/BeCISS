<x-layouts::auth :title="__('Email verification')">
    <div class="mt-4 flex flex-col gap-6">
        <flux:text class="text-center">
            {{ __('Enter the 6-digit verification code sent to your email address.') }}
        </flux:text>

        @if (session('status') == 'verification-link-sent')
            <flux:text class="text-center font-medium !dark:text-green-400 !text-green-600">
                {{ __('A new verification code has been sent to your email address.') }}
            </flux:text>
        @endif

        <form method="POST" action="{{ route('verification.code') }}" class="flex flex-col gap-4">
            @csrf
            <flux:field>
                <flux:label>{{ __('Verification Code') }}</flux:label>
                <flux:input name="code" type="text" inputmode="numeric" maxlength="6" placeholder="000000" required autofocus />
                <flux:error name="code" />
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Verify Email') }}
            </flux:button>
        </form>

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="ghost" class="text-sm cursor-pointer">
                    {{ __('Resend verification code') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button variant="ghost" type="submit" class="text-sm cursor-pointer" data-test="logout-button">
                    {{ __('Log out') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>
