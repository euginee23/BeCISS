<?php

use App\Services\ResidentNameParser;

/**
 * @param  list<array{0: string, 1: string, 2?: string}>  $names  name, household, section
 * @return list<array<string, mixed>>
 */
function parseNames(array $names): array
{
    return (new ResidentNameParser)->parseAll(array_map(fn (array $name): array => [
        'name' => $name[0],
        'household' => $name[1],
        'section' => $name[2] ?? 'Purok Santan',
    ], $names));
}

it('splits first name, middle initial, compound surname and suffix', function () {
    [$juliet, $ma, $diane, $arnel, $gaudencio] = parseNames([
        ['Juliet M. Delos Reyes', '1'],
        ['Ma. Era S. Torres', '2'],
        ['Diane I Elorde', '3'],
        ['Arnel Jay S.Sumanting', '4'],
        ['Gaudencio Jr. Dacay', '5'],
    ]);

    expect($juliet)->toMatchArray(['first_name' => 'Juliet', 'middle_name' => 'M.', 'last_name' => 'Delos Reyes', 'guessed' => false])
        ->and($ma)->toMatchArray(['first_name' => 'Ma. Era', 'middle_name' => 'S.', 'last_name' => 'Torres'])
        ->and($diane)->toMatchArray(['first_name' => 'Diane', 'middle_name' => 'I.', 'last_name' => 'Elorde'])
        ->and($arnel)->toMatchArray(['first_name' => 'Arnel Jay', 'middle_name' => 'S.', 'last_name' => 'Sumanting'])
        ->and($gaudencio)->toMatchArray(['first_name' => 'Gaudencio', 'last_name' => 'Dacay', 'suffix' => 'Jr.']);
});

it('repairs casing and spacing', function () {
    [$ardiory, $tag, $dominador] = parseNames([
        ['Ardiory U. MAsicampo', '1'],
        ['Tag - asa Mychell', '2', 'Purok Manan-Aw'],
        ['Dominador L. CO-OT', '3'],
    ]);

    expect($ardiory['last_name'])->toBe('Masicampo')
        ->and($dominador['last_name'])->toBe('Co-ot')
        ->and([$tag['first_name'], $tag['last_name']])->toContain('Tag-asa');
});

it('reads surname-first names from the surname the household shares', function () {
    [$rolando, $emelyn, $mychell] = parseNames([
        ['Elorde Rolando', '284', 'Purok Manan-Aw'],
        ['Elorde Emelyn', '284', 'Purok Manan-Aw'],
        ['Mychell Elorde', '284', 'Purok Manan-Aw'],
    ]);

    expect($rolando)->toMatchArray(['first_name' => 'Rolando', 'last_name' => 'Elorde', 'guessed' => false])
        ->and($emelyn)->toMatchArray(['first_name' => 'Emelyn', 'last_name' => 'Elorde'])
        ->and($mychell)->toMatchArray(['first_name' => 'Mychell', 'last_name' => 'Elorde', 'guessed' => false]);
});

it('uses surnames and first names known elsewhere in the document', function () {
    $parsed = parseNames([
        ['Danilo C. Villegas', '1', 'Purok Rose'],
        ['Romeo D. Urbano', '2', 'Purok Rose'],
        ['Villegas Letecia', '3', 'Purok Manan-Aw'],
        ['Moises Villegas', '4', 'Purok Manan-Aw'],
        ['Romeo Arcebes', '5', 'Purok Manan-Aw'],
    ]);

    expect($parsed[2])->toMatchArray(['first_name' => 'Letecia', 'last_name' => 'Villegas', 'guessed' => false])
        ->and($parsed[3])->toMatchArray(['first_name' => 'Moises', 'last_name' => 'Villegas', 'guessed' => false])
        ->and($parsed[4])->toMatchArray(['first_name' => 'Romeo', 'last_name' => 'Arcebes', 'guessed' => false]);
});

it('falls back to the purok usual order and flags the guess', function () {
    $parsed = parseNames([
        ['Arapoc Mascardo', '267', 'Purok Manan-Aw'],
        ['Arapoc Rosita', '267', 'Purok Manan-Aw'],
        ['Dayday Janver', '283', 'Purok Manan-Aw'],
        ['Ronelyn Paglinawan', '19'],
    ]);

    expect($parsed[2])->toMatchArray(['first_name' => 'Janver', 'last_name' => 'Dayday', 'guessed' => true])
        ->and($parsed[3])->toMatchArray(['first_name' => 'Ronelyn', 'last_name' => 'Paglinawan', 'guessed' => true]);
});
