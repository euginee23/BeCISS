<?php

use App\Models\Certificate;
use App\Services\CertificateWorkflow;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Certificate Requests')]
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

    public bool $showCancelModal = false;

    public ?int $certificateToCancel = null;

    public string $cancellationReason = '';

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

    public function confirmCancel(int $id): void
    {
        $this->resetValidation();
        $this->certificateToCancel = $id;
        $this->cancellationReason = '';
        $this->showCancelModal = true;
    }

    public function cancelCertificate(CertificateWorkflow $workflow): void
    {
        $this->validate([
            'cancellationReason' => ['nullable', 'string', 'max:500'],
        ]);

        $certificate = Certificate::findOrFail($this->certificateToCancel);

        $workflow->cancel($certificate, auth()->user(), $this->cancellationReason ?: null);

        $this->showCancelModal = false;
        $this->certificateToCancel = null;
    }

    #[Computed]
    public function certificates()
    {
        return Certificate::query()
            ->with('resident', 'certificateType')
            ->when($this->search, fn ($query, $search) => $query->where(function ($sub) use ($search) {
                $sub->where('certificate_number', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('purpose_other', 'like', "%{$search}%")
                    ->orWhereHas('resident', fn ($q) => $q
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                    );
            }))
            ->when($this->status, fn ($query, $status) => $query->where('status', $status))
            ->when($this->type, fn ($query, $type) => $query->where('type', $type))
            ->orderBy(
                in_array($this->sortBy, ['certificate_number', 'type', 'status', 'created_at'], true) ? $this->sortBy : 'created_at',
                $this->sortDirection === 'asc' ? 'asc' : 'desc',
            )
            ->paginate(10);
    }
}; ?>

<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Certificate Requests') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Manage certificate requests from residents') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" href="{{ route('certificates.create') }}">
            {{ __('New Request') }}
        </flux:button>
    </div>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search certificates...') }}"
            class="max-w-xs"
        />

        <flux:select wire:model.live="status" class="max-w-xs">
            <option value="">{{ __('All Status') }}</option>
            @foreach (App\Models\Certificate::STATUSES as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="type" class="max-w-xs">
            <option value="">{{ __('All Types') }}</option>
            @foreach (App\Models\CertificateType::labels() as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->certificates">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'certificate_number'" :direction="$sortDirection" wire:click="sort('certificate_number')">
                {{ __('Certificate #') }}
            </flux:table.column>
            <flux:table.column>{{ __('Requester') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection" wire:click="sort('type')">
                {{ __('Type') }}
            </flux:table.column>
            <flux:table.column>{{ __('Purpose') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">
                {{ __('Status') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">
                {{ __('Requested') }}
            </flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->certificates as $certificate)
                <flux:table.row :key="$certificate->id">
                    <flux:table.cell variant="strong" class="font-mono text-sm">
                        {{ $certificate->certificate_number }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="xs" name="{{ $certificate->requester_name }}" />
                            <div>{{ $certificate->requester_name }}</div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $certificate->type_label }}</flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate">{{ $certificate->purpose_label }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$certificate->status_color">
                            {{ $certificate->status_label }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-sm text-zinc-500">
                        {{ $certificate->created_at->format('M j, Y') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="eye" href="{{ route('certificates.show', $certificate) }}">
                                    {{ __('View') }}
                                </flux:menu.item>
                                @if ($certificate->isEditable())
                                    <flux:menu.item icon="pencil" href="{{ route('certificates.edit', $certificate) }}">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                @endif
                                <flux:menu.separator />
                                @if ($certificate->isCancellable())
                                    <flux:menu.item icon="x-circle" variant="danger" wire:click="confirmCancel({{ $certificate->id }})">
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
                            <flux:icon name="document-text" class="size-12 text-zinc-300" />
                            <flux:text class="text-zinc-500">{{ __('No certificate requests found') }}</flux:text>
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

    {{-- Cancel Confirmation Modal --}}
    <flux:modal wire:model="showCancelModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel Request') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('The request is kept on record as cancelled and the resident is notified.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Reason') }}</flux:label>
                <flux:textarea wire:model="cancellationReason" rows="3" />
                <flux:error name="cancellationReason" />
            </flux:field>

            <flux:error name="status" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">
                    {{ __('Keep') }}
                </flux:button>
                <flux:button variant="danger" wire:click="cancelCertificate">
                    {{ __('Cancel Request') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
