<?php

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Edit Staff')]
#[Layout('layouts::app')]
class extends Component {
    public User $user;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $showPassword = false;

    public bool $resetPassword = false;

    public bool $isAdmin = false;

    /** @var list<string> */
    public array $permissions = [];

    public bool $isSelf = false;

    public function mount(User $user): void
    {
        if (! $user->hasRole(['admin', 'staff'])) {
            abort(404);
        }

        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->isAdmin = $user->isAdmin();
        $this->permissions = $user->permissions ?? [];
        $this->isSelf = $user->id === auth()->id();
    }

    public function generatePassword(): void
    {
        $this->password = Str::password(16);
        $this->resetPassword = true;
        $this->showPassword = true;
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $this->user->id],
            'isAdmin' => ['boolean'],
            'permissions' => ['required_if:isAdmin,false', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', User::STAFF_RESOURCES)],
        ];

        if ($this->resetPassword) {
            $rules['password'] = ['required', 'string', Password::defaults()];
        }

        return $rules;
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

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'permissions' => $permissions,
        ];

        if (! $this->isSelf) {
            $data['role'] = $this->isAdmin ? 'admin' : 'staff';
        }

        if ($this->resetPassword) {
            $data['password'] = $validated['password'];
        }

        $this->user->update($data);

        session()->flash('status', __('Staff member updated successfully.'));

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
        <flux:heading size="xl">{{ __('Edit Staff') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Update staff account and resource access') }}</flux:text>
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

            @if($resetPassword)
                <flux:field>
                    <flux:label>{{ __('New Password') }}</flux:label>
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
                        {{ __('Share this password with the staff member securely.') }}
                    </flux:text>
                </flux:field>
            @else
                <flux:text class="text-sm text-zinc-500">{{ __('Leave the password as is, or generate a new one.') }}</flux:text>
                <div class="mt-3">
                    <flux:button variant="outline" size="sm" icon="arrow-path" wire:click="generatePassword" type="button">
                        {{ __('Reset Password') }}
                    </flux:button>
                </div>
            @endif
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
                        <flux:switch wire:model.live="isAdmin" :disabled="$isSelf" />
                    </div>
                    @if($isSelf)
                        <flux:text class="text-sm text-amber-600 dark:text-amber-400 mt-2">{{ __('You cannot change your own role.') }}</flux:text>
                    @endif
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
                {{ __('Update Staff') }}
            </flux:button>
        </div>
    </form>
</div>
