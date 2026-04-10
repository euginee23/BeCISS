<?php

use App\Models\Blotter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Blotter Reports')]
#[Layout('layouts::app')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $sortBy = 'created_at';

    #[Url]
    public string $sortDirection = 'desc';

    public bool $showDeleteModal = false;

    public ?int $blotterToDelete = null;

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

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->blotterToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function deleteBlotter(): void
    {
        if ($this->blotterToDelete) {
            Blotter::find($this->blotterToDelete)?->delete();
            $this->showDeleteModal = false;
            $this->blotterToDelete = null;
        }
    }

    #[Computed]
    public function blotters()
    {
        return Blotter::query()
            ->with('resident')
            ->when($this->search, fn ($query, $search) => $query
                ->where('blotter_number', 'like', "%{$search}%")
                ->orWhere('narrative', 'like', "%{$search}%")
                ->orWhere('respondent_name', 'like', "%{$search}%")
                ->orWhereHas('resident', fn ($q) => $q
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                )
            )
            ->when($this->status, fn ($query, $status) => $query->where('status', $status))
            ->when($this->type, fn ($query, $type) => $query->where('incident_type', $type))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }
}; ?>

<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Blotter Reports') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Manage blotter reports from residents') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" href="{{ route('blotters.create') }}">
            {{ __('New Report') }}
        </flux:button>
    </div>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search blotters...') }}"
            class="max-w-xs"
        />

        <flux:select wire:model.live="status" class="max-w-xs">
            <option value="">{{ __('All Status') }}</option>
            @foreach (App\Models\Blotter::STATUSES as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="type" class="max-w-xs">
            <option value="">{{ __('All Types') }}</option>
            @foreach (App\Models\Blotter::TYPES as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->blotters">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'blotter_number'" :direction="$sortDirection" wire:click="sort('blotter_number')">
                {{ __('Blotter #') }}
            </flux:table.column>
            <flux:table.column>{{ __('Complainant') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'incident_type'" :direction="$sortDirection" wire:click="sort('incident_type')">
                {{ __('Incident Type') }}
            </flux:table.column>
            <flux:table.column>{{ __('Respondent') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">
                {{ __('Status') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                {{ __('Filed') }}
            </flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->blotters as $blotter)
                <flux:table.row :key="$blotter->id">
                    <flux:table.cell variant="strong" class="font-mono text-sm">
                        {{ $blotter->blotter_number }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="xs" name="{{ $blotter->resident->full_name }}" />
                            {{ $blotter->resident->full_name }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $blotter->type_label }}</flux:table.cell>
                    <flux:table.cell>{{ $blotter->respondent_name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$blotter->status_color">
                            {{ $blotter->status_label }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-sm text-zinc-500">
                        {{ $blotter->created_at->format('M j, Y') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="eye" href="{{ route('blotters.show', $blotter) }}">
                                    {{ __('View') }}
                                </flux:menu.item>
                                @if (in_array($blotter->status, ['pending', 'processing']))
                                    <flux:menu.item icon="pencil" href="{{ route('blotters.edit', $blotter) }}">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                @endif
                                <flux:menu.separator />
                                @if ($blotter->status === 'pending')
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $blotter->id }})">
                                        {{ __('Cancel') }}
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-8">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="shield-exclamation" class="size-12 text-zinc-300" />
                            <flux:text class="text-zinc-500">{{ __('No blotter reports found') }}</flux:text>
                            @if ($search || $status || $type)
                                <flux:button variant="ghost" size="sm" wire:click="$set('search', ''); $set('status', ''); $set('type', '');">
                                    {{ __('Clear filters') }}
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
                <flux:heading size="lg">{{ __('Cancel Report') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Are you sure you want to cancel this blotter report? This action cannot be undone.') }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                    {{ __('Keep') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteBlotter">
                    {{ __('Cancel Report') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
