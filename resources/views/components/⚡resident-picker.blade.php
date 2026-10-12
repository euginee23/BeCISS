<?php

use App\Models\Resident;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;

new class extends Component
{
    /**
     * The chosen resident's id, bound to the parent form with wire:model.
     */
    #[Modelable]
    public ?int $value = null;

    public string $search = '';

    public function choose(int $id): void
    {
        $this->value = $id;
        $this->search = '';
    }

    public function clear(): void
    {
        $this->value = null;
    }

    #[Computed]
    public function selected(): ?Resident
    {
        return $this->value ? Resident::find($this->value) : null;
    }

    /**
     * @return Collection<int, Resident>
     */
    #[Computed]
    public function matches(): Collection
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return new Collection;
        }

        return Resident::query()
            ->search($this->search)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(10)
            ->get();
    }
}; ?>

<div class="relative">
    @if ($this->selected)
        <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-600">
            <div class="flex min-w-0 items-center gap-3">
                <flux:avatar size="xs" name="{{ $this->selected->full_name }}" />
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-zinc-800 dark:text-white">{{ $this->selected->full_name }}</div>
                    <div class="truncate text-xs text-zinc-500">{{ $this->selected->address }}</div>
                </div>
            </div>
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clear">{{ __('Change') }}</flux:button>
        </div>
    @else
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Type the resident\'s name...') }}"
            autocomplete="off"
            x-on:keydown.escape="$wire.set('search', '')"
        />

        @if (mb_strlen(trim($search)) >= 2)
            <div class="absolute inset-x-0 z-20 mt-1 max-h-72 overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-800">
                @forelse ($this->matches as $resident)
                    <button
                        type="button"
                        wire:key="resident-option-{{ $resident->id }}"
                        wire:click="choose({{ $resident->id }})"
                        class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-zinc-100 focus:bg-zinc-100 focus:outline-none dark:hover:bg-zinc-700 dark:focus:bg-zinc-700"
                    >
                        <flux:avatar size="xs" name="{{ $resident->full_name }}" />
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-zinc-800 dark:text-white">{{ $resident->full_name }}</div>
                            <div class="truncate text-xs text-zinc-500">{{ $resident->address }}</div>
                        </div>
                    </button>
                @empty
                    <div class="px-3 py-3 text-sm text-zinc-500">{{ __('No residents found') }}</div>
                @endforelse
            </div>
        @endif
    @endif
</div>
