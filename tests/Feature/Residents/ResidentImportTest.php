<?php

use App\Models\ActivityLog;
use App\Models\Resident;
use App\Models\User;
use App\Services\ResidentImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * @param  list<list<string>>  $rows
 */
function residentCsv(array $rows, string $name = 'residents.csv'): UploadedFile
{
    $lines = array_map(fn (array $row): string => implode(',', $row), [ResidentImporter::COLUMNS, ...$rows]);

    return UploadedFile::fake()->createWithContent($name, implode("\n", $lines)."\n");
}

/**
 * A Word household list laid out like the barangay's printed table.
 */
function householdDocx(): UploadedFile
{
    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText('Republic of the Philippines');
    $section->addText('Purok Santan');

    $table = $section->addTable();
    $rows = [
        ['No.', 'Household Leader', 'Precinct Number', 'Remarks'],
        ['', '', '', 'Atoa'],
        ['1.', 'Conrado H. Banglos Jr.', '86B', ''],
        ['', 'Myrna L. Banglos', '', ''],
        ['', '', '', ''],
        ['Purok Manan- Aw'],
        ['2.', 'Dela Cruz Mario', '88-A', ''],
        ['', 'Dela Cruz Juanita', '88 - A', 'E'],
        ['', 'Sedenio Irene', '', ''],
    ];

    foreach ($rows as $cells) {
        $table->addRow();

        foreach ($cells as $cell) {
            $table->addCell(2000, count($cells) === 1 ? ['gridSpan' => 4] : [])->addText($cell);
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'household');
    IOFactory::createWriter($word, 'Word2007')->save($path);
    $content = file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent('household-list.docx', $content);
}

describe('access', function () {
    it('can be opened by admin and staff with the residents permission', function () {
        $this->actingAs($this->admin)->get(route('residents.import'))->assertSuccessful();

        $this->actingAs(User::factory()->staff(['residents'])->create())
            ->get(route('residents.import'))
            ->assertSuccessful();
    });

    it('is forbidden to staff without the residents permission and to residents', function () {
        $this->actingAs(User::factory()->staff(['certificates'])->create())
            ->get(route('residents.import'))
            ->assertForbidden();

        $residentUser = User::factory()->resident()->create();
        Resident::factory()->create(['user_id' => $residentUser->id]);

        $this->actingAs($residentUser)->get(route('residents.import'))->assertForbidden();
    });

    it('redirects guests to login', function () {
        $this->get(route('residents.import'))->assertRedirect(route('login'));
    });

    it('is linked from the residents index', function () {
        $this->actingAs($this->admin)
            ->get(route('residents.index'))
            ->assertSee(route('residents.import'));
    });
});

it('downloads a template with the expected header', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::residents.import')
        ->call('downloadTemplate')
        ->assertFileDownloaded('beciss-residents-import-template.csv');

    expect(app(ResidentImporter::class)->templateRows()[0])->toBe(ResidentImporter::COLUMNS);
});

it('rejects a file without the required columns', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::residents.import')
        ->set('file', UploadedFile::fake()->createWithContent('bad.csv', "name,age\nJuan,30\n"))
        ->assertHasErrors('file')
        ->assertSet('file', null);
});

it('previews new, duplicate and invalid rows', function () {
    Resident::factory()->create(['first_name' => 'Existing', 'last_name' => 'Person', 'middle_name' => null, 'suffix' => null]);

    Livewire::actingAs($this->admin)
        ->test('pages::residents.import')
        ->set('file', residentCsv([
            ['1', 'Santan', 'Banglos', 'Conrado', 'H.', 'Jr.', '', '', '', '', '', '86B', ''],
            ['1', 'Santan', 'Banglos', 'Myrna', 'L.', '', '', '', '', '', '', '', ''],
            ['2', 'Santan', 'Person', 'Existing', '', '', '', '', '', '', '', '', ''],
            ['3', 'Mars', 'Doe', 'John', '', '', 'x', '', '', '', '', '', ''],
            ['4', 'Santan', '', '', '', '', '', '', '', '', '', '', ''],
            ['5', 'Santan', 'Banglos', 'Myrna', '', '', '', '', '', '', '', '', ''],
        ]))
        ->assertHasNoErrors()
        ->assertSee('Ready to import')
        ->assertSee('Already registered as resident')
        ->assertSee('Unknown purok')
        ->assertSee('Gender must be male or female.')
        ->assertSee('Same person as row 3.')
        ->assertSee('Import 2 residents');
});

