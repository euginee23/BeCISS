<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Resident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use SplFileObject;

/**
 * Bulk-loads residents from a household list: a CSV built from the template,
 * or a PDF / Word copy of the barangay's printed household table.
 *
 * Rows sharing a household number within a purok form one household: the
 * first importable row becomes the head and the rest are linked to it via
 * household_head_id. Rows matching an existing resident are skipped.
 */
class ResidentImporter
{
    /**
     * The template's columns, in order.
     *
     * @var list<string>
     */
    public const array COLUMNS = [
        'household_no',
        'purok',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'gender',
        'birthdate',
        'civil_status',
        'house_number',
        'street',
        'precinct_number',
        'is_voter',
    ];

    /**
     * Columns the file cannot do without.
     *
     * @var list<string>
     */
    public const array REQUIRED_COLUMNS = ['purok', 'last_name', 'first_name'];

    /**
     * File types the importer reads.
     *
     * @var list<string>
     */
    public const array EXTENSIONS = ['csv', 'txt', 'pdf', 'docx'];

    public function __construct(public HouseholdListReader $documentReader) {}

    /**
     * Parse and check a file without writing anything.
     *
     * @param  list<int>  $swappedRows  rows whose first and last names should be exchanged
     * @return array{
     *     rows: list<array{line: int, group: string, household: string, name: string, purok: ?string, precinct_number: ?string, status: string, messages: list<string>, warnings: list<string>, is_head: bool, existing_head_id: ?int, attributes: array<string, mixed>}>,
     *     summary: array{total: int, new: int, duplicates: int, invalid: int, warnings: int, households: int}
     * }
     *
     * @throws ValidationException when the file cannot be read
     */
    public function parse(string $path, string $extension = 'csv', array $swappedRows = [], bool $everyoneIsVoter = false): array
    {
        $existing = $this->existingResidentsByName();
        $seenInFile = [];
        $headsByGroup = [];
        $rows = [];

        foreach ($this->readRows($path, Str::lower($extension)) as $line => ['values' => $values, 'warnings' => $warnings]) {
            if (in_array($line, $swappedRows, true)) {
                [$values['first_name'], $values['last_name']] = [$values['last_name'], $values['first_name']];
            }

            $attributes = $this->normalize($values, $everyoneIsVoter);
            $messages = $this->validateRow($attributes);
            $group = $attributes['purok'].'|'.($values['household_no'] !== '' ? $values['household_no'] : 'line:'.$line);
            $status = $messages === [] ? 'new' : 'invalid';
            $existingMatch = null;

            if ($status === 'new') {
                $key = $this->nameKey($attributes);
                $existingMatch = collect($existing[$key] ?? [])->first(fn (array $candidate): bool => $this->isSamePerson($attributes, $candidate));
                $fileMatch = collect($seenInFile[$key] ?? [])->first(fn (array $candidate): bool => $this->isSamePerson($attributes, $candidate));

                if ($existingMatch) {
                    $status = 'duplicate';
                    $messages[] = __('Already registered as resident #:id.', ['id' => $existingMatch['id']]);
                } elseif ($fileMatch) {
                    $status = 'duplicate';
                    $messages[] = __('Same person as row :line.', ['line' => $fileMatch['line']]);
                } else {
                    $seenInFile[$key][] = [...$attributes, 'line' => $line];
                }
            }

            $isHead = false;
            $existingHeadId = null;

            if (! array_key_exists($group, $headsByGroup)) {
                if ($status === 'new') {
                    $isHead = true;
                    $headsByGroup[$group] = null;
                } elseif ($existingMatch) {
                    $headsByGroup[$group] = $existingMatch['household_head_id'] ?? $existingMatch['id'];
                }
            } else {
                $existingHeadId = $headsByGroup[$group];
            }

            $rows[] = [
                'line' => $line,
                'group' => $group,
                'household' => $values['household_no'],
                'name' => trim($attributes['last_name'].', '.implode(' ', array_filter([
                    $attributes['first_name'],
                    $attributes['middle_name'],
                    $attributes['suffix'],
                ])), ', '),
                'purok' => $attributes['purok'],
                'precinct_number' => $attributes['precinct_number'],
                'status' => $status,
                'messages' => $messages,
                'warnings' => $status === 'new' ? $warnings : [],
                'is_head' => $isHead,
                'existing_head_id' => $existingHeadId,
                'attributes' => $attributes,
            ];
        }

        $rowsByStatus = collect($rows)->countBy('status');

        return [
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'new' => $rowsByStatus->get('new', 0),
                'duplicates' => $rowsByStatus->get('duplicate', 0),
                'invalid' => $rowsByStatus->get('invalid', 0),
                'warnings' => collect($rows)->filter(fn (array $row): bool => $row['warnings'] !== [])->count(),
                'households' => collect($rows)->where('status', 'new')->pluck('group')->unique()->count(),
            ],
        ];
    }

    /**
     * Create every importable row as an approved resident.
     *
     * @param  list<int>  $swappedRows
     * @return array{created: int, skipped: int, households: int}
     */
    public function import(string $path, string $filename, array $swappedRows = [], bool $everyoneIsVoter = false): array
    {
        $result = $this->parse($path, pathinfo($filename, PATHINFO_EXTENSION), $swappedRows, $everyoneIsVoter);

        return DB::transaction(function () use ($result, $filename): array {
            $headIds = [];
            $created = 0;

            foreach ($result['rows'] as $row) {
                if ($row['status'] !== 'new') {
                    continue;
                }

                $resident = Resident::create([
                    ...$row['attributes'],
                    'household_head_id' => $row['is_head'] ? null : ($headIds[$row['group']] ?? $row['existing_head_id']),
                    'status' => 'approved',
                    'approved_at' => now(),
                ]);

                if ($row['is_head']) {
                    $headIds[$row['group']] = $resident->id;
                }

                $created++;
            }

            $summary = [
                'created' => $created,
                'skipped' => $result['summary']['total'] - $created,
                'households' => $result['summary']['households'],
            ];

            ActivityLog::record(
                module: 'residents',
                action: 'imported',
                description: 'Imported '.$created.' residents in '.$summary['households'].' households from '.$filename.'.',
                properties: [...$summary, 'file' => $filename],
            );

            return $summary;
        });
    }

    /**
     * The downloadable template: a header row and an example household.
     *
     * @return list<list<string>>
     */
    public function templateRows(): array
    {
        $purok = Resident::PUROKS[0];

        return [
            self::COLUMNS,
            ['1', $purok, 'Dela Cruz', 'Juan', 'P.', 'Jr.', 'male', '1980-05-14', 'married', '12', 'Mabini Street', '86B', 'yes'],
            ['1', $purok, 'Dela Cruz', 'Maria', 'S.', '', 'female', '1982-11-02', 'married', '12', 'Mabini Street', '86B', 'yes'],
            ['2', $purok, 'Santos', 'Pedro', '', '', '', '', '', '', '', '', 'no'],
        ];
    }

    /**
     * Read the file into rows of column values, keyed by line or row number.
     *
     * @return array<int, array{values: array<string, string>, warnings: list<string>}>
     *
     * @throws ValidationException
     */
    private function readRows(string $path, string $extension): array
    {
        return match ($extension) {
            'pdf', 'docx' => $this->documentReader->read($path, $extension),
            'csv', 'txt' => $this->readCsvRows($path),
            default => throw ValidationException::withMessages([
                'file' => __('Upload a CSV, PDF or Word (.docx) file.'),
            ]),
        };
    }

    /**
     * Read a CSV built from the template, indexed by file line.
     *
     * @return array<int, array{values: array<string, string>, warnings: list<string>}>
     *
     * @throws ValidationException
     */
    private function readCsvRows(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setCsvControl(',', '"', '');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $header = null;
        $rows = [];

        foreach ($file as $index => $cells) {
            if (! is_array($cells) || $cells === [null]) {
                continue;
            }

            $cells = array_map(fn (?string $cell): string => $this->cleanCell($cell), $cells);

            if ($header === null) {
                $header = array_map(fn (string $cell): string => Str::snake(Str::lower(ltrim($cell, "\u{FEFF}"))), $cells);
                $missing = array_diff(self::REQUIRED_COLUMNS, $header);

                if ($missing !== []) {
                    throw ValidationException::withMessages([
                        'file' => __('The file is missing the :columns column(s). Download the template to see the expected header.', ['columns' => implode(', ', $missing)]),
                    ]);
                }

                continue;
            }

            if (implode('', $cells) === '') {
                continue;
            }

            $values = [];

            foreach (self::COLUMNS as $column) {
                $position = array_search($column, $header, true);
                $values[$column] = $position === false ? '' : ($cells[$position] ?? '');
            }

            $rows[$index + 1] = ['values' => $values, 'warnings' => []];
        }

        if ($header === null) {
            throw ValidationException::withMessages(['file' => __('The file is empty.')]);
        }

        return $rows;
    }

    /**
     * Trim, collapse whitespace and repair spreadsheet-exported Windows-1252 text.
     */
    private function cleanCell(?string $cell): string
    {
        $cell = (string) $cell;

        if (! mb_check_encoding($cell, 'UTF-8')) {
            $cell = mb_convert_encoding($cell, 'UTF-8', 'Windows-1252');
        }

        return trim((string) preg_replace('/\s+/u', ' ', $cell));
    }

    /**
     * Turn raw cell values into resident attributes.
     *
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function normalize(array $values, bool $everyoneIsVoter): array
    {
        $gender = Str::lower($values['gender']);
        $voter = Str::lower($values['is_voter']);
        $precinct = $values['precinct_number'] !== '' ? Str::upper(str_replace([' ', '-', '–'], '', $values['precinct_number'])) : null;

        return [
            'first_name' => $values['first_name'],
            'middle_name' => $values['middle_name'] ?: null,
            'last_name' => $values['last_name'],
            'suffix' => $values['suffix'] ?: null,
            'gender' => match ($gender) {
                'm', 'male' => 'male',
                'f', 'female' => 'female',
                '' => null,
                default => $values['gender'],
            },
            'birthdate' => $values['birthdate'] ?: null,
            'civil_status' => Str::lower($values['civil_status']) ?: 'single',
            'house_number' => $values['house_number'] ?: null,
            'street' => $values['street'] ?: null,
            'purok' => $this->matchPurok($values['purok']) ?? ($values['purok'] ?: null),
            'precinct_number' => $precinct,
            'is_voter' => match (true) {
                $everyoneIsVoter, $precinct !== null, in_array($voter, ['yes', 'y', '1', 'true'], true) => true,
                in_array($voter, ['no', 'n', '0', 'false', ''], true) => false,
                default => $values['is_voter'],
            },
        ];
    }

    /**
     * Find the configured purok a cell refers to, with or without its "Purok" prefix.
     */
    private function matchPurok(string $value): ?string
    {
        $normalize = fn (string $purok): string => Str::lower((string) preg_replace('/^purok|\s+/i', '', $purok));
        $wanted = $normalize($value);

        if ($wanted === '') {
            return null;
        }

        return collect(Resident::PUROKS)->first(fn (string $purok): bool => $normalize($purok) === $wanted);
    }

    /**
     * Validate one normalized row, returning its error messages.
     *
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    private function validateRow(array &$attributes): array
    {
        $validator = Validator::make($attributes, [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'civil_status' => ['required', Rule::in(array_keys(Resident::CIVIL_STATUSES))],
            'house_number' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'purok' => ['required', Rule::in(Resident::PUROKS)],
            'precinct_number' => ['nullable', 'string', 'max:20'],
            'is_voter' => ['boolean'],
        ], [
            'purok.in' => __('Unknown purok ":value".', ['value' => $attributes['purok']]),
            'gender.in' => __('Gender must be male or female.'),
            'is_voter.boolean' => __('Voter must be yes or no.'),
        ]);

        if ($validator->fails()) {
            return $validator->errors()->all();
        }

        if ($attributes['birthdate'] !== null) {
            $attributes['birthdate'] = Carbon::parse($attributes['birthdate'])->toDateString();
        }

        return [];
    }

    /**
     * Existing residents grouped by name key, for duplicate detection.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function existingResidentsByName(): array
    {
        return Resident::query()
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'birthdate', 'household_head_id'])
            ->groupBy(fn (Resident $resident): string => $this->nameKey($resident->only(['first_name', 'last_name', 'suffix'])))
            ->map(fn (Collection $residents): array => $residents->map(fn (Resident $resident): array => [
                'id' => $resident->id,
                'middle_name' => $resident->middle_name,
                'birthdate' => $resident->birthdate?->toDateString(),
                'household_head_id' => $resident->household_head_id,
            ])->all())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function nameKey(array $attributes): string
    {
        return Str::lower(implode('|', [
            $attributes['first_name'],
            $attributes['last_name'],
            str_replace('.', '', (string) ($attributes['suffix'] ?? '')),
        ]));
    }

    /**
     * Same-named records are one person unless their birthdates or middle
     * initials, where both are known, disagree.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $candidate
     */
    private function isSamePerson(array $row, array $candidate): bool
    {
        if ($row['birthdate'] && $candidate['birthdate'] && $row['birthdate'] !== $candidate['birthdate']) {
            return false;
        }

        $initial = fn (?string $middleName): string => Str::lower(Str::substr(ltrim((string) $middleName, '. '), 0, 1));

        return ! ($row['middle_name'] && $candidate['middle_name'] && $initial($row['middle_name']) !== $initial($candidate['middle_name']));
    }
}
