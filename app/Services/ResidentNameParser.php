<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Splits free-text names from household lists into name parts.
 *
 * Lists mix "Juan P. Dela Cruz" with "Dela Cruz Juan". A middle initial
 * settles the order; otherwise surnames shared within the household,
 * surnames and first names seen elsewhere in the document, and finally the
 * purok's usual order decide. Names settled only by that last fallback are
 * flagged as guessed.
 */
class ResidentNameParser
{
    /**
     * Words that belong to the surname that follows them.
     *
     * @var list<string>
     */
    public const array PARTICLES = ['de', 'del', 'dela', 'delos', 'san', 'santa', 'sta.'];

    /**
     * @var array<string, string>
     */
    private const array SUFFIXES = ['jr' => 'Jr.', 'sr' => 'Sr.', 'ii' => 'II', 'iii' => 'III', 'iv' => 'IV'];

    /**
     * Parse every name of a document at once, so each name can lean on the rest.
     *
     * @param  list<array{name: string, household: string, section: string}>  $entries
     * @return list<array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string, guessed: bool}>
     */
    public function parseAll(array $entries): array
    {
        $names = array_map(fn (array $entry): array => $this->analyse($entry['name']), $entries);

        $knownSurnames = [];
        $knownFirstNames = [];
        $householdCounts = [];

        foreach ($names as $index => $name) {
            $household = $entries[$index]['section'].'|'.$entries[$index]['household'];

            if ($name['token_count'] < 2) {
                continue;
            }

            if ($name['certain']) {
                $knownSurnames[$name['surname_first']['key']] = true;
                $knownFirstNames[$name['first_last']['first_key']] = true;
                $householdCounts[$household][$name['surname_first']['key']] = ($householdCounts[$household][$name['surname_first']['key']] ?? 0) + 1;

                continue;
            }

            foreach (array_unique([$name['surname_first']['key'], $name['first_last']['key']]) as $key) {
                $householdCounts[$household][$key] = ($householdCounts[$household][$key] ?? 0) + 1;
            }
        }

        $orders = [];

        foreach ([1, 2] as $pass) {
            foreach ($names as $index => $name) {
                if ($name['certain'] || isset($orders[$index])) {
                    continue;
                }

                $household = $entries[$index]['section'].'|'.$entries[$index]['household'];
                $order = $this->decideOrder($name, $householdCounts[$household] ?? [], $knownSurnames, $knownFirstNames);

                if ($order !== null) {
                    $orders[$index] = $order;
                    $knownSurnames[$name[$order]['key']] = true;
                    $knownFirstNames[$name[$order]['first_key']] = true;
                }
            }
        }

        $sectionDefaults = $this->sectionDefaults($entries, $orders);

        return array_map(function (array $name, int $index) use ($orders, $sectionDefaults, $entries): array {
            $guessed = ! $name['certain'] && ! isset($orders[$index]) && $name['token_count'] > 1;
            $order = $name['certain'] ? 'first_last' : ($orders[$index] ?? $sectionDefaults[$entries[$index]['section']] ?? 'first_last');

            return [
                'first_name' => $name[$order]['first_name'],
                'middle_name' => $name['middle_name'],
                'last_name' => $name[$order]['last_name'],
                'suffix' => $name['suffix'],
                'guessed' => $guessed,
            ];
        }, $names, array_keys($names));
    }

