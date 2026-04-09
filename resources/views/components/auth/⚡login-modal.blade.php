<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts('login:'.$throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn('login:'.$throttleKey),
                    'minutes' => 1,
                ]),
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit('login:'.$throttleKey);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear('login:'.$throttleKey);

        session()->regenerate();

        if (! Auth::user()->hasVerifiedEmail()) {
            Auth::user()->sendEmailVerificationNotification();
        }

        $this->redirect(route('dashboard'), navigate: true);
    }
};
?>

<div>
    <flux:modal name="login" class="w-full max-w-md">
        <div class="flex flex-col gap-6 p-1">
            <div class="flex flex-col gap-1 text-center">
                <div class="flex items-center justify-center size-12 mx-auto rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 shadow-lg shadow-emerald-500/20 mb-2">
                    <svg class="size-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <flux:heading size="xl">Welcome back</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">Sign in to access barangay services</flux:text>
            </div>

            <form wire:submit="login" class="flex flex-col gap-4">
                <flux:field>
                    <flux:label>Email address</flux:label>
                    <flux:input type="email" wire:model="email" placeholder="email@example.com" autofocus required autocomplete="email" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <div class="flex items-center justify-between mb-1">
                        <flux:label>Password</flux:label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm text-emerald-600 hover:text-emerald-700 dark:text-emerald-400">Forgot password?</a>
                        @endif
                    </div>
                    <flux:input type="password" wire:model="password" placeholder="Password" viewable required autocomplete="current-password" />
                    <flux:error name="password" />
                </flux:field>

                <flux:checkbox wire:model="remember" label="Remember me" />

                <flux:button type="submit" variant="primary" class="w-full mt-1" wire:loading.attr="disabled">
                    <span wire:loading.remove>Log in</span>
                    <span wire:loading>Signing in...</span>
                </flux:button>
            </form>

            @if (Route::has('register'))
                <div class="text-sm text-center text-zinc-600 dark:text-zinc-400">
                    Don't have an account?
                    <button
                        type="button"
                        x-on:click="$flux.modal('login').close(); $nextTick(() => $flux.modal('register').open())"
                        class="text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 font-medium hover:underline cursor-pointer"
                    >Sign up</button>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
</div>