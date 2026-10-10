<?php

use App\Mail\RegistrationRejected;
use App\Mail\ResidentApproved;
use App\Models\ActivityLog;
use App\Models\Resident;
use App\Notifications\ResidentNotification;
use Illuminate\Support\Facades\DB;
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
    public string $purok = '';

    #[Url]
    public string $gender = '';

    #[Url]
    public string $civilStatus = '';

    #[Url]
    public string $ageGroup = '';

    #[Url]
    public string $voter = '';

    #[Url]
    public string $sector = '';

    #[Url]
    public string $account = '';

    #[Url]
    public string $profile = '';

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

    /**
     * Any registry filter change starts again from the first page.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['purok', 'gender', 'civilStatus', 'ageGroup', 'voter', 'sector', 'account', 'profile'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'purok', 'gender', 'civilStatus', 'ageGroup', 'voter', 'sector', 'account', 'profile');
        $this->resetPage();
    }

    #[Computed]
    public function hasFilters(): bool
    {
        return (bool) ($this->search || $this->purok || $this->gender || $this->civilStatus || $this->ageGroup || $this->voter || $this->sector || $this->account || $this->profile);
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
            $resident = Resident::with('user')->find($this->residentToDelete);

            if ($resident) {
                $user = $resident->user;

                // Soft delete only: certificates, appointments and blotters cascade on a
                // hard delete and would take the resident's history with them. Removing
                // the account frees the email address; the nullOnDelete foreign key
                // clears user_id on the retained record.
                ActivityLog::record(
                    module: 'residents',
                    action: 'deleted',
                    subject: $resident,
                    description: 'Deleted resident '.$resident->full_name.'.',
                );

                $resident->delete();
                $user?->delete();
            }

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

        ActivityLog::record(
            module: 'residents',
            action: 'approved',
            subject: $resident,
            description: 'Approved the registration of '.$resident->full_name.'.',
        );

        if ($resident->user) {
            Mail::to($resident->user)->send(new ResidentApproved($resident->user, $resident));
        }

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

    /**
     * Reject a pending registration.
     *
     * The account and resident record are removed so the applicant can register
     * again with the same email address once the issue has been resolved.
     */
    public function rejectResident(): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'max:1000'],
        ]);

        if ($this->residentToReject) {
            $resident = Resident::with('user')->findOrFail($this->residentToReject);
            $user = $resident->user;
            $reason = $this->rejectionReason;

            // Send before deleting — afterwards there is no address to send to.
            if ($user) {
                Mail::to($user->email)->send(new RegistrationRejected($resident->full_name, $reason));
            }

            // Logged before the delete: the subject is about to disappear, so the
            // description carries the record.
            ActivityLog::record(
                module: 'residents',
                action: 'rejected',
                subject: $resident,
                description: 'Rejected the registration of '.$resident->full_name.' ('.($user?->email ?? 'no account').'). Reason: '.$reason,
                properties: ['reason' => $reason, 'email' => $user?->email],
            );

            DB::transaction(function () use ($resident, $user): void {
                $resident->forceDelete();
                $user?->delete();
            });

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
                    ->orWhere('house_number', 'like', "%{$search}%")
                    ->orWhere('street', 'like', "%{$search}%")
                    ->orWhere('purok', 'like', "%{$search}%")
                )
            )
            ->when($this->tab === 'all', fn ($query) => $query
                ->when($this->purok, fn ($q) => $q->where('purok', $this->purok))
                ->when($this->gender, fn ($q) => $q->where('gender', $this->gender))
                ->when($this->civilStatus, fn ($q) => $q->where('civil_status', $this->civilStatus))
                ->when($this->ageGroup, fn ($q) => $q->ageGroup($this->ageGroup))
                ->when($this->voter !== '', fn ($q) => $q->where('is_voter', $this->voter === 'yes'))
                ->when($this->sector, fn ($q) => $q->inSector($this->sector))
                ->when($this->account === 'with', fn ($q) => $q->whereNotNull('user_id'))
                ->when($this->account === 'without', fn ($q) => $q->whereNull('user_id'))
                ->when($this->profile === 'incomplete', fn ($q) => $q->incomplete())
            )
            ->orderBy(
                in_array($this->sortBy, ['last_name', 'birthdate', 'purok', 'created_at'], true) ? $this->sortBy : 'last_name',
                $this->sortDirection === 'desc' ? 'desc' : 'asc',
            )
            ->orderBy('first_name')
            ->paginate(15);
    }
}; ?>

