<?php

use App\Mail\ResidentApproved;
use App\Models\Resident;
use App\Notifications\ResidentNotification;
use Illuminate\Support\Facades\Mail;
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
    public string $tab = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortBy = 'last_name';

    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDeleteModal = false;

    public ?int $residentToDelete = null;

    public bool $showRejectModal = false;

    public ?int $residentToReject = null;

    public string $rejectionReason = '';

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

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->search = '';
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

    public function approveResident(int $id): void
    {
        $resident = Resident::with('user')->findOrFail($id);
        $resident->update([
            'status' => 'approved',
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        Mail::to($resident->user)->send(new ResidentApproved($resident->user, $resident));

        $resident->user?->notify(new ResidentNotification(
            type: 'registration_approved',
            title: 'Registration Approved',
            body: 'Your registration has been approved. You now have full access to BeCISS.',
            url: route('dashboard'),
        ));
    }

    public function openRejectModal(int $id): void
    {
        $this->residentToReject = $id;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function rejectResident(): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'max:500'],
        ]);

        if ($this->residentToReject) {
            $resident = Resident::with('user')->findOrFail($this->residentToReject);
            $resident->update([
                'status' => 'rejected',
                'rejection_reason' => $this->rejectionReason,
            ]);

            $resident->user?->notify(new ResidentNotification(
                type: 'registration_rejected',
                title: 'Registration Not Approved',
                body: 'Your registration was not approved. Reason: ' . $this->rejectionReason,
                url: route('complete-profile'),
            ));

            $this->showRejectModal = false;
            $this->residentToReject = null;
            $this->rejectionReason = '';
        }
    }

    #[Computed]
    public function pendingCount(): int
    {
        return Resident::pending()->count();
    }

    #[Computed]
    public function residents()
    {
        return Resident::query()
            ->when($this->tab === 'pending', fn ($query) => $query->pending())
            ->when($this->tab === 'all', fn ($query) => $query->approved())
            ->when($this->search, fn ($query, $search) => $query
                ->where(fn ($q) => $q
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('purok', 'like', "%{$search}%")
                )
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

    {{-- Tabs --}}
    <div class="mb-4 flex items-center gap-4 border-b border-zinc-200 dark:border-zinc-700">
        <button
            wire:click="$set('tab', 'all')"
            class="relative px-1 pb-3 text-sm font-medium transition-colors cursor-pointer {{ $tab === 'all' ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
        >
            {{ __('All Residents') }}
            @if($tab === 'all')
                <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-emerald-600 dark:bg-emerald-400 rounded-full"></span>
            @endif
        </button>
        <button
            wire:click="$set('tab', 'pending')"
            class="relative flex items-center gap-2 px-1 pb-3 text-sm font-medium transition-colors cursor-pointer {{ $tab === 'pending' ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
        >
            {{ __('Pending Registrations') }}
            @if($this->pendingCount > 0)
                <flux:badge size="sm" color="amber">{{ $this->pendingCount }}</flux:badge>
            @endif
            @if($tab === 'pending')
                <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-emerald-600 dark:bg-emerald-400 rounded-full"></span>
            @endif
        </button>
    </div>

    <div class="mb-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search residents...') }}"
            class="max-w-sm"
        />
    </div>

    @if($tab === 'pending')
        {{-- ===== PENDING REGISTRATIONS VIEW ===== --}}
        @forelse ($this->residents as $resident)
            <div wire:key="pending-{{ $resident->id }}" class="mb-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <flux:avatar size="sm" name="{{ $resident->full_name }}" />
                        <div class="min-w-0">
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $resident->full_name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Submitted') }} {{ $resident->created_at->diffForHumans() }}
                                @if($resident->user)
                                    &middot; {{ $resident->user->email }}
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <flux:button variant="primary" size="sm" icon="check" wire:click="approveResident({{ $resident->id }})" wire:confirm="{{ __('Approve this resident registration?') }}">
                            {{ __('Approve') }}
                        </flux:button>
                        <flux:button variant="danger" size="sm" icon="x-mark" wire:click="openRejectModal({{ $resident->id }})">
                            {{ __('Reject') }}
                        </flux:button>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Gender') }}</span>
                        <p class="font-medium text-zinc-900 dark:text-white">{{ ucfirst($resident->gender) }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Birthdate') }}</span>
                        <p class="font-medium text-zinc-900 dark:text-white">{{ $resident->birthdate->format('M d, Y') }} ({{ $resident->age }} yrs)</p>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Civil Status') }}</span>
                        <p class="font-medium text-zinc-900 dark:text-white">{{ ucfirst($resident->civil_status) }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Contact') }}</span>
                        <p class="font-medium text-zinc-900 dark:text-white">{{ $resident->contact_number ?? '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Address') }}</span>
                        <p class="font-medium text-zinc-900 dark:text-white">
                            {{ $resident->address }}
                            @if($resident->purok)
                                ({{ $resident->purok }})
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-12 text-center">
                <flux:icon name="check-circle" class="size-12 text-emerald-300 mx-auto mb-3" />
                <flux:heading>{{ __('All caught up!') }}</flux:heading>
                <flux:text class="text-zinc-500 mt-1">{{ __('No pending registrations to review.') }}</flux:text>
            </div>
        @endforelse

        @if($this->residents->hasPages())
            <div class="mt-4">
                {{ $this->residents->links() }}
            </div>
        @endif
    @else
        {{-- ===== ALL RESIDENTS TABLE ===== --}}
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
    @endif

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

    {{-- Reject Modal --}}
    <flux:modal wire:model="showRejectModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reject Registration') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Please provide a reason for rejecting this registration. The resident will be able to see this reason and resubmit their information.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Rejection Reason') }}</flux:label>
                <flux:textarea wire:model="rejectionReason" rows="3" placeholder="{{ __('e.g., Incomplete address information...') }}" />
                <flux:error name="rejectionReason" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showRejectModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="rejectResident">
                    {{ __('Reject') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
