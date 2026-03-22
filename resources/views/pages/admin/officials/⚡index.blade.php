<?php

use App\Models\BarangayOfficial;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Barangay Officials')]
#[Layout('layouts::app')]
class extends Component
{
    // Form state
    public ?int $officialId = null;
    public string $name = '';
    public string $position = '';
    public string $committee = '';
    public string $termStart = '';
    public string $termEnd = '';
    public int $sortOrder = 0;
    public bool $isActive = true;

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $officialToDelete = null;

    #[Computed]
    public function officials()
    {
        return BarangayOfficial::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $official = BarangayOfficial::findOrFail($id);
        $this->officialId = $official->id;
        $this->name = $official->name;
        $this->position = $official->position;
        $this->committee = $official->committee ?? '';
        $this->termStart = $official->term_start?->format('Y-m-d') ?? '';
        $this->termEnd = $official->term_end?->format('Y-m-d') ?? '';
        $this->sortOrder = $official->sort_order;
        $this->isActive = $official->is_active;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'committee' => ['nullable', 'string', 'max:255'],
            'termStart' => ['nullable', 'date'],
            'termEnd' => ['nullable', 'date', 'after_or_equal:termStart'],
            'sortOrder' => ['integer', 'min:0'],
            'isActive' => ['boolean'],
        ]);

        BarangayOfficial::updateOrCreate(
            ['id' => $this->officialId],
            [
                'name' => $this->name,
                'position' => $this->position,
                'committee' => $this->committee ?: null,
                'term_start' => $this->termStart ?: null,
                'term_end' => $this->termEnd ?: null,
                'sort_order' => $this->sortOrder,
                'is_active' => $this->isActive,
            ]
        );

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->officials);
    }

    public function toggleActive(int $id): void
    {
        $official = BarangayOfficial::findOrFail($id);
        $official->update(['is_active' => ! $official->is_active]);
        unset($this->officials);
    }

    public function confirmDelete(int $id): void
    {
        $this->officialToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function deleteOfficial(): void
    {
        if ($this->officialToDelete) {
            BarangayOfficial::find($this->officialToDelete)?->delete();
            $this->showDeleteModal = false;
            $this->officialToDelete = null;
            unset($this->officials);
        }
    }

    private function resetForm(): void
    {
        $this->officialId = null;
        $this->name = '';
        $this->position = '';
        $this->committee = '';
        $this->termStart = '';
        $this->termEnd = '';
        $this->sortOrder = 0;
        $this->isActive = true;
        $this->resetValidation();
    }
};
?>

<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Barangay Officials</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">
                Manage elected and appointed barangay officials.
            </flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            Add Official
        </flux:button>
    </div>

    {{-- Officials Table --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">

        @if($this->officials->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center gap-3">
                <div class="size-14 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                    <flux:icon name="user-group" class="size-7 text-zinc-400" />
                </div>
                <div>
                    <flux:heading>No officials yet</flux:heading>
                    <flux:text class="text-zinc-400 mt-1">Add your first barangay official to get started.</flux:text>
                </div>
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">Add Official</flux:button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <th class="px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Name</th>
                            <th class="px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Position</th>
                            <th class="hidden md:table-cell px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Committee</th>
                            <th class="hidden lg:table-cell px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Term</th>
                            <th class="px-5 py-3 text-center font-medium text-zinc-500 dark:text-zinc-400">Status</th>
                            <th class="px-5 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->officials as $official)
                            <tr wire:key="{{ $official->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="size-9 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-700 dark:text-emerald-400 font-semibold text-sm shrink-0">
                                            {{ strtoupper(substr($official->name, 0, 1)) }}
                                        </div>
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ $official->name }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-zinc-700 dark:text-zinc-300">{{ $official->position }}</td>
                                <td class="hidden md:table-cell px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $official->committee ?? '—' }}</td>
                                <td class="hidden lg:table-cell px-5 py-4 text-zinc-500 dark:text-zinc-400 text-xs">
                                    @if($official->term_start)
                                        {{ $official->term_start->format('M Y') }}
                                        @if($official->term_end)
                                            – {{ $official->term_end->format('M Y') }}
                                        @else
                                            – present
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-center">
                                        <flux:badge
                                            :color="$official->is_active ? 'green' : 'zinc'"
                                            wire:click="toggleActive({{ $official->id }})"
                                            class="cursor-pointer"
                                        >{{ $official->is_active ? 'Active' : 'Inactive' }}</flux:badge>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditModal({{ $official->id }})">
                                            Edit
                                        </flux:button>
                                        <flux:button variant="ghost" size="sm" icon="trash" class="text-red-600 dark:text-red-400 hover:text-red-700" wire:click="confirmDelete({{ $official->id }})">
                                            Delete
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Create / Edit Modal --}}
    <flux:modal wire:model="showFormModal" class="w-full max-w-lg">
        <div class="flex flex-col gap-5">
            <flux:heading>{{ $officialId ? 'Edit Official' : 'Add New Official' }}</flux:heading>

            <form wire:submit="save" class="flex flex-col gap-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <flux:field class="sm:col-span-2">
                        <flux:label>Full Name <flux:badge size="sm" color="red" class="ml-1">Required</flux:badge></flux:label>
                        <flux:input wire:model="name" placeholder="Hon. Juan Dela Cruz" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Position <flux:badge size="sm" color="red" class="ml-1">Required</flux:badge></flux:label>
                        <flux:input wire:model="position" placeholder="e.g. Kagawad" />
                        <flux:error name="position" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Committee</flux:label>
                        <flux:input wire:model="committee" placeholder="e.g. Peace and Order" />
                        <flux:error name="committee" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Term Start</flux:label>
                        <flux:input type="date" wire:model="termStart" />
                        <flux:error name="termStart" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Term End</flux:label>
                        <flux:input type="date" wire:model="termEnd" />
                        <flux:error name="termEnd" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Sort Order</flux:label>
                        <flux:input type="number" wire:model="sortOrder" min="0" />
                        <flux:description>Lower numbers appear first</flux:description>
                        <flux:error name="sortOrder" />
                    </flux:field>

                    <flux:field class="flex items-center gap-3 mt-6">
                        <flux:checkbox wire:model="isActive" label="Active official" />
                    </flux:field>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <flux:button variant="ghost" wire:click="$set('showFormModal', false)" type="button">Cancel</flux:button>
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ $officialId ? 'Save Changes' : 'Add Official' }}</span>
                        <span wire:loading>Saving...</span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteModal" class="w-full max-w-sm">
        <div class="flex flex-col gap-4 text-center">
            <div class="size-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto">
                <flux:icon name="trash" class="size-6 text-red-600 dark:text-red-400" />
            </div>
            <div>
                <flux:heading>Delete Official</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">
                    Are you sure you want to delete this official? This action cannot be undone.
                </flux:text>
            </div>
            <div class="flex items-center justify-center gap-3">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="deleteOfficial" wire:loading.attr="disabled">
                    Delete
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>
