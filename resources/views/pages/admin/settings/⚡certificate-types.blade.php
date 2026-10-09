<?php

use App\Models\ActivityLog;
use App\Models\CertificateType;
use App\Services\CertificateDocumentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new
#[Title('Certificate Types')]
#[Layout('layouts::app')]
class extends Component
{
    use WithFileUploads;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $name = '';

    public string $description = '';

    public string $requirements = '';

    public string $fee = '0';

    public string $sort_order = '0';

    public bool $is_active = true;

    public bool $available_to_residents = true;

    public bool $requires_ctc = false;

    /** @var TemporaryUploadedFile|null */
    public $template = null;

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
            'available_to_residents' => ['boolean'],
            'requires_ctc' => ['boolean'],
            'template' => ['nullable', 'file', 'mimes:docx', 'max:5120'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->sort_order = (string) ((int) CertificateType::max('sort_order') + 1);
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetForm();

        $type = CertificateType::findOrFail($id);

        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->description = $type->description ?? '';
        $this->requirements = $type->requirements ?? '';
        $this->fee = (string) $type->fee;
        $this->sort_order = (string) $type->sort_order;
        $this->is_active = $type->is_active;
        $this->available_to_residents = $type->available_to_residents;
        $this->requires_ctc = $type->requires_ctc;
        $this->showFormModal = true;
    }

    public function save(CertificateDocumentService $documents): void
    {
        $this->validate();

        $type = $this->editingId
            ? CertificateType::findOrFail($this->editingId)
            : new CertificateType(['slug' => CertificateType::slugFor($this->name)]);

        $type->fill([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'requirements' => $this->requirements ?: null,
            'fee' => $this->fee,
            'sort_order' => (int) $this->sort_order,
            'is_active' => $this->is_active,
            'available_to_residents' => $this->available_to_residents,
            'requires_ctc' => $this->requires_ctc,
        ]);

        if ($this->template) {
            $this->storeTemplate($type, $documents);
        }

        $isNew = ! $type->exists;
        $type->save();

        ActivityLog::record(
            module: 'certificate_types',
            action: $isNew ? 'created' : 'updated',
            subject: $type,
            description: ($isNew ? 'Created' : 'Updated').' certificate type '.$type->name.'.',
            properties: ['fee' => (float) $type->fee],
        );

        $this->showFormModal = false;
        $this->resetForm();
        unset($this->types);
    }

