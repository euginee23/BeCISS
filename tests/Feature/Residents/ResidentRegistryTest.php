<?php

use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function newResidentForm()
{
    return Livewire::actingAs(test()->admin)
        ->test('pages::residents.create')
        ->set('first_name', 'Juan')
        ->set('last_name', 'Dela Cruz')
        ->set('birthdate', '1990-05-15')
        ->set('gender', 'male')
        ->set('civil_status', 'married')
        ->set('street', 'Main Street')
        ->set('purok', 'Purok 2')
        ->set('residency_start_date', now()->subYears(5)->toDateString());
}

describe('staff-registered residents', function () {
    it('are approved and listed in the registry straight away', function () {
        newResidentForm()->call('save')->assertHasNoErrors();

        $resident = Resident::where('last_name', 'Dela Cruz')->sole();

        expect($resident->status)->toBe('approved')
            ->and($resident->approved_at)->not->toBeNull()
            ->and($resident->user_id)->toBeNull();

        Livewire::actingAs($this->admin)
            ->test('pages::residents.index')
            ->assertSee('Dela Cruz')
            ->assertSee('Walk-in');
    });

    it('saves the extended profile fields', function () {
        newResidentForm()
            ->set('place_of_birth', 'Cebu City')
            ->set('citizenship', 'Filipino')
            ->set('religion', 'Roman Catholic')
            ->set('blood_type', 'O+')
            ->set('email', 'juan@example.com')
            ->set('educational_attainment', 'college_graduate')
            ->set('employment_status', 'employed')
            ->set('is_pwd', true)
            ->set('pwd_id_number', 'PWD-1234')
            ->set('is_solo_parent', true)
            ->set('is_4ps_beneficiary', true)
            ->call('save')
            ->assertHasNoErrors();

        expect(Resident::where('last_name', 'Dela Cruz')->sole())
            ->place_of_birth->toBe('Cebu City')
            ->religion->toBe('Roman Catholic')
            ->blood_type->toBe('O+')
            ->email->toBe('juan@example.com')
            ->education_label->toBe('College Graduate')
            ->employment_label->toBe('Employed')
            ->is_pwd->toBeTrue()
            ->pwd_id_number->toBe('PWD-1234')
            ->is_solo_parent->toBeTrue()
            ->is_4ps_beneficiary->toBeTrue();
    });

    it('rejects values outside the option lists', function () {
        newResidentForm()
            ->set('blood_type', 'Z+')
            ->set('educational_attainment', 'phd_in_magic')
            ->set('employment_status', 'astronaut')
            ->call('save')
            ->assertHasErrors(['blood_type', 'educational_attainment', 'employment_status']);
    });

    it('accepts every civil status the database allows', function () {
        newResidentForm()->set('civil_status', 'divorced')->call('save')->assertHasNoErrors();
    });

    it('updates the extended fields on edit', function () {
        $resident = Resident::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::residents.edit', ['resident' => $resident])
            ->set('religion', 'Islam')
            ->set('is_ofw', true)
            ->call('save')
            ->assertHasNoErrors();

        expect($resident->fresh())
            ->religion->toBe('Islam')
            ->is_ofw->toBeTrue();
    });

    it('can be approved without an account and without sending mail', function () {
        Mail::fake();
        $resident = Resident::factory()->pending()->create(['user_id' => null]);

        Livewire::actingAs($this->admin)
            ->test('pages::residents.index')
            ->call('approveResident', $resident->id);

        expect($resident->fresh()->status)->toBe('approved');
        Mail::assertNothingSent();
    });
});

describe('registry filters', function () {
    beforeEach(function () {
        $this->senior = Resident::factory()->senior()->female()->create(['last_name' => 'Seniorita', 'purok' => 'Purok 1', 'is_voter' => true, 'civil_status' => 'married']);
        $this->minor = Resident::factory()->minor()->male()->create(['last_name' => 'Minorson', 'purok' => 'Purok 2', 'is_voter' => false, 'civil_status' => 'single']);
        $this->pwd = Resident::factory()->adult()->male()->pwd()->create(['last_name' => 'Pwdman', 'purok' => 'Purok 3', 'is_voter' => true, 'civil_status' => 'widowed']);
        $this->online = Resident::factory()->adult()->female()->create([
            'last_name' => 'Onlineson',
            'civil_status' => 'single',
            'purok' => 'Purok 3',
            'is_voter' => false,
            'user_id' => User::factory()->resident()->create()->id,
        ]);
    });

    it('filters the registry', function (string $property, string $value, array $expected) {
        $names = Livewire::actingAs($this->admin)
            ->test('pages::residents.index')
            ->set($property, $value)
            ->instance()
            ->residents
            ->pluck('last_name')
            ->sort()
            ->values()
            ->all();

        expect($names)->toBe($expected);
    })->with([
        'purok' => ['purok', 'Purok 3', ['Onlineson', 'Pwdman']],
        'gender' => ['gender', 'male', ['Minorson', 'Pwdman']],
        'civil status' => ['civilStatus', 'widowed', ['Pwdman']],
        'minors' => ['ageGroup', 'minor', ['Minorson']],
        'seniors' => ['ageGroup', 'senior', ['Seniorita']],
        'voters' => ['voter', 'yes', ['Pwdman', 'Seniorita']],
        'non-voters' => ['voter', 'no', ['Minorson', 'Onlineson']],
        'senior sector' => ['sector', 'senior', ['Seniorita']],
        'pwd sector' => ['sector', 'is_pwd', ['Pwdman']],
        'with account' => ['account', 'with', ['Onlineson']],
        'without account' => ['account', 'without', ['Minorson', 'Pwdman', 'Seniorita']],
    ]);

    it('ignores unknown sort columns', function () {
        Livewire::actingAs($this->admin)
            ->withQueryParams(['sortBy' => 'password; drop table residents'])
            ->test('pages::residents.index')
            ->assertSuccessful()
            ->assertSee('Pwdman');
    });

    it('shows sector badges on the resident page', function () {
        $this->actingAs($this->admin)
            ->get(route('residents.show', $this->pwd))
            ->assertSuccessful()
            ->assertSee('Person with Disability (PWD)');

        $this->actingAs($this->admin)
            ->get(route('residents.show', $this->senior))
            ->assertSee('Senior Citizen');
    });
});

it('lets residents fill the extended fields during sign-up', function () {
    Mail::fake();
    $user = User::factory()->resident()->create();

    Livewire::actingAs($user)
        ->test('pages::complete-profile')
        ->set('first_name', 'Ana')
        ->set('last_name', 'Reyes')
        ->set('birthdate', '1995-01-01')
        ->set('gender', 'female')
        ->set('contact_number', '09171234567')
        ->set('street', 'Rizal Street')
        ->set('purok', 'Purok 4')
        ->set('residency_start_date', '2020-01-01')
        ->set('is_solo_parent', true)
        ->set('educational_attainment', 'high_school_graduate')
        ->call('submitProfile')
        ->assertHasNoErrors();

    expect($user->fresh()->resident)
        ->is_solo_parent->toBeTrue()
        ->educational_attainment->toBe('high_school_graduate')
        ->status->toBe('pending');
});