it('keeps people with different middle initials or birthdates apart', function () {
    Resident::factory()->create(['first_name' => 'Felix', 'last_name' => 'Lumayno', 'middle_name' => 'M.', 'suffix' => null, 'birthdate' => '1960-01-01']);

    $file = residentCsv([
        ['1', 'Santan', 'Lumayno', 'Felix', 'P.', '', '', '', '', '', '', '', ''],
        ['2', 'Santan', 'Lumayno', 'Felix', 'M.', '', '', '1990-01-01', '', '', '', '', ''],
    ]);

    $summary = app(ResidentImporter::class)->parse($file->getRealPath())['summary'];

    expect($summary['new'])->toBe(2)->and($summary['duplicates'])->toBe(0);
});

it('imports households with heads, members, puroks and precincts', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::residents.import')
        ->set('file', residentCsv([
            ['1', 'santan', 'Banglos', 'Conrado', 'H.', 'Jr.', 'M', '1970-03-02', 'married', '', '', '86 -B', ''],
            ['1', 'Purok Santan', 'Banglos', 'Myrna', 'L.', '', '', '', '', '', '', '', 'yes'],
            ['1', 'Santan', 'Banglos', 'Christian', 'L.', '', '', '', '', '', '', '', 'no'],
            ['2', 'Manan-Aw', 'Arapoc', 'Mascardo', '', '', '', '', '', '', '', '88-B', ''],
            ['', 'Sunflower', 'Perez', 'Allan', '', '', '', '', '', '', '', '', ''],
        ], 'sugod.csv'))
        ->call('import')
        ->assertRedirect(route('residents.index'));

    expect(Resident::count())->toBe(5);

    $head = Resident::where('first_name', 'Conrado')->first();

    expect($head)
        ->household_head_id->toBeNull()
        ->purok->toBe('Purok Santan')
        ->precinct_number->toBe('86B')
        ->is_voter->toBeTrue()
        ->gender->toBe('male')
        ->civil_status->toBe('married')
        ->status->toBe('approved')
        ->and($head->birthdate->toDateString())->toBe('1970-03-02')
        ->and($head->householdMembers->pluck('first_name')->sort()->values()->all())->toBe(['Christian', 'Myrna']);

    expect(Resident::where('first_name', 'Myrna')->first())
        ->is_voter->toBeTrue()
        ->birthdate->toBeNull()
        ->gender->toBeNull()
        ->civil_status->toBe('single')
        ->and(Resident::where('first_name', 'Christian')->first()->is_voter)->toBeFalse()
        ->and(Resident::where('first_name', 'Mascardo')->first())
        ->purok->toBe('Purok Manan-Aw')
        ->precinct_number->toBe('88B')
        ->household_head_id->toBeNull();

    $log = ActivityLog::latest('id')->first();

    expect($log)
        ->module->toBe('residents')
        ->action->toBe('imported')
        ->user_id->toBe($this->admin->id)
        ->and($log->properties)->toMatchArray(['created' => 5, 'skipped' => 0, 'households' => 3, 'file' => 'sugod.csv']);
});

it('skips invalid and duplicate rows and attaches members to an existing head', function () {
    $existingHead = Resident::factory()->create([
        'first_name' => 'Rico', 'last_name' => 'Lauron', 'middle_name' => 'P.', 'suffix' => null, 'purok' => 'Purok Santan',
    ]);

    $file = residentCsv([
        ['21', 'Santan', 'Lauron', 'Rico', 'P.', '', '', '', '', '', '', '', ''],
        ['21', 'Santan', 'Lauron', 'Lorena', 'P.', '', '', '', '', '', '', '', ''],
        ['22', 'Nowhere', 'Agan', 'Junel', '', '', '', '', '', '', '', '', ''],
    ]);

    $result = app(ResidentImporter::class)->import($file->getRealPath(), 'residents.csv');

    expect($result)->toBe(['created' => 1, 'skipped' => 2, 'households' => 1])
        ->and(Resident::where('first_name', 'Lorena')->first()->household_head_id)->toBe($existingHead->id)
        ->and(Resident::where('first_name', 'Junel')->exists())->toBeFalse();
});

it('reads spreadsheet exports with a BOM and Windows-1252 accents', function () {
    $content = "\u{FEFF}".implode(',', ResidentImporter::COLUMNS)."\n"
        .mb_convert_encoding("1,Manan-Aw,Cañoñero,Divina,,,,,,,,,\n", 'Windows-1252', 'UTF-8');

    $file = UploadedFile::fake()->createWithContent('export.csv', $content);
    $rows = app(ResidentImporter::class)->parse($file->getRealPath())['rows'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['status'])->toBe('new')
        ->and($rows[0]['attributes']['last_name'])->toBe('Cañoñero');
});

describe('incomplete residents', function () {
    it('render on the index, show and edit pages without a birthdate or gender', function () {
        $resident = Resident::factory()->incomplete()->create(['first_name' => 'Nobirth', 'precinct_number' => '86A']);

        $this->actingAs($this->admin)
            ->get(route('residents.index'))
            ->assertSuccessful()
            ->assertSee('Nobirth')
            ->assertSee('Incomplete');

        $this->actingAs($this->admin)
            ->get(route('residents.show', $resident))
            ->assertSuccessful()
            ->assertSee('Incomplete profile')
            ->assertSee('86A');

        Livewire::actingAs($this->admin)
            ->test('pages::residents.edit', ['resident' => $resident])
            ->assertSet('birthdate', '')
            ->assertSet('gender', '')
            ->assertSet('precinct_number', '86A');
    });

    it('can be filtered on the index', function () {
        Resident::factory()->create(['first_name' => 'Complete']);
        Resident::factory()->incomplete()->create(['first_name' => 'Halfdone']);

        Livewire::actingAs($this->admin)
            ->test('pages::residents.index')
            ->set('profile', 'incomplete')
            ->assertSee('Halfdone')
            ->assertDontSee('Complete');
    });

    it('are excluded from age groups', function () {
        Resident::factory()->incomplete()->create();

        expect(Resident::query()->ageGroup('senior')->count())->toBe(0)
            ->and(Resident::query()->incomplete()->count())->toBe(1);
    });
});

it('shows the household on the resident page', function () {
    $head = Resident::factory()->create(['first_name' => 'Headperson']);
    $member = Resident::factory()->create(['first_name' => 'Memberperson', 'household_head_id' => $head->id]);

    $this->actingAs($this->admin)
        ->get(route('residents.show', $head))
        ->assertSee('Memberperson');

    $this->actingAs($this->admin)
        ->get(route('residents.show', $member))
        ->assertSee('Headperson');
});

it('saves the precinct number from the create form', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::residents.create')
        ->set('first_name', 'Juan')
        ->set('last_name', 'Dela Cruz')
        ->set('birthdate', '1990-01-01')
        ->set('gender', 'male')
        ->set('civil_status', 'single')
        ->set('street', 'Mabini Street')
        ->set('purok', 'Purok Santan')
        ->set('residency_start_date', '2000-01-01')
        ->set('precinct_number', '87A')
        ->call('save')
        ->assertHasNoErrors();

    expect(Resident::where('first_name', 'Juan')->first())
        ->precinct_number->toBe('87A')
        ->purok->toBe('Purok Santan');
});

