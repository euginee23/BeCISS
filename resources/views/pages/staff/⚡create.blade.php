<?php

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Add Staff')]
#[Layout('layouts::app')]
class extends Component {
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $showPassword = false;

    public bool $isAdmin = false;

    /** @var list<string> */
    public array $permissions = [];

    public function mount(): void
    {
        $this->generatePassword();
    }

    public function generatePassword(): void
    {
        $this->password = Str::password(16);
    }

    public function updatedIsAdmin(): void
    {
        if ($this->isAdmin) {
            $this->permissions = User::STAFF_RESOURCES;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'isAdmin' => ['boolean'],
            'permissions' => ['required_if:isAdmin,false', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', User::STAFF_RESOURCES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'permissions.required_if' => __('Please select at least one resource panel for staff members.'),
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $permissions = $this->isAdmin ? User::STAFF_RESOURCES : $validated['permissions'];

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $this->isAdmin ? 'admin' : 'staff',
            'permissions' => $permissions,
        ]);

        $user->markEmailAsVerified();

        session()->flash('status', __('Staff member created successfully.'));

        $this->redirect(route('staff.index'), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('staff.index') }}">
            {{ __('Back to Staff') }}
        </flux:button>
    </div>

    <div class="mb-6">
        <flux:heading size="xl">{{ __('Add Staff') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Create a new staff or admin account') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8 max-w-2xl">
        {{-- Account Information --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Account Information') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Full Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="name" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Email Address') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="email" type="email" required />
                    <flux:error name="email" />
                </flux:field>
            </div>
        </div>

        {{-- Password --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Password') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Generated Password') }} <span class="text-red-500">*</span></flux:label>
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <flux:input
                            wire:model="password"
                            :type="$showPassword ? 'text' : 'password'"
                            readonly
                        />
                    </div>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        :icon="$showPassword ? 'eye-slash' : 'eye'"
                        wire:click="$toggle('showPassword')"
                        type="button"
                    />
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="arrow-path"
                        wire:click="generatePassword"
                        type="button"
                    />
                </div>
                <flux:error name="password" />
                <flux:text class="text-sm text-zinc-500 mt-1">
                    {{ __('Share this password with the staff member securely. They can change it later in their settings.') }}
                </flux:text>
            </flux:field>
        </div>

        {{-- Role & Permissions --}}
        <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Role & Permissions') }}</flux:heading>

            <div class="space-y-6">
                <flux:field>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:label>{{ __('Administrator') }}</flux:label>
                            <flux:text class="text-sm text-zinc-500">{{ __('Admins have full access to all resources and settings.') }}</flux:text>
                        </div>
                        <flux:switch wire:model.live="isAdmin" />
                    </div>
                </flux:field>

                <flux:separator />

                <div>
                    <flux:label class="mb-3">{{ __('Resource Access') }}</flux:label>
                    <flux:text class="text-sm text-zinc-500 mb-4">{{ __('Select which resource panels this staff member can access.') }}</flux:text>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 cursor-pointer {{ $isAdmin ? 'opacity-60' : '' }}">
                            <flux:checkbox wire:model="permissions" value="residents" :disabled="$isAdmin" />
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-white text-sm">{{ __('Residents') }}</div>
                                <div class="text-xs text-zinc-500">{{ __('Manage resident records') }}</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 cursor-pointer {{ $isAdmin ? 'opacity-60' : '' }}">
                            <flux:checkbox wire:model="permissions" value="certificates" :disabled="$isAdmin" />
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-white text-sm">{{ __('Certificates') }}</div>
                                <div class="text-xs text-zinc-500">{{ __('Process certificate requests') }}</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 cursor-pointer {{ $isAdmin ? 'opacity-60' : '' }}">
                            <flux:checkbox wire:model="permissions" value="appointments" :disabled="$isAdmin" />
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-white text-sm">{{ __('Appointments') }}</div>
                                <div class="text-xs text-zinc-500">{{ __('Manage appointments') }}</div>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 cursor-pointer {{ $isAdmin ? 'opacity-60' : '' }}">
                            <flux:checkbox wire:model="permissions" value="blotters" :disabled="$isAdmin" />
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-white text-sm">{{ __('Blotters') }}</div>
                                <div class="text-xs text-zinc-500">{{ __('Handle blotter reports') }}</div>
                            </div>
                        </label>
                    </div>

                    <flux:error name="permissions" />
                </div>
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" href="{{ route('staff.index') }}">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ __('Create Staff') }}
            </flux:button>
        </div>
    </form>
</div>
