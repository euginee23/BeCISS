<?php

use App\Models\ServiceFee;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Service Fees')]
#[Layout('layouts::app')]
class extends Component
{
    // Edit modal
    public bool $showEditModal = false;

    public ?int $editingFeeId = null;

    public string $editingLabel = '';

    public string $editingFee = '';

    public bool $editingIsActive = true;

    public function mount(): void
    {
        ServiceFee::sync();
    }

    public function openEditModal(int $id): void
    {
        $fee = ServiceFee::findOrFail($id);
        $this->editingFeeId = $fee->id;
        $this->editingLabel = $fee->label;
        $this->editingFee = (string) $fee->fee;
        $this->editingIsActive = $fee->is_active;
        $this->showEditModal = true;
    }

    public function updateFee(): void
    {
        $this->validate([
            'editingFee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        ServiceFee::findOrFail($this->editingFeeId)->update([
            'fee' => $this->editingFee,
            'is_active' => $this->editingIsActive,
        ]);

        $this->showEditModal = false;
        $this->editingFeeId = null;
        unset($this->certificateFees, $this->blotterFees);
    }

    #[Computed]
    public function certificateFees()
    {
        $order = array_keys(ServiceFee::CERTIFICATE_SERVICES);

        return ServiceFee::whereIn('service_type', $order)
            ->get()
            ->sortBy(fn ($fee) => array_search($fee->service_type, $order))
            ->values();
    }

    #[Computed]
    public function blotterFees()
    {
        $order = array_keys(ServiceFee::BLOTTER_SERVICES);

        return ServiceFee::whereIn('service_type', $order)
            ->get()
            ->sortBy(fn ($fee) => array_search($fee->service_type, $order))
            ->values();
    }
}; ?>

<div class="flex flex-col gap-6 max-w-3xl">
    <div>
        <flux:heading size="xl">{{ __('Service Fees') }}</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('Set the processing fees charged for certificates and blotter reports.') }}
        </flux:text>
    </div>

    {{-- Certificates --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 flex flex-col gap-5">
        <div class="flex items-center gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
            <div class="size-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <flux:icon name="document-text" class="size-4 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <flux:heading>{{ __('Certificates') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Fees applied when a certificate of each type is requested.') }}</flux:text>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Certificate Type') }}</flux:table.column>
                <flux:table.column>{{ __('Fee') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->certificateFees as $fee)
                    <flux:table.row :key="$fee->id">
                        <flux:table.cell variant="strong">{{ $fee->label }}</flux:table.cell>
                        <flux:table.cell class="font-mono">₱{{ number_format($fee->fee, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$fee->is_active ? 'green' : 'zinc'">
                                {{ $fee->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditModal({{ $fee->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Blotter --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 flex flex-col gap-5">
        <div class="flex items-center gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
            <div class="size-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                <flux:icon name="shield-exclamation" class="size-4 text-red-600 dark:text-red-400" />
            </div>
            <div>
                <flux:heading>{{ __('Blotter') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">{{ __('Fee charged when a blotter report is filed.') }}</flux:text>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Service') }}</flux:table.column>
                <flux:table.column>{{ __('Fee') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->blotterFees as $fee)
                    <flux:table.row :key="$fee->id">
                        <flux:table.cell variant="strong">{{ $fee->label }}</flux:table.cell>
                        <flux:table.cell class="font-mono">₱{{ number_format($fee->fee, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$fee->is_active ? 'green' : 'zinc'">
                                {{ $fee->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditModal({{ $fee->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit Fee') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500">{{ $editingLabel }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Fee Amount (₱)') }} <span class="text-red-500">*</span></flux:label>
                <flux:input type="number" wire:model="editingFee" step="0.01" min="0" required />
                <flux:error name="editingFee" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Active') }}</flux:label>
                <flux:switch wire:model="editingIsActive" />
                <flux:text class="text-sm text-zinc-500 mt-1">{{ __('Inactive fees return ₱0 when looked up in the system.') }}</flux:text>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" wire:click="updateFee">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
