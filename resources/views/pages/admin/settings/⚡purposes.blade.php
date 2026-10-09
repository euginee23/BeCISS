<?php

use App\Models\ActivityLog;
use App\Models\CertificatePurpose;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Certificate Purposes')]
#[Layout('layouts::app')]
class extends Component
{
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public bool $is_active = true;

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::notIn([CertificatePurpose::OTHER]),
                Rule::unique('certificate_purposes', 'name')->ignore($this->editingId),
            ],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.not_in' => __('"Other" is always offered at the end of the list.'),
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->is_active = true;
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();

        $purpose = CertificatePurpose::findOrFail($id);

        $this->editingId = $purpose->id;
        $this->name = $purpose->name;
        $this->is_active = $purpose->is_active;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $purpose = $this->editingId
            ? CertificatePurpose::findOrFail($this->editingId)
            : new CertificatePurpose(['sort_order' => (int) CertificatePurpose::max('sort_order') + 1]);

        $isNew = ! $purpose->exists;

        $purpose->fill([
            'name' => $this->name,
            'is_active' => $this->is_active,
        ])->save();

        ActivityLog::record(
            module: 'purposes',
            action: $isNew ? 'created' : 'updated',
            subject: $purpose,
            description: ($isNew ? 'Added' : 'Updated').' certificate purpose "'.$purpose->name.'".',
        );

        $this->showFormModal = false;
        unset($this->purposes);
    }

    public function toggleActive(int $id): void
    {
        $purpose = CertificatePurpose::findOrFail($id);
        $purpose->update(['is_active' => ! $purpose->is_active]);

        ActivityLog::record(
            module: 'purposes',
            action: 'updated',
            subject: $purpose,
            description: ($purpose->is_active ? 'Activated' : 'Deactivated').' certificate purpose "'.$purpose->name.'".',
        );

        unset($this->purposes);
    }

    public function move(int $id, string $direction): void
    {
        $ordered = CertificatePurpose::query()->ordered()->get()->values();
        $index = $ordered->search(fn (CertificatePurpose $purpose) => $purpose->id === $id);
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! $ordered->has($swapWith)) {
            return;
        }

        $items = $ordered->all();
        [$items[$index], $items[$swapWith]] = [$items[$swapWith], $items[$index]];

        foreach (array_values($items) as $position => $purpose) {
            $purpose->update(['sort_order' => $position + 1]);
        }

        unset($this->purposes);
    }

    public function delete(int $id): void
    {
        $purpose = CertificatePurpose::findOrFail($id);
        $purpose->delete();

        ActivityLog::record(
            module: 'purposes',
            action: 'deleted',
            subject: $purpose,
            description: 'Deleted certificate purpose "'.$purpose->name.'".',
        );

        unset($this->purposes);
    }

    /**
     * @return Collection<int, CertificatePurpose>
     */
    #[Computed]
    public function purposes(): Collection
    {
        return CertificatePurpose::query()->ordered()->get();
    }
}; ?>

<div class="flex flex-col gap-6 max-w-3xl">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Certificate Purposes') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">
                {{ __('The purposes residents and staff choose from when requesting a certificate. "Other" is always available.') }}
            </flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Add Purpose') }}
        </flux:button>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
        @forelse ($this->purposes as $purpose)
            <div wire:key="purpose-{{ $purpose->id }}" class="flex items-center gap-3 px-4 py-3">
                <div class="flex flex-col">
                    <flux:button variant="ghost" size="xs" icon="chevron-up" wire:click="move({{ $purpose->id }}, 'up')" :disabled="$loop->first" />
                    <flux:button variant="ghost" size="xs" icon="chevron-down" wire:click="move({{ $purpose->id }}, 'down')" :disabled="$loop->last" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium truncate {{ $purpose->is_active ? 'text-zinc-900 dark:text-white' : 'text-zinc-400 line-through' }}">{{ $purpose->name }}</div>
                </div>
                <flux:switch :checked="$purpose->is_active" wire:click="toggleActive({{ $purpose->id }})" />
                <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditModal({{ $purpose->id }})" />
                <flux:button variant="ghost" size="sm" icon="trash" wire:click="delete({{ $purpose->id }})" wire:confirm="{{ __('Delete this purpose? Existing certificates keep their purpose text.') }}" />
            </div>
        @empty
            <div class="px-4 py-8 text-center text-zinc-500">{{ __('No purposes yet.') }}</div>
        @endforelse
        <div class="flex items-center gap-3 px-4 py-3 text-zinc-500">
            <flux:icon name="lock-closed" class="size-4" />
            <span>{{ __('Other (resident specifies)') }}</span>
        </div>
    </div>

    <flux:modal wire:model="showFormModal" class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit Purpose') : __('Add Purpose') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Purpose') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="name" required />
                <flux:error name="name" />
            </flux:field>

            <flux:switch wire:model="is_active" label="{{ __('Active') }}" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
