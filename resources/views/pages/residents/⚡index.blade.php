<?php

use App\Models\Resident;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Residents')]
#[Layout('layouts::app')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortBy = 'last_name';

    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDeleteModal = false;

    public ?int $residentToDelete = null;

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
        $this->residentToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function deleteResident(): void
    {
        if ($this->residentToDelete) {
            Resident::find($this->residentToDelete)?->delete();
            $this->showDeleteModal = false;
            $this->residentToDelete = null;
        }
    }

    #[Computed]
    public function residents()
    {
        return Resident::query()
            ->when($this->search, fn ($query, $search) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('middle_name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('purok', 'like', "%{$search}%")
            )
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }
}; ?>

<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Residents') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Manage barangay resident records') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" href="{{ route('residents.create') }}">
            {{ __('Add Resident') }}
        </flux:button>
    </div>

    <div class="mb-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search residents...') }}"
            class="max-w-sm"
        />
    </div>

    <flux:table :paginate="$this->residents">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'last_name'" :direction="$sortDirection" wire:click="sort('last_name')">
                {{ __('Name') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'birthdate'" :direction="$sortDirection" wire:click="sort('birthdate')">
                {{ __('Age') }}
            </flux:table.column>
            <flux:table.column>{{ __('Gender') }}</flux:table.column>
            <flux:table.column>{{ __('Address') }}</flux:table.column>
            <flux:table.column>{{ __('Contact') }}</flux:table.column>
            <flux:table.column>{{ __('Voter') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->residents as $resident)
                <flux:table.row :key="$resident->id">
                    <flux:table.cell variant="strong">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="xs" name="{{ $resident->full_name }}" />
                            <div>
                                <div>{{ $resident->full_name }}</div>
                                <div class="text-xs text-zinc-500">{{ ucfirst($resident->civil_status) }}</div>
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $resident->age }} {{ __('yrs') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$resident->gender === 'male' ? 'blue' : 'pink'">
                            {{ ucfirst($resident->gender) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="max-w-xs truncate">
                            {{ $resident->address }}
                            @if ($resident->purok)
                                <span class="text-zinc-500">({{ $resident->purok }})</span>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $resident->contact_number ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($resident->is_voter)
                            <flux:badge size="sm" color="emerald">{{ __('Yes') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="eye" href="{{ route('residents.show', $resident) }}">
                                    {{ __('View') }}
                                </flux:menu.item>
                                <flux:menu.item icon="pencil" href="{{ route('residents.edit', $resident) }}">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $resident->id }})">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-8">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="users" class="size-12 text-zinc-300" />
                            <flux:text class="text-zinc-500">{{ __('No residents found') }}</flux:text>
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
                <flux:heading size="lg">{{ __('Delete Resident') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Are you sure you want to delete this resident? This action cannot be undone.') }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteResident">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