describe('documents', function () {
    it('imports a Word household list', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::residents.import')
            ->set('file', householdDocx())
            ->assertHasNoErrors()
            ->assertSee('Name order guessed from &quot;Sedenio Irene&quot;', false)
            ->call('import');

        $conrado = Resident::where('first_name', 'Conrado')->first();

        expect(Resident::count())->toBe(5)
            ->and($conrado)
            ->last_name->toBe('Banglos')
            ->middle_name->toBe('H.')
            ->suffix->toBe('Jr.')
            ->precinct_number->toBe('86B')
            ->purok->toBe('Purok Santan')
            ->and($conrado->householdMembers->pluck('first_name')->all())->toBe(['Myrna']);

        $mario = Resident::where('first_name', 'Mario')->first();

        expect($mario)
            ->last_name->toBe('Dela Cruz')
            ->purok->toBe('Purok Manan-Aw')
            ->precinct_number->toBe('88A')
            ->and(Resident::where('first_name', 'Juanita')->first()->precinct_number)->toBe('88A')
            ->and(Resident::where('first_name', 'Irene')->first())
            ->last_name->toBe('Sedenio')
            ->household_head_id->toBe($mario->id);
    });

    it('imports a PDF household list', function () {
        $glyph = "\u{F0FC}";
        $layout = implode("\n", [
            '                 REPUBLIC OF THE PHILIPPINES',
            '                        Purok Santan',
            'No.     Household Leader          Precinct Number      Remarks',
            '                                         Atoa   Dili atoa   undecided',
            "1.     Conrado H. Banglos Jr.        86B         {$glyph}",
            "         Myrna L. Banglos                        {$glyph}",
            "                                                 {$glyph}",
            "\f2.       Gabriel L. Banglos                    {$glyph}",
            "        Analyn G. Benedicto          86C         {$glyph}",
            '74.Fedeliza M. Laranjo     87A',
            '                    Purok Manan- Aw',
            '266.   Engracio M. Sedenio Jr.   88 - A',
            '        Elizabeth Sedenio       88 – A',
        ]);

        Process::fake(['*' => Process::result(output: $layout)]);

        Livewire::actingAs($this->admin)
            ->test('pages::residents.import')
            ->set('file', UploadedFile::fake()->createWithContent('election.pdf', '%PDF-1.4'))
            ->assertHasNoErrors()
            ->call('import');

        Process::assertRan(fn ($process): bool => $process->command[0] === 'pdftotext' && in_array('-layout', $process->command, true));

        expect(Resident::count())->toBe(7)
            ->and(Resident::where('first_name', 'Conrado')->first()->householdMembers->pluck('first_name')->all())->toBe(['Myrna'])
            ->and(Resident::where('first_name', 'Gabriel')->first()->householdMembers->pluck('first_name')->all())->toBe(['Analyn'])
            ->and(Resident::where('first_name', 'Analyn')->first()->precinct_number)->toBe('86C')
            ->and(Resident::where('first_name', 'Fedeliza')->first())
            ->last_name->toBe('Laranjo')
            ->household_head_id->toBeNull()
            ->and(Resident::where('first_name', 'Elizabeth')->first())
            ->purok->toBe('Purok Manan-Aw')
            ->precinct_number->toBe('88A')
            ->household_head_id->toBe(Resident::where('first_name', 'Engracio')->value('id'));
    });

    it('reports a PDF that cannot be read', function () {
        Process::fake(['*' => Process::result(exitCode: 1)]);

        Livewire::actingAs($this->admin)
            ->test('pages::residents.import')
            ->set('file', UploadedFile::fake()->createWithContent('locked.pdf', '%PDF-1.4'))
            ->assertHasErrors('file')
            ->assertSet('file', null);
    });

    it('reports a document without a household table', function () {
        Process::fake(['*' => Process::result(output: "Minutes of the barangay assembly\nNothing to see here\n")]);

        Livewire::actingAs($this->admin)
            ->test('pages::residents.import')
            ->set('file', UploadedFile::fake()->createWithContent('minutes.pdf', '%PDF-1.4'))
            ->assertHasErrors('file');
    });

    it('rejects other file types', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::residents.import')
            ->set('file', UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'))
            ->assertHasErrors('file');
    });
});

it('swaps a misread name order before importing', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::residents.import')
        ->set('file', residentCsv([
            ['1', 'Gumamela', 'Joelito', 'Repolidon', '', '', '', '', '', '', '', '', ''],
        ]))
        ->call('swapName', 2)
        ->assertSee('Repolidon, Joelito')
        ->call('import');

    expect(Resident::first())
        ->first_name->toBe('Joelito')
        ->last_name->toBe('Repolidon');
});

it('can mark everyone in the list as a registered voter', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::residents.import')
        ->set('everyoneIsVoter', true)
        ->set('file', residentCsv([
            ['1', 'Santan', 'Didal', 'Nier', '', '', '', '', '', '', '', '', 'no'],
        ]))
        ->call('import');

    expect(Resident::first()->is_voter)->toBeTrue();
});
