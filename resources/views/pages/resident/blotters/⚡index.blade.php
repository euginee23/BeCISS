<?php

use App\Models\Blotter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('My Blotters')]
#[Layout('layouts::app')]
class extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function resident()
    {
        return auth()->user()->resident;
    }

    #[Computed]
    public function blotters()
    {
        $resident = $this->resident;

        if (! $resident) {
            return null;
        }

        return $resident->blotters()
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(10);
    }
};
?>

<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" class="text-zinc-900 dark:text-white">{{ __('My Blotters') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">{{ __('Track the status of your blotter reports.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('resident.blotters.create') }}">
            {{ __('File Blotter') }}
        </flux:button>
    </div>

    {{-- Status Filter --}}
    <div class="flex items-center gap-3 flex-wrap">
        <flux:select wire:model.live="status" class="w-40">
            <flux:select.option value="">{{ __('All Status') }}</flux:select.option>
            @foreach(\App\Models\Blotter::STATUSES as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        @if($status)
            <flux:button variant="ghost" size="sm" wire:click="$set('status', '')">{{ __('Clear filter') }}</flux:button>
        @endif
    </div>

    {{-- Blotter Cards --}}
    @if($this->blotters && $this->blotters->isNotEmpty())
        <div class="flex flex-col gap-4">
            @foreach($this->blotters as $blotter)
                <div wire:key="{{ $blotter->id }}" class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 transition-all hover:shadow-lg hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent dark:from-emerald-950/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative">
                        {{-- Top row: type + status --}}
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="size-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                                    <flux:icon name="shield-exclamation" class="size-5 text-red-600 dark:text-red-400" />
                                </div>
                                <div class="min-w-0">
                                    <span class="font-semibold text-zinc-900 dark:text-white block truncate">{{ $blotter->type_label }}</span>
                                    <span class="font-mono text-xs text-zinc-400">{{ $blotter->blotter_number }}</span>
                                </div>
                            </div>
                            <flux:badge :color="$blotter->status_color" size="sm" class="shrink-0">{{ $blotter->status_label }}</flux:badge>
                        </div>

                        {{-- Narrative preview --}}
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-3 line-clamp-2">{{ $blotter->narrative }}</p>

                        {{-- Details row --}}
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-4 flex-wrap text-sm text-zinc-600 dark:text-zinc-300">
                                <span class="flex items-center gap-1.5">
                                    <flux:icon name="calendar" class="size-4 text-zinc-400" />
                                    {{ $blotter->incident_datetime->format('M d, Y g:i A') }}
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <flux:icon name="banknotes" class="size-4 text-zinc-400" />
                                    ₱{{ number_format($blotter->fee, 2) }}
                                    @if($blotter->is_paid)
                                        <flux:badge color="green" size="sm">{{ __('Paid') }}</flux:badge>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            @if($this->blotters->hasPages())
                <div class="mt-2">
                    {{ $this->blotters->links() }}
                </div>
            @endif
        </div>
    @else
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <div class="flex flex-col items-center justify-center py-16 text-center gap-3">
                <div class="size-14 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                    <flux:icon name="shield-exclamation" class="size-7 text-zinc-400" />
                </div>
                <div>
                    <flux:heading>{{ __('No blotters found') }}</flux:heading>
                    <flux:text class="text-zinc-400 mt-1">
                        @if($status)
                            {{ __('No blotters with this status.') }} <button wire:click="$set('status', '')" class="text-emerald-600 hover:underline">{{ __('Clear filter') }}</button>
                        @else
                            {{ __('You haven\'t filed any blotter reports yet.') }}
                        @endif
                    </flux:text>
                </div>
            </div>
        </div>
    @endif
</div>
