<?php

use App\Models\Appointment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Appointments')]
#[Layout('layouts::app')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $date = '';

    #[Url]
    public string $sortBy = 'appointment_date';

    #[Url]
    public string $sortDirection = 'asc';

    public bool $showCancelModal = false;

    public ?int $appointmentToCancel = null;

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

    public function confirmCancel(int $id): void
    {
        $this->appointmentToCancel = $id;
        $this->showCancelModal = true;
    }

    public function cancelAppointment(): void
    {
        if ($this->appointmentToCancel) {
            Appointment::find($this->appointmentToCancel)?->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $this->cancellationReason,
            ]);
            $this->showCancelModal = false;
            $this->appointmentToCancel = null;
            $this->cancellationReason = '';
        }
    }

    #[Computed]
    public function appointments()
    {
        return Appointment::query()
            ->with('resident')
            ->when($this->search, fn ($query, $search) => $query
                ->where('reference_number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('resident', fn ($q) => $q
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                )
            )
            ->when($this->status, fn ($query, $status) => $query->where('status', $status))
            ->when($this->date, fn ($query, $date) => $query->whereDate('appointment_date', $date))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function todayCount(): int
    {
        return Appointment::today()->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])->count();
    }

    #[Computed]
    public function upcomingCount(): int
    {
        return Appointment::upcoming()->count();
    }
}; ?>

<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Appointments') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Manage service appointments and schedules') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" href="{{ route('appointments.create') }}">
            {{ __('New Appointment') }}
        </flux:button>
    </div>

    {{-- Stats Cards --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-900/20">
            <div class="flex items-center gap-3">
                <flux:icon name="calendar" class="size-8 text-emerald-600" />
                <div>
                    <flux:text class="text-sm text-emerald-700 dark:text-emerald-300">{{ __('Today') }}</flux:text>
                    <flux:heading size="xl" class="text-emerald-900 dark:text-emerald-100">{{ $this->todayCount }}</flux:heading>
                </div>
            </div>
        </div>
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/20">
            <div class="flex items-center gap-3">
                <flux:icon name="clock" class="size-8 text-blue-600" />
                <div>
                    <flux:text class="text-sm text-blue-700 dark:text-blue-300">{{ __('Upcoming') }}</flux:text>
                    <flux:heading size="xl" class="text-blue-900 dark:text-blue-100">{{ $this->upcomingCount }}</flux:heading>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search appointments...') }}"
            class="max-w-xs"
        />

        <flux:select wire:model.live="status" class="max-w-xs">
            <option value="">{{ __('All Status') }}</option>
            @foreach (App\Models\Appointment::STATUSES as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live="date" type="date" class="max-w-xs" />
    </div>

    <flux:table :paginate="$this->appointments">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'reference_number'" :direction="$sortDirection" wire:click="sort('reference_number')">
                {{ __('Reference #') }}
            </flux:table.column>
            <flux:table.column>{{ __('Resident') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'service_type'" :direction="$sortDirection" wire:click="sort('service_type')">
                {{ __('Service') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'appointment_date'" :direction="$sortDirection" wire:click="sort('appointment_date')">
                {{ __('Schedule') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">
                {{ __('Status') }}
            </flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->appointments as $appointment)
                <flux:table.row :key="$appointment->id">
                    <flux:table.cell variant="strong" class="font-mono text-sm">
                        {{ $appointment->reference_number }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="xs" name="{{ $appointment->resident->full_name }}" />
                            {{ $appointment->resident->full_name }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $appointment->service_type_label }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        <div class="text-sm font-medium">{{ $appointment->appointment_date->format('M j, Y') }}</div>
                        <div class="text-xs text-zinc-500">{{ $appointment->appointment_time->format('g:i A') }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$appointment->status_color">
                            {{ $appointment->status_label }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="eye" href="{{ route('appointments.show', $appointment) }}">
                                    {{ __('View') }}
                                </flux:menu.item>
                                @if (in_array($appointment->status, ['scheduled', 'confirmed']))
                                    <flux:menu.item icon="pencil" href="{{ route('appointments.edit', $appointment) }}">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="x-mark" variant="danger" wire:click="confirmCancel({{ $appointment->id }})">
                                        {{ __('Cancel') }}
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-8">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="calendar" class="size-12 text-zinc-300" />
                            <flux:text class="text-zinc-500">{{ __('No appointments found') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Cancel Modal --}}
    <flux:modal wire:model="showCancelModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Cancel Appointment') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Please provide a reason for cancelling this appointment.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Reason') }}</flux:label>
                <flux:textarea wire:model="cancellationReason" rows="3" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">
                    {{ __('Keep') }}
                </flux:button>
                <flux:button variant="danger" wire:click="cancelAppointment">
                    {{ __('Cancel Appointment') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
