<?php

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $user = app(CreateNewUser::class)->create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        Auth::login($user);
        session()->regenerate();
        $this->redirect(route('dashboard'), navigate: true);
    }
};
?>

<div>
    <flux:modal name="register" class="w-full max-w-md">
        <div class="flex flex-col gap-6 p-1">
            <div class="flex flex-col gap-1 text-center">
                <div class="flex items-center justify-center size-12 mx-auto rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 shadow-lg shadow-emerald-500/20 mb-2">
                    <svg class="size-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>

                <flux:heading size="xl">Create an account</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">Register to access barangay services online</flux:text>
            </div>

            <form wire:submit="register" class="flex flex-col gap-4">
                <flux:field>
                    <flux:label>Full Name</flux:label>
                    <flux:input wire:model="name" placeholder="Your full name" required autocomplete="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Email address</flux:label>
                    <flux:input type="email" wire:model="email" placeholder="email@example.com" required autocomplete="email" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>Password</flux:label>
                    <flux:input type="password" wire:model="password" placeholder="Password" viewable required autocomplete="new-password" />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>Confirm Password</flux:label>
                    <flux:input type="password" wire:model="password_confirmation" placeholder="Confirm your password" viewable required autocomplete="new-password" />
                    <flux:error name="password_confirmation" />
                </flux:field>

                <flux:button type="submit" variant="primary" class="w-full mt-1" wire:loading.attr="disabled">
                    <span wire:loading.remove>Create Account</span>
                    <span wire:loading>Creating account...</span>
                </flux:button>
            </form>

            <div class="text-sm text-center text-zinc-600 dark:text-zinc-400">
                Already have an account?
                <button
                    type="button"
                    x-on:click="$flux.modal('register').close(); $nextTick(() => $flux.modal('login').open())"
                    class="text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 font-medium hover:underline cursor-pointer"
                >Log in</button>
            </div>
        </div>
    </flux:modal>
</div>
</div>