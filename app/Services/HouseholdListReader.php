<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

/**
 * Reads a printed household list (PDF or Word) into import rows.
 *
 * The expected layout is the barangay's household table: a "Purok …"
 * heading before each purok, then rows of number, name and precinct, where
 * a numbered row starts a household and unnumbered rows below it are its
 * members. Any other columns, such as remarks, are ignored.
 */
class HouseholdListReader
{
    private const string PUROK_HEADING = '/^purok\b/i';

    private const string PRECINCT = '/^\d{2,4}\s*[-–]?\s*\p{L}$/u';

    /**
     * Table headings and letterhead lines that are never residents.
     */
    private const string SKIPPED_LINE = '/household\s+(leader|head)|precinct|remarks|\batoa\b|undecided|out\s+of\b|^town$|^no\.?$|republic of|province of|municipality of|^region\b|^barangay\b|office of/i';

    public function __construct(public ResidentNameParser $nameParser) {}

    /**
     * @return array<int, array{values: array<string, string>, warnings: list<string>}>
     *
     * @throws ValidationException when the document cannot be read
     */
    public function read(string $path, string $extension): array
    {
        $items = match ($extension) {
            'pdf' => $this->pdfItems($path),
            'docx' => $this->docxItems($path),
        };

        $entries = $this->assemble($items);

        if ($entries === []) {
            throw ValidationException::withMessages([
                'file' => __('No residents were found in the document. It needs a "Purok …" heading and rows of number, name and precinct. Scanned documents cannot be read.'),
            ]);
        }

        $names = $this->nameParser->parseAll(array_map(fn (array $entry): array => [
            'name' => $entry['name'],
            'household' => $entry['household_no'],
            'section' => $entry['purok'],
        ], $entries));

        $rows = [];

        foreach ($entries as $index => $entry) {
            $name = $names[$index];
            $warnings = $entry['warnings'];

            if ($name['guessed']) {
                $warnings[] = __('Name order guessed from ":name". Use Swap if the first and last names are reversed.', ['name' => $entry['name']]);
            }

            $rows[$index + 1] = [
                'values' => [
                    'household_no' => $entry['household_no'],
                    'purok' => $entry['purok'],
                    'last_name' => $name['last_name'],
                    'first_name' => $name['first_name'],
                    'middle_name' => (string) $name['middle_name'],
                    'suffix' => (string) $name['suffix'],
                    'gender' => '',
                    'birthdate' => '',
                    'civil_status' => '',
                    'house_number' => '',
                    'street' => '',
                    'precinct_number' => $entry['precinct_number'],
                    'is_voter' => '',
                ],
                'warnings' => $warnings,
            ];
        }

        return $rows;
    }

