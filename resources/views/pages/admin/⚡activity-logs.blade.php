<?php

use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Activity Logs')]
#[Layout('layouts::app')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $module = '';

    #[Url]
    public string $staff = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedModule(): void
    {
        $this->resetPage();
    }

    public function updatedStaff(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'module', 'staff', 'from', 'to']);
        $this->resetPage();
    }

    #[Computed]
    public function logs()
    {
        return ActivityLog::query()
            ->with(['actor', 'subject'])
            ->when($this->search, fn ($query, $search) => $query->where('description', 'like', "%{$search}%"))
            ->when($this->module, fn ($query, $module) => $query->where('module', $module))
            ->when($this->staff, fn ($query, $staff) => $query->where('user_id', $staff))
            ->when($this->from, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($this->to, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            // Tie-break on id: several actions can land in the same second.
            ->latest()
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function staffMembers()
    {
        return User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->orderBy('name')
            ->get();
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Activity Logs') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('A record of every action staff have taken on residents, certificates, appointments and blotters.') }}</flux:text>
    </div>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search activity...') }}"
            class="max-w-xs"
        />

        <flux:select wire:model.live="module" class="max-w-xs">
            <option value="">{{ __('All Modules') }}</option>
            @foreach (ActivityLog::MODULES as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="staff" class="max-w-xs">
            <option value="">{{ __('All Staff') }}</option>
            @foreach ($this->staffMembers as $member)
                <option value="{{ $member->id }}">{{ $member->name }}</option>
            @endforeach
        </flux:select>

        <flux:field>
            <flux:label>{{ __('From') }}</flux:label>
            <flux:input type="date" wire:model.live="from" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('To') }}</flux:label>
            <flux:input type="date" wire:model.live="to" />
        </flux:field>

        <flux:button variant="ghost" wire:click="resetFilters">{{ __('Reset') }}</flux:button>
    </div>

    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>{{ __('When') }}</flux:table.column>
            <flux:table.column>{{ __('Staff') }}</flux:table.column>
            <flux:table.column>{{ __('Module') }}</flux:table.column>
            <flux:table.column>{{ __('Action') }}</flux:table.column>
            <flux:table.column>{{ __('Details') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->logs as $log)
                <flux:table.row :key="$log->id">
                    <flux:table.cell class="whitespace-nowrap text-sm text-zinc-500">
                        {{ $log->created_at->format('M j, Y g:i A') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="xs" name="{{ $log->actor?->name ?? __('Removed staff') }}" />
                            <div>{{ $log->actor?->name ?? __('Removed staff') }}</div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">{{ $log->module_label }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $log->action_label }}</flux:table.cell>
                    <flux:table.cell class="max-w-md text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $log->description }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500 py-8">
                        {{ __('No activity recorded for these filters.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
