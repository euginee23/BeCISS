<?php

use App\Models\ActivityLog;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Services')]
#[Layout('layouts::app')]
class extends Component
{
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $requirements = '';

    public string $fee = '0';

    public string $sort_order = '0';

    public bool $is_active = true;

    public bool $is_bookable = true;

    public bool $show_on_website = true;

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'requirements' => ['nullable', 'string', 'max:2000'],
            'fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'is_bookable' => ['boolean'],
            'show_on_website' => ['boolean'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->sort_order = (string) ((int) Service::max('sort_order') + 1);
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetForm();

        $service = Service::findOrFail($id);

        $this->editingId = $service->id;
        $this->name = $service->name;
        $this->description = $service->description ?? '';
        $this->requirements = $service->requirements ?? '';
        $this->fee = (string) $service->fee;
        $this->sort_order = (string) $service->sort_order;
        $this->is_active = $service->is_active;
        $this->is_bookable = $service->is_bookable;
        $this->show_on_website = $service->show_on_website;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $service = $this->editingId
            ? Service::findOrFail($this->editingId)
            : new Service(['slug' => Service::slugFor($this->name)]);

        $isNew = ! $service->exists;

        $service->fill([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'requirements' => $this->requirements ?: null,
            'fee' => $this->fee,
            'sort_order' => (int) $this->sort_order,
            /**
             * System services back built-in flows (certificate visits,
             * blotter complaints), so they always stay bookable.
             */
            'is_active' => $service->is_system ? true : $this->is_active,
            'is_bookable' => $service->is_system ? true : $this->is_bookable,
            'show_on_website' => $this->show_on_website,
        ])->save();

        ActivityLog::record(
            module: 'services',
            action: $isNew ? 'created' : 'updated',
            subject: $service,
            description: ($isNew ? 'Created' : 'Updated').' service '.$service->name.'.',
        );

        $this->showFormModal = false;
        unset($this->services);
    }

    public function delete(int $id): void
    {
        $service = Service::findOrFail($id);

        if ($service->is_system) {
            $this->addError('delete', __(':name is used by the system and cannot be deleted.', ['name' => $service->name]));

            return;
        }

        $service->delete();

        ActivityLog::record(
            module: 'services',
            action: 'deleted',
            subject: $service,
            description: 'Deleted service '.$service->name.'.',
        );

        unset($this->services);
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->withCount('appointments')->ordered()->get();
    }

    #[Computed]
    public function editingSystemService(): bool
    {
        return $this->editingId !== null && (bool) Service::find($this->editingId)?->is_system;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->requirements = '';
        $this->fee = '0';
        $this->sort_order = '0';
        $this->is_active = true;
        $this->is_bookable = true;
        $this->show_on_website = true;
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Services') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">
                {{ __('Barangay services residents can book appointments for and see on the public website.') }}
            </flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('New Service') }}
        </flux:button>
    </div>

    <flux:error name="delete" />

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Service') }}</flux:table.column>
                <flux:table.column>{{ __('Fee') }}</flux:table.column>
                <flux:table.column>{{ __('Availability') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->services as $service)
                    <flux:table.row :key="$service->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white flex items-center gap-2">
                                {{ $service->name }}
                                @if ($service->is_system)
                                    <flux:icon name="lock-closed" class="size-3.5 text-zinc-400" />
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500 max-w-md truncate">{{ $service->description }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono">
                            {{ (float) $service->fee > 0 ? '₱'.number_format((float) $service->fee, 2) : __('Free') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @if ($service->is_bookable)
                                    <flux:badge size="sm" color="blue">{{ __('Bookable') }}</flux:badge>
                                @endif
                                @if ($service->show_on_website)
                                    <flux:badge size="sm" color="purple">{{ __('On website') }}</flux:badge>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500 mt-1">{{ trans_choice(':count appointment|:count appointments', $service->appointments_count) }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$service->is_active ? 'green' : 'zinc'">
                                {{ $service->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditModal({{ $service->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                                @unless ($service->is_system)
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="delete({{ $service->id }})" wire:confirm="{{ __('Delete this service? Past appointments keep their label.') }}" />
                                @endunless
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No services yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showFormModal" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit Service') : __('New Service') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="name" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fee (₱)') }}</flux:label>
                    <flux:input type="number" wire:model="fee" step="0.01" min="0" />
                    <flux:description>{{ __('Shown to residents for reference. Use 0 if free.') }}</flux:description>
                    <flux:error name="fee" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Display Order') }}</flux:label>
                    <flux:input type="number" wire:model="sort_order" min="0" />
                    <flux:error name="sort_order" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="description" rows="2" />
                    <flux:error name="description" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Requirements') }}</flux:label>
                    <flux:textarea wire:model="requirements" rows="3" placeholder="{{ __('One requirement per line') }}" />
                    <flux:error name="requirements" />
                </flux:field>

                @if ($this->editingSystemService)
                    <flux:text class="sm:col-span-2 text-sm text-zinc-500">{{ __('This service is used by the system and is always active and bookable.') }}</flux:text>
                @else
                    <flux:switch wire:model="is_active" label="{{ __('Active') }}" />
                    <flux:switch wire:model="is_bookable" label="{{ __('Can be booked as an appointment') }}" />
                @endif
                <flux:switch wire:model="show_on_website" label="{{ __('Show on the public website') }}" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
