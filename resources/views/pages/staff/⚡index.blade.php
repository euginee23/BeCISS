<?php

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Staff')]
#[Layout('layouts::app')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortBy = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDeleteModal = false;

    public ?int $staffToDelete = null;

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        if ($id === auth()->id()) {
            return;
        }

        $this->staffToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function deleteStaff(): void
    {
        if ($this->staffToDelete && $this->staffToDelete !== auth()->id()) {
            User::where('id', $this->staffToDelete)
                ->whereIn('role', ['staff', 'admin'])
                ->delete();

            $this->showDeleteModal = false;
            $this->staffToDelete = null;
            unset($this->staffMembers);
        }
    }

    #[Computed]
    public function staffMembers()
    {
        return User::query()
            ->whereIn('role', ['staff', 'admin'])
            ->when($this->search, fn ($query, $search) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                )
            )
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }
}; ?>

<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Staff') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Manage staff accounts and resource access') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" href="{{ route('staff.create') }}">
            {{ __('Add Staff') }}
        </flux:button>
    </div>

    <div class="mb-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search staff...') }}"
            class="max-w-sm"
        />
    </div>

    <flux:table :paginate="$this->staffMembers">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">
                {{ __('Name') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">
                {{ __('Email') }}
            </flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column class="text-center">{{ __('Residents') }}</flux:table.column>
            <flux:table.column class="text-center">{{ __('Certificates') }}</flux:table.column>
            <flux:table.column class="text-center">{{ __('Appointments') }}</flux:table.column>
            <flux:table.column class="text-center">{{ __('Blotters') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->staffMembers as $staff)
                <flux:table.row :key="$staff->id">
                    <flux:table.cell variant="strong">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="xs" name="{{ $staff->name }}" />
                            <span>{{ $staff->name }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $staff->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$staff->isAdmin() ? 'amber' : 'blue'">
                            {{ $staff->isAdmin() ? __('Admin') : __('Staff') }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-center">
                        @if($staff->hasPermission('residents'))
                            <flux:icon name="check" class="size-5 text-emerald-600 dark:text-emerald-400 mx-auto" />
                        @else
                            <flux:icon name="x-mark" class="size-5 text-zinc-300 dark:text-zinc-600 mx-auto" />
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-center">
                        @if($staff->hasPermission('certificates'))
                            <flux:icon name="check" class="size-5 text-emerald-600 dark:text-emerald-400 mx-auto" />
                        @else
                            <flux:icon name="x-mark" class="size-5 text-zinc-300 dark:text-zinc-600 mx-auto" />
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-center">
                        @if($staff->hasPermission('appointments'))
                            <flux:icon name="check" class="size-5 text-emerald-600 dark:text-emerald-400 mx-auto" />
                        @else
                            <flux:icon name="x-mark" class="size-5 text-zinc-300 dark:text-zinc-600 mx-auto" />
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-center">
                        @if($staff->hasPermission('blotters'))
                            <flux:icon name="check" class="size-5 text-emerald-600 dark:text-emerald-400 mx-auto" />
                        @else
                            <flux:icon name="x-mark" class="size-5 text-zinc-300 dark:text-zinc-600 mx-auto" />
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil" href="{{ route('staff.edit', $staff) }}">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                @if($staff->id !== auth()->id())
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $staff->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center py-8">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="user-group" class="size-12 text-zinc-300" />
                            <flux:text class="text-zinc-500">{{ __('No staff members found') }}</flux:text>
                            @if ($search)
                                <flux:button variant="ghost" size="sm" wire:click="$set('search', '')">
                                    {{ __('Clear search') }}
                                </flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Staff Member') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Are you sure you want to delete this staff member? This will remove their account and access. This action cannot be undone.') }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteStaff">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
