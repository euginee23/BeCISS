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

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">My Certificates</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">Track the status of your certificate requests.</flux:text>
        </div>
    </div>

    @php $resident = $this->resident; @endphp

    @if(! $resident)
        {{-- No linked resident record --}}
        <div class="rounded-xl border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-6">
            <div class="flex items-start gap-4">
                <div class="size-10 rounded-lg bg-amber-100 dark:bg-amber-800/50 flex items-center justify-center shrink-0">
                    <flux:icon name="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <flux:heading>Profile Not Linked</flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-300 mt-1">
                        Your account is not yet linked to a resident profile. Please visit the barangay hall or contact staff to have your account linked before you can request certificates.
                    </flux:text>
                </div>
            </div>
        </div>
    @else

        {{-- Filters --}}
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

        {{-- Certificates List --}}
        @if($this->certificates && $this->certificates->isNotEmpty())
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                <th class="px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Certificate #</th>
                                <th class="px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Type</th>
                                <th class="hidden md:table-cell px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Purpose</th>
                                <th class="px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Status</th>
                                <th class="hidden sm:table-cell px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Requested</th>
                                <th class="px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Fee</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($this->certificates as $cert)
                                <tr wire:key="{{ $cert->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="px-5 py-4 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $cert->certificate_number }}</td>
                                    <td class="px-5 py-4 font-medium text-zinc-900 dark:text-white">{{ $cert->type_label }}</td>
                                    <td class="hidden md:table-cell px-5 py-4 text-zinc-500 dark:text-zinc-400 max-w-xs truncate">{{ $cert->purpose }}</td>
                                    <td class="px-5 py-4">
                                        <flux:badge :color="match($cert->status) {
                                            'pending' => 'yellow',
                                            'processing' => 'blue',
                                            'ready_for_pickup' => 'lime',
                                            'completed' => 'green',
                                            'rejected' => 'red',
                                            'cancelled' => 'zinc',
                                            default => 'zinc'
                                        }" size="sm">{{ $cert->status_label }}</flux:badge>
                                    </td>
                                    <td class="hidden sm:table-cell px-5 py-4 text-zinc-500 dark:text-zinc-400 text-xs">{{ $cert->created_at->format('M d, Y') }}</td>
                                    <td class="px-5 py-4 text-zinc-700 dark:text-zinc-300">
                                        ₱{{ number_format($cert->fee, 2) }}
                                        @if($cert->is_paid)
                                            <flux:badge color="green" size="sm" class="ml-1">Paid</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($this->certificates->hasPages())
                    <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-800">
                        {{ $this->certificates->links() }}
                    </div>
                @endif
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
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
    @endif

</div>
