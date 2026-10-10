<?php

use App\Services\ResidentImporter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

new
#[Title('Import Residents')]
#[Layout('layouts::app')]
class extends Component {
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public string $show = 'all';

    public bool $everyoneIsVoter = false;

    /** @var list<int> */
    public array $swappedRows = [];

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:'.implode(',', ResidentImporter::EXTENSIONS), 'max:10240'],
        ];
    }

    /**
     * Check the upload as soon as it arrives so the preview can render.
     */
    public function updatedFile(): void
    {
        $this->validate();
        $this->reset('show', 'swappedRows');

        try {
            $this->parseUpload();
        } catch (ValidationException $exception) {
            $this->file = null;

            throw $exception;
        }

        unset($this->preview);
    }

    /**
     * The parsed upload, or null before a file is chosen.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function preview(): ?array
    {
        if (! $this->file instanceof TemporaryUploadedFile || $this->getErrorBag()->has('file')) {
            return null;
        }

        return $this->parseUpload();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseUpload(): array
    {
        return app(ResidentImporter::class)->parse(
            $this->file->getRealPath(),
            $this->file->getClientOriginalExtension(),
            $this->swappedRows,
            $this->everyoneIsVoter,
        );
    }

    /**
     * Exchange the first and last names of a row whose name order was misread.
     */
    public function swapName(int $row): void
    {
        $this->swappedRows = in_array($row, $this->swappedRows, true)
            ? array_values(array_diff($this->swappedRows, [$row]))
            : [...$this->swappedRows, $row];
    }

    /**
     * Preview rows narrowed to the chosen view.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function visibleRows(): array
    {
        $rows = $this->preview['rows'] ?? [];

        if ($this->show === 'issues') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['status'] !== 'new' || $row['warnings'] !== []));
        }

        return $rows;
    }

    public function import(ResidentImporter $importer): void
    {
        abort_unless(auth()->user()->hasPermission('residents') && ! auth()->user()->isResident(), 403);

        $this->validate();

        $result = $importer->import($this->file->getRealPath(), $this->file->getClientOriginalName(), $this->swappedRows, $this->everyoneIsVoter);

        session()->flash('status', __('Imported :created residents in :households households. :skipped rows were skipped.', $result));

        $this->redirect(route('residents.index'), navigate: true);
    }

    public function clearFile(): void
    {
        $this->reset('file', 'show', 'swappedRows');
        $this->resetErrorBag();
    }

    public function downloadTemplate(ResidentImporter $importer): StreamedResponse
    {
        return response()->streamDownload(function () use ($importer): void {
            $handle = fopen('php://output', 'w');

            foreach ($importer->templateRows() as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'beciss-residents-import-template.csv', ['Content-Type' => 'text/csv']);
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('residents.index') }}">
            {{ __('Back to Residents') }}
        </flux:button>
    </div>

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Import Residents') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Add residents in bulk from a household list') }}</flux:text>
        </div>

        <flux:button icon="arrow-down-tray" wire:click="downloadTemplate">
            {{ __('Download Template') }}
        </flux:button>
    </div>

    <div class="mb-6 rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg" class="mb-1">{{ __('Upload File') }}</flux:heading>
        <flux:text class="mb-2 text-sm text-zinc-500">
            {{ __('PDF or Word (.docx): the household list table, with a "Purok …" heading before each purok and rows of number, name and precinct. A numbered row starts a household; the rows below it are its members. Other columns are ignored.') }}
        </flux:text>
        <flux:text class="mb-4 text-sm text-zinc-500">
            {{ __('CSV: use the template. Required columns are purok, last_name and first_name; rows sharing a household_no form one household. Birthdate and gender may be left blank and completed later.') }}
        </flux:text>

        <div class="flex flex-wrap items-start gap-3">
            <flux:field class="max-w-md flex-1">
                <flux:input type="file" wire:model="file" accept=".csv,.pdf,.docx" />
                <flux:error name="file" />
            </flux:field>

            @if ($file)
                <flux:button variant="ghost" icon="x-mark" wire:click="clearFile">{{ __('Clear') }}</flux:button>
            @endif
        </div>

        <div class="mt-4">
            <flux:checkbox wire:model.live="everyoneIsVoter" :label="__('Everyone in this list is a registered voter')" />
        </div>

        <div wire:loading wire:target="file" class="mt-2">
            <flux:text class="text-sm text-zinc-500">{{ __('Reading file...') }}</flux:text>
        </div>
    </div>

    @if ($this->preview)
        @php($summary = $this->preview['summary'])

        @if ($summary['warnings'] > 0)
            <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
                <flux:callout.text>
                    {{ trans_choice(':count row needs a quick check, usually a guessed name order. Use Swap on any row whose first and last names are reversed.|:count rows need a quick check, usually a guessed name order. Use Swap on any row whose first and last names are reversed.', $summary['warnings']) }}
                </flux:callout.text>
            </flux:callout>
        @endif

        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm text-zinc-500">{{ __('Ready to import') }}</flux:text>
                <flux:heading size="xl" class="text-emerald-600 dark:text-emerald-400">{{ $summary['new'] }}</flux:heading>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm text-zinc-500">{{ __('Households') }}</flux:text>
                <flux:heading size="xl">{{ $summary['households'] }}</flux:heading>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm text-zinc-500">{{ __('Duplicates (skipped)') }}</flux:text>
                <flux:heading size="xl" class="text-amber-600 dark:text-amber-400">{{ $summary['duplicates'] }}</flux:heading>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text class="text-sm text-zinc-500">{{ __('Errors (skipped)') }}</flux:text>
                <flux:heading size="xl" class="text-red-600 dark:text-red-400">{{ $summary['invalid'] }}</flux:heading>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <flux:select wire:model.live="show" class="max-w-48">
                <option value="all">{{ __('All rows') }} ({{ $summary['total'] }})</option>
                <option value="issues">{{ __('Needs attention') }} ({{ $summary['duplicates'] + $summary['invalid'] + $summary['warnings'] }})</option>
            </flux:select>

            <flux:button
                variant="primary"
                icon="arrow-up-tray"
                wire:click="import"
                wire:confirm="{{ __('Import :count residents?', ['count' => $summary['new']]) }}"
                :disabled="$summary['new'] === 0"
            >
                {{ trans_choice('Import :count resident|Import :count residents', $summary['new']) }}
            </flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>#</flux:table.column>
                <flux:table.column>{{ __('Household') }}</flux:table.column>
                <flux:table.column>{{ __('Last name, First name') }}</flux:table.column>
                <flux:table.column>{{ __('Purok') }}</flux:table.column>
                <flux:table.column>{{ __('Precinct') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->visibleRows as $row)
                    <flux:table.row :key="'line-'.$row['line']">
                        <flux:table.cell class="text-zinc-500">{{ $row['line'] }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $row['household'] ?: '—' }}
                            @if ($row['is_head'])
                                <flux:badge size="sm" color="blue" class="ml-1">{{ __('Head') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-2">
                                {{ $row['name'] ?: '—' }}
                                @if ($row['status'] !== 'invalid' || in_array($row['line'], $swappedRows, true))
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="arrows-right-left"
                                        wire:click="swapName({{ $row['line'] }})"
                                        :tooltip="__('Swap first and last name')"
                                    >{{ __('Swap') }}</flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $row['purok'] ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $row['precinct_number'] ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($row['status'] === 'new' && $row['warnings'] !== [])
                                <flux:badge size="sm" color="orange">{{ __('Check') }}</flux:badge>
                            @elseif ($row['status'] === 'new')
                                <flux:badge size="sm" color="emerald">{{ __('New') }}</flux:badge>
                            @elseif ($row['status'] === 'duplicate')
                                <flux:badge size="sm" color="amber">{{ __('Duplicate') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="red">{{ __('Error') }}</flux:badge>
                            @endif

                            @foreach ([...$row['messages'], ...$row['warnings']] as $message)
                                <div class="mt-1 max-w-sm text-xs whitespace-normal text-zinc-500">{{ $message }}</div>
                            @endforeach
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">
                            {{ __('No rows to show.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif
</div>
