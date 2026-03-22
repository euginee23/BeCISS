<?php

use App\Models\Certificate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('My Certificates')]
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
    public function certificates()
    {
        $resident = $this->resident;

        if (! $resident) {
            return null;
        }

        return $resident->certificates()
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(10);
    }
};
?>

<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div>
        <flux:heading size="xl" class="text-zinc-900 dark:text-white">My Certificates</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">Track the status of your certificate requests.</flux:text>
    </div>

    {{-- Status Filter --}}
    <div class="flex items-center gap-3 flex-wrap">
        <flux:select wire:model.live="status" class="w-40">
            <flux:select.option value="">All Status</flux:select.option>
            @foreach(\App\Models\Certificate::STATUSES as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        @if($status)
            <flux:button variant="ghost" size="sm" wire:click="$set('status', '')">Clear filter</flux:button>
        @endif
    </div>

    {{-- Certificates Cards --}}
    @if($this->certificates && $this->certificates->isNotEmpty())
        <div class="flex flex-col gap-4">
            @foreach($this->certificates as $cert)
                <div wire:key="{{ $cert->id }}" class="group relative overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 transition-all hover:shadow-lg hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent dark:from-emerald-950/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative">
                        {{-- Top row: type + status --}}
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="size-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                                    <flux:icon name="document-text" class="size-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <div class="min-w-0">
                                    <span class="font-semibold text-zinc-900 dark:text-white block truncate">{{ $cert->type_label }}</span>
                                    <span class="font-mono text-xs text-zinc-400">{{ $cert->certificate_number }}</span>
                                </div>
                            </div>
                            <flux:badge :color="match($cert->status) {
                                'pending' => 'yellow',
                                'processing' => 'blue',
                                'ready_for_pickup' => 'lime',
                                'completed' => 'green',
                                'rejected' => 'red',
                                'cancelled' => 'zinc',
                                default => 'zinc'
                            }" size="sm" class="shrink-0">{{ $cert->status_label }}</flux:badge>
                        </div>

                        {{-- Purpose --}}
                        @if($cert->purpose)
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-3 line-clamp-2">{{ $cert->purpose }}</p>
                        @endif

                        {{-- Details row --}}
                        <div class="flex items-center gap-4 flex-wrap text-sm text-zinc-600 dark:text-zinc-300">
                            <span class="flex items-center gap-1.5">
                                <flux:icon name="calendar" class="size-4 text-zinc-400" />
                                {{ $cert->created_at->format('M d, Y') }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <flux:icon name="banknotes" class="size-4 text-zinc-400" />
                                ₱{{ number_format($cert->fee, 2) }}
                                @if($cert->is_paid)
                                    <flux:badge color="green" size="sm">Paid</flux:badge>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach

            @if($this->certificates->hasPages())
                <div class="mt-2">
                    {{ $this->certificates->links() }}
                </div>
            @endif
        </div>
    @else
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <div class="flex flex-col items-center justify-center py-16 text-center gap-3">
                <div class="size-14 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                    <flux:icon name="document-text" class="size-7 text-zinc-400" />
                </div>
                <div>
                    <flux:heading>No certificates found</flux:heading>
                    <flux:text class="text-zinc-400 mt-1">
                        @if($status)
                            No certificates with this status. <button wire:click="$set('status', '')" class="text-emerald-600 hover:underline">Clear filter</button>
                        @else
                            You haven't requested any certificates yet. Visit the barangay hall to request one.
                        @endif
                    </flux:text>
                </div>
            </div>
        </div>
    @endif

</div>