    /**
     * Turn the document's stream of headings, rows and gaps into residents.
     *
     * @param  list<array<string, string>>  $items
     * @return list<array{household_no: string, purok: string, name: string, precinct_number: string, warnings: list<string>}>
     */
    private function assemble(array $items): array
    {
        $entries = [];
        $purok = '';
        $household = null;
        $usedNumbers = [];
        $afterGap = false;

        foreach ($items as $item) {
            if ($item['type'] === 'purok') {
                $purok = $item['text'];
                $household = null;
                $afterGap = false;

                continue;
            }

            if ($item['type'] === 'gap') {
                $afterGap = $household !== null;

                continue;
            }

            if ($item['type'] === 'page') {
                $afterGap = false;

                continue;
            }

            $warnings = [];

            if ($item['number'] !== '') {
                $household = $item['number'];

                for ($copy = 2; isset($usedNumbers[$household]); $copy++) {
                    $household = $item['number'].chr(64 + $copy);
                }

                if ($household !== $item['number']) {
                    $warnings[] = __('Household :number is listed twice; this one was imported as :renamed.', ['number' => $item['number'], 'renamed' => $household]);
                }

                $usedNumbers[$household] = true;
            } elseif ($afterGap) {
                $warnings[] = __('Listed after the end of household :number without a number of its own; it was added to household :number.', ['number' => $household]);
            }

            $afterGap = false;

            $entries[] = [
                'household_no' => (string) $household,
                'purok' => $purok,
                'name' => $item['name'],
                'precinct_number' => $item['precinct'],
                'warnings' => $warnings,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array<string, string>>
     *
     * @throws ValidationException
     */
    private function pdfItems(string $path): array
    {
        try {
            $result = Process::timeout(60)->run(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
        } catch (Throwable) {
            $result = null;
        }

        if (! $result?->successful()) {
            throw ValidationException::withMessages([
                'file' => __('The PDF could not be read. Make sure it is not password-protected, or upload the Word version instead.'),
            ]);
        }

        $items = [];
        $started = false;

        foreach (preg_split('/\r\n|\n|\r/', $result->output()) ?: [] as $line) {
            if (str_contains($line, "\f")) {
                $items[] = ['type' => 'page'];
                $line = str_replace("\f", '', $line);
            }

            $line = trim((string) preg_replace('/[\p{Co}✓✔]/u', '', $line));

            if ($line === '') {
                $items[] = ['type' => 'gap'];

                continue;
            }

            if (preg_match(self::PUROK_HEADING, $line)) {
                $started = true;
                $items[] = ['type' => 'purok', 'text' => $line];

                continue;
            }

            if (preg_match(self::SKIPPED_LINE, $line)) {
                continue;
            }

            $row = $this->pdfRow($line);

            if ($row['number'] !== '') {
                $started = true;
            }

            if ($started) {
                $items[] = $row['name'] === '' ? ['type' => 'gap'] : $row;
            }
        }

        return $items;
    }

    /**
     * Split a laid-out text line into its number, name and precinct columns.
     *
     * @return array{type: string, number: string, name: string, precinct: string}
     */
    private function pdfRow(string $line): array
    {
        $number = '';

        if (preg_match('/^(\d{1,4})\.?(?:\s+|(?=\p{L})|$)(.*)$/u', $line, $match)) {
            [, $number, $line] = $match;
        }

        $columns = preg_split('/\s{3,}/u', trim($line), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $name = $columns[0] ?? '';
        $precinct = '';

        if (preg_match(self::PRECINCT, $name)) {
            [$name, $precinct] = ['', $name];
        } elseif (isset($columns[1]) && preg_match(self::PRECINCT, $columns[1])) {
            $precinct = $columns[1];
        } elseif (preg_match('/^(.*\S)\s+(\d{2,4}\s*[-–]?\s*\p{L})$/u', $name, $match)) {
            [, $name, $precinct] = $match;
        }

        return ['type' => 'row', 'number' => $number, 'name' => $name, 'precinct' => $precinct];
    }

    /**
     * @return list<array<string, string>>
     *
     * @throws ValidationException
     */
    private function docxItems(string $path): array
    {
        try {
            $document = IOFactory::load($path, 'Word2007');
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => __('The Word document could not be read. Save it as .docx and try again.'),
            ]);
        }

        $items = [];

        foreach ($document->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof Table) {
                    array_push($items, ...$this->tableItems($element));

                    continue;
                }

                $text = $this->clean($this->text($element));

                if (preg_match(self::PUROK_HEADING, $text)) {
                    $items[] = ['type' => 'purok', 'text' => $text];
                }
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, string>>
     */
    private function tableItems(Table $table): array
    {
        $items = [];
        $columns = ['number' => 0, 'name' => 1, 'precinct' => 2];

        foreach ($table->getRows() as $row) {
            $cells = array_map(fn ($cell): string => $this->clean($this->text($cell)), $row->getCells());
            $filled = array_values(array_filter($cells, fn (string $cell): bool => $cell !== ''));

            if (count($filled) === 1 && preg_match(self::PUROK_HEADING, $filled[0])) {
                $items[] = ['type' => 'purok', 'text' => $filled[0]];

                continue;
            }

            if ($this->isHeaderRow($cells, $columns)) {
                continue;
            }

            $name = $cells[$columns['name']] ?? '';

            if ($name === '' || preg_match(self::SKIPPED_LINE, $name)) {
                $items[] = ['type' => 'gap'];

                continue;
            }

            $precinct = $cells[$columns['precinct']] ?? '';

            $items[] = [
                'type' => 'row',
                'number' => preg_match('/^(\d{1,4})\.?$/', $cells[$columns['number']] ?? '', $match) ? $match[1] : '',
                'name' => $name,
                'precinct' => preg_match(self::PRECINCT, $precinct) ? $precinct : '',
            ];
        }

        return $items;
    }

    /**
     * Detect a heading row and learn the column positions from it.
     *
     * @param  list<string>  $cells
     * @param  array{number: int, name: int, precinct: int}  $columns
     */
    private function isHeaderRow(array $cells, array &$columns): bool
    {
        $isHeader = false;

        foreach ($cells as $position => $cell) {
            if (preg_match('/precinct/i', $cell)) {
                $columns['precinct'] = $position;
                $isHeader = true;
            } elseif (preg_match('/household|leader|member|^name$/i', $cell)) {
                $columns['name'] = $position;
                $isHeader = true;
            } elseif (preg_match('/^(no\.?|#|number)$/i', $cell)) {
                $columns['number'] = $position;
                $isHeader = true;
            }
        }

        return $isHeader;
    }

    /**
     * Collect the plain text of a Word element and everything inside it.
     */
    private function text(mixed $element): string
    {
        if ($element instanceof TextBreak) {
            return ' ';
        }

        if (method_exists($element, 'getText') && is_string($text = $element->getText())) {
            return $text;
        }

        if (method_exists($element, 'getElements')) {
            return implode('', array_map(fn ($child): string => $this->text($child), $element->getElements()));
        }

        return '';
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', Str::of($text)->replaceMatches('/[\p{Co}✓✔]/u', '')->toString()));
    }
}