    /**
     * Break a raw name into both possible readings.
     *
     * @return array<string, mixed>
     */
    private function analyse(string $raw): array
    {
        $name = str_replace(['–', '—'], '-', $raw);
        $name = (string) preg_replace('/\s*-\s*/u', '-', $name);
        $name = (string) preg_replace('/(?<=\b\p{L})\.(?=\p{L}{2})/u', '. ', $name);
        $tokens = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $suffix = null;

        foreach ($tokens as $position => $token) {
            if (preg_match('/^(jr|sr)\.?\p{L}?$|^(ii|iii|iv)$/iu', $token, $match)) {
                $suffix = self::SUFFIXES[Str::lower($match[1] ?: $match[2])];
                unset($tokens[$position]);
            }
        }

        $tokens = array_values(array_map(fn (string $token): string => $this->fixCase($token), $tokens));
        $count = count($tokens);

        $initialAt = null;

        foreach ($tokens as $position => $token) {
            if ($position > 0 && $position < $count - 1 && preg_match('/^\p{L}\.?$/u', $token)) {
                $initialAt = $position;

                break;
            }
        }

        if ($initialAt !== null) {
            $reading = $this->reading(array_slice($tokens, 0, $initialAt), array_slice($tokens, $initialAt + 1));

            return [
                'certain' => true,
                'token_count' => $count,
                'middle_name' => Str::upper(rtrim($tokens[$initialAt], '.')).'.',
                'suffix' => $suffix,
                'first_last' => $reading,
                'surname_first' => $reading,
            ];
        }

        $surnameStart = max($count - 1, 0);

        while ($surnameStart > 1 && in_array(Str::lower($tokens[$surnameStart - 1]), self::PARTICLES, true)) {
            $surnameStart--;
        }

        $surnameEnd = 1;

        while ($surnameEnd < $count - 1 && in_array(Str::lower($tokens[$surnameEnd - 1]), self::PARTICLES, true)) {
            $surnameEnd++;
        }

        return [
            'certain' => $count < 2,
            'token_count' => $count,
            'middle_name' => null,
            'suffix' => $suffix,
            'first_last' => $this->reading(array_slice($tokens, 0, $surnameStart), array_slice($tokens, $surnameStart)),
            'surname_first' => $this->reading(array_slice($tokens, $surnameEnd), array_slice($tokens, 0, $surnameEnd)),
        ];
    }

    /**
     * @param  list<string>  $first
     * @param  list<string>  $last
     * @return array{first_name: string, last_name: string, key: string, first_key: string}
     */
    private function reading(array $first, array $last): array
    {
        return [
            'first_name' => implode(' ', $first),
            'last_name' => implode(' ', $last),
            'key' => Str::lower(implode(' ', $last)),
            'first_key' => Str::lower($first[0] ?? ''),
        ];
    }

    /**
     * Pick a reading from evidence, or null when the evidence is even.
     *
     * @param  array<string, int>  $householdCounts
     * @param  array<string, bool>  $knownSurnames
     * @param  array<string, bool>  $knownFirstNames
     */
    private function decideOrder(array $name, array $householdCounts, array $knownSurnames, array $knownFirstNames): ?string
    {
        $leading = $householdCounts[$name['surname_first']['key']] ?? 0;
        $trailing = $householdCounts[$name['first_last']['key']] ?? 0;

        if (max($leading, $trailing) >= 2 && $leading !== $trailing) {
            return $leading > $trailing ? 'surname_first' : 'first_last';
        }

        $surnameFirstScore = (int) isset($knownSurnames[$name['surname_first']['key']]) + (int) isset($knownFirstNames[$name['surname_first']['first_key']]);
        $firstLastScore = (int) isset($knownSurnames[$name['first_last']['key']]) + (int) isset($knownFirstNames[$name['first_last']['first_key']]);

        if ($surnameFirstScore === $firstLastScore) {
            return null;
        }

        return $surnameFirstScore > $firstLastScore ? 'surname_first' : 'first_last';
    }

    /**
     * The order most names in each section were found to follow.
     *
     * @param  list<array{name: string, household: string, section: string}>  $entries
     * @param  array<int, string>  $orders
     * @return array<string, string>
     */
    private function sectionDefaults(array $entries, array $orders): array
    {
        $tally = [];

        foreach ($orders as $index => $order) {
            $section = $entries[$index]['section'];
            $tally[$section][$order] = ($tally[$section][$order] ?? 0) + 1;
        }

        return array_map(
            fn (array $counts): string => ($counts['surname_first'] ?? 0) > ($counts['first_last'] ?? 0) ? 'surname_first' : 'first_last',
            $tally,
        );
    }

    /**
     * Repair stray capitals such as "MAsicampo" or "CO-OT".
     */
    private function fixCase(string $token): string
    {
        $letters = (string) preg_replace('/[^\p{L}]/u', '', $token);

        if (mb_strlen($letters) > 1 && (mb_strtoupper($letters) === $letters || preg_match('/^\p{Lu}{2}\p{Ll}/u', $letters))) {
            return Str::ucfirst(Str::lower($token));
        }

        return $token;
    }
}