<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Residents') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Registry of all barangay residents, with or without an online account') }}</flux:text>
        </div>

        <div class="flex gap-2">
            <flux:button icon="arrow-up-tray" href="{{ route('residents.import') }}">
                {{ __('Import') }}
            </flux:button>
            <flux:button variant="primary" icon="plus" href="{{ route('residents.create') }}">
                {{ __('Add Resident') }}
            </flux:button>
        </div>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4" :heading="session('status')" />
    @endif

    {{-- Tabs --}}
    <div class="mb-4 flex items-center gap-4 border-b border-zinc-200 dark:border-zinc-700">
        <button
            wire:click="$set('tab', 'all')"
            class="relative px-1 pb-3 text-sm font-medium transition-colors cursor-pointer {{ $tab === 'all' ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
        >
            {{ __('Registry') }}
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

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search residents...') }}"
            class="max-w-sm"
        />

        @if ($tab === 'all')
            <flux:select wire:model.live="purok" class="max-w-40">
                <option value="">{{ __('All Puroks') }}</option>
                @foreach (\App\Models\Resident::PUROKS as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="gender" class="max-w-36">
                <option value="">{{ __('All Genders') }}</option>
                <option value="male">{{ __('Male') }}</option>
                <option value="female">{{ __('Female') }}</option>
            </flux:select>

            <flux:select wire:model.live="civilStatus" class="max-w-40">
                <option value="">{{ __('Any Civil Status') }}</option>
                @foreach (\App\Models\Resident::CIVIL_STATUSES as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="ageGroup" class="max-w-40">
                <option value="">{{ __('All Ages') }}</option>
                <option value="minor">{{ __('0–17 (Minor)') }}</option>
                <option value="adult">{{ __('18–59 (Adult)') }}</option>
                <option value="senior">{{ __('60+ (Senior)') }}</option>
            </flux:select>

            <flux:select wire:model.live="voter" class="max-w-36">
                <option value="">{{ __('Voters & Non-voters') }}</option>
                <option value="yes">{{ __('Voters') }}</option>
                <option value="no">{{ __('Non-voters') }}</option>
            </flux:select>

            <flux:select wire:model.live="sector" class="max-w-52">
                <option value="">{{ __('All Sectors') }}</option>
                <option value="senior">{{ __('Senior Citizen') }}</option>
                @foreach (\App\Models\Resident::SECTORS as $column => $label)
                    <option value="{{ $column }}">{{ __($label) }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="account" class="max-w-44">
                <option value="">{{ __('With or without account') }}</option>
                <option value="with">{{ __('Has online account') }}</option>
                <option value="without">{{ __('No online account') }}</option>
            </flux:select>

            <flux:select wire:model.live="profile" class="max-w-44">
                <option value="">{{ __('Any profile') }}</option>
                <option value="incomplete">{{ __('Incomplete profile') }}</option>
            </flux:select>

            @if ($this->hasFilters)
                <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
            @endif
        @endif
    </div>

    @if ($tab === 'all')
        <flux:text class="mb-2 text-sm text-zinc-500">
            {{ trans_choice(':count resident found|:count residents found', $this->residents->total()) }}
        </flux:text>
    @endif

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
                        <p class="font-medium text-zinc-900 dark:text-white">{{ $resident->gender ? ucfirst($resident->gender) : '—' }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Birthdate') }}</span>
                        <p class="font-medium text-zinc-900 dark:text-white">
                            @if ($resident->birthdate)
                                {{ $resident->birthdate->format('M d, Y') }} ({{ $resident->age }} yrs)
                            @else
                                —
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Civil Status') }}</span>
                        <p class="font-medium text-zinc-900 dark:text-white">{{ $resident->civil_status_label }}</p>
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
            <flux:table.column>{{ __('Account') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->residents as $resident)
                <flux:table.row :key="$resident->id">
                    <flux:table.cell variant="strong">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="xs" name="{{ $resident->full_name }}" />
                            <div>
                                <div class="flex items-center gap-2">
                                    {{ $resident->full_name }}
                                    @if ($resident->isIncomplete())
                                        <flux:badge size="sm" color="amber">{{ __('Incomplete') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="text-xs text-zinc-500">
                                    {{ $resident->civil_status_label }}
                                    @foreach ($resident->sector_labels as $sectorLabel)
                                        · {{ $sectorLabel }}
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $resident->age !== null ? $resident->age.' '.__('yrs') : '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($resident->gender)
                            <flux:badge size="sm" :color="$resident->gender === 'male' ? 'blue' : 'pink'">
                                {{ ucfirst($resident->gender) }}
                            </flux:badge>
                        @else
                            —
                        @endif
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
                        @if ($resident->user_id)
                            <flux:badge size="sm" color="blue" icon="user-circle">{{ __('Online') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Walk-in') }}</flux:badge>
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
                    <flux:table.cell colspan="8" class="text-center py-8">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="users" class="size-12 text-zinc-300" />
                            <flux:text class="text-zinc-500">{{ __('No residents found') }}</flux:text>
                            @if ($this->hasFilters)
                                <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                                    {{ __('Clear filters') }}
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
                    {{ __('Please provide a reason for rejecting this registration. The reason is emailed to the applicant, and their account is removed so they can register again with the same email address.') }}
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