    public function removeTemplate(int $id): void
    {
        $type = CertificateType::findOrFail($id);

        if ($type->template_path) {
            Storage::disk($type->template_disk ?: 'local')->delete($type->template_path);
        }

        $type->update([
            'template_path' => null,
            'template_original_name' => null,
            'template_placeholders' => null,
            'template_uploaded_at' => null,
        ]);

        ActivityLog::record(
            module: 'certificate_types',
            action: 'updated',
            subject: $type,
            description: 'Removed the uploaded template for '.$type->name.'.',
        );

        unset($this->types);
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteType(): void
    {
        $type = CertificateType::findOrFail($this->deletingId);

        if ($type->certificates()->exists()) {
            $this->addError('delete', __('This type has certificates on record. Deactivate it instead.'));

            return;
        }

        $type->delete();

        ActivityLog::record(
            module: 'certificate_types',
            action: 'deleted',
            subject: $type,
            description: 'Deleted certificate type '.$type->name.'.',
        );

        $this->showDeleteModal = false;
        $this->deletingId = null;
        unset($this->types);
    }

    /**
     * @return Collection<int, CertificateType>
     */
    #[Computed]
    public function types(): Collection
    {
        return CertificateType::query()
            ->withCount('certificates')
            ->ordered()
            ->get();
    }

    /**
     * Placeholders admins can put in a template.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function placeholders(): array
    {
        return CertificateDocumentService::PLACEHOLDERS;
    }

    private function storeTemplate(CertificateType $type, CertificateDocumentService $documents): void
    {
        $path = $this->template->storeAs(
            'certificate-templates',
            $type->slug.'-'.Str::uuid().'.docx',
            'local',
        );

        if ($type->template_path) {
            Storage::disk($type->template_disk ?: 'local')->delete($type->template_path);
        }

        $type->fill([
            'template_disk' => 'local',
            'template_path' => $path,
            'template_original_name' => $this->template->getClientOriginalName(),
            'template_placeholders' => $documents->placeholdersIn(Storage::disk('local')->path($path)),
            'template_uploaded_at' => now(),
        ]);
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
        $this->available_to_residents = true;
        $this->requires_ctc = false;
        $this->template = null;
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Certificate Types') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-1">
                {{ __('Manage the certificates residents can request, their fees, requirements and print templates.') }}
            </flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('New Type') }}
        </flux:button>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Fee') }}</flux:table.column>
                <flux:table.column>{{ __('Template') }}</flux:table.column>
                <flux:table.column>{{ __('Options') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->types as $type)
                    <flux:table.row :key="$type->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $type->name }}</div>
                            <div class="text-xs text-zinc-500">{{ trans_choice(':count certificate|:count certificates', $type->certificates_count) }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono">
                            {{ $type->isFree() ? __('Free') : '₱'.number_format((float) $type->fee, 2) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($type->hasTemplate())
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" color="green" icon="document-check">{{ __('Uploaded') }}</flux:badge>
                                    <flux:button variant="ghost" size="xs" icon="x-mark" wire:click="removeTemplate({{ $type->id }})" wire:confirm="{{ __('Remove the uploaded template?') }}" />
                                </div>
                                <div class="text-xs text-zinc-500 mt-1 truncate max-w-48">{{ $type->template_original_name }}</div>
                            @elseif ($type->template_original_name)
                                <flux:badge size="sm" color="zinc">{{ __('Built-in') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('None') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @if ($type->available_to_residents)
                                    <flux:badge size="sm" color="blue">{{ __('Online request') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Staff only') }}</flux:badge>
                                @endif
                                @if ($type->requires_ctc)
                                    <flux:badge size="sm" color="purple">{{ __('CTC') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$type->is_active ? 'green' : 'zinc'">
                                {{ $type->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-1">
                                <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditModal({{ $type->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDelete({{ $type->id }})" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                            {{ __('No certificate types yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Form Modal --}}
    <flux:modal wire:model="showFormModal" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Certificate Type') : __('New Certificate Type') }}</flux:heading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="name" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fee (₱)') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input type="number" wire:model="fee" step="0.01" min="0" required />
                    <flux:description>{{ __('Use 0 for free certificates. Free types skip payment.') }}</flux:description>
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
                    <flux:textarea wire:model="requirements" rows="3" placeholder="{{ __('One requirement per line, e.g. Valid ID') }}" />
                    <flux:error name="requirements" />
                </flux:field>

                <flux:switch wire:model="is_active" label="{{ __('Active') }}" />
                <flux:switch wire:model="available_to_residents" label="{{ __('Residents can request online') }}" />
                <flux:switch wire:model="requires_ctc" label="{{ __('Requires CTC details on issuance') }}" />
            </div>

            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-3">
                <flux:field>
                    <flux:label>{{ __('Word Template (.docx)') }}</flux:label>
                    <flux:input type="file" wire:model="template" accept=".docx" />
                    <flux:description>{{ __('Uploading replaces the current template. Certificates are always issued as PDF.') }}</flux:description>
                    <flux:error name="template" />
                </flux:field>

                <details class="text-sm">
                    <summary class="cursor-pointer text-zinc-600 dark:text-zinc-300">{{ __('Available placeholders') }}</summary>
                    <div class="mt-2 grid gap-1 sm:grid-cols-2">
                        @foreach ($this->placeholders as $placeholder => $help)
                            <div wire:key="placeholder-{{ $placeholder }}">
                                <code class="text-xs text-blue-600 dark:text-blue-400">${{ '{' }}{{ $placeholder }}{{ '}' }}</code>
                                <span class="text-xs text-zinc-500">{{ $help }}</span>
                            </div>
                        @endforeach
                    </div>
                </details>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showFormModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Certificate Type') }}</flux:heading>
                <flux:text class="mt-2 text-zinc-500">{{ __('Types already used by certificates cannot be deleted. Deactivate them instead.') }}</flux:text>
            </div>
            <flux:error name="delete" />
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteType">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
