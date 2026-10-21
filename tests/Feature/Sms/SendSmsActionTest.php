<?php

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Blotter;
use App\Models\Certificate;
use App\Models\Resident;
use App\Models\User;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SmsTemplates;
use Livewire\Livewire;

/**
 * Records every text instead of sending it.
 */
class FakeSmsSender implements SmsSender
{
    /** @var list<array{to: string, message: string}> */
    public array $sent = [];

    public ?string $failWith = null;

    public function send(string $to, string $message): void
    {
        if ($this->failWith) {
            throw new RuntimeException($this->failWith);
        }

        $this->sent[] = ['to' => $to, 'message' => $message];
    }
}

beforeEach(function () {
    $this->sms = new FakeSmsSender;
    $this->app->instance(SmsSender::class, $this->sms);

    $this->admin = User::factory()->admin()->create();
    $this->resident = Resident::factory()->create(['first_name' => 'Juan', 'contact_number' => '0917 123 4567 ']);
});

dataset('pages', [
    'certificate' => [fn () => ['pages::certificates.show', ['certificate' => Certificate::factory()->readyForPickup()->create(['resident_id' => test()->resident->id])]], 'certificates'],
    'blotter' => [fn () => ['pages::blotters.show', ['blotter' => Blotter::factory()->readyForPickup()->create(['resident_id' => test()->resident->id])]], 'blotters'],
    'appointment' => [fn () => ['pages::appointments.show', ['appointment' => Appointment::factory()->confirmed()->create(['resident_id' => test()->resident->id])]], 'appointments'],
    'resident' => [fn () => ['pages::residents.show', ['resident' => test()->resident]], 'residents'],
]);

test('staff can text the resident from the page', function (Closure $page, string $module) {
    [$component, $params] = $page();

    Livewire::actingAs($this->admin)
        ->test($component, $params)
        ->assertSee('Send SMS')
        ->call('openSmsModal')
        ->assertSet('showSmsModal', true)
        ->assertSet('smsBody', fn (string $body) => str_contains($body, 'Hi Juan'))
        ->call('sendSms')
        ->assertHasNoErrors()
        ->assertSet('showSmsModal', false)
        ->assertSee('SMS sent to +639171234567.');

    expect($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['to'])->toBe('+639171234567');

    $log = ActivityLog::where('action', 'sms_sent')->sole();

    expect($log->module)->toBe($module)
        ->and($log->properties['to'])->toBe('+639171234567')
        ->and($log->properties['body'])->toBe($this->sms->sent[0]['message']);
})->with('pages');

test('a failed send shows the reason and logs nothing', function () {
    $this->sms->failWith = 'IPROG SMS rejected the message: Insufficient credits';
    $certificate = Certificate::factory()->readyForPickup()->create(['resident_id' => $this->resident->id]);

    Livewire::actingAs($this->admin)
        ->test('pages::certificates.show', ['certificate' => $certificate])
        ->call('openSmsModal')
        ->call('sendSms')
        ->assertHasErrors(['smsBody'])
        ->assertSet('showSmsModal', true);

    expect(ActivityLog::where('action', 'sms_sent')->exists())->toBeFalse();
});

test('a resident without a mobile number cannot be texted', function (?string $number) {
    $resident = Resident::factory()->create(['contact_number' => $number]);

    Livewire::actingAs($this->admin)
        ->test('pages::residents.show', ['resident' => $resident])
        ->assertSeeHtml('No mobile number on file')
        ->call('openSmsModal')
        ->assertSet('showSmsModal', false);

    expect($this->sms->sent)->toBeEmpty();
})->with([null, '', '(032) 123 4567']);

test('staff text only from pages their permission opens', function () {
    $staff = User::factory()->staff(['residents'])->create();
    $certificate = Certificate::factory()->readyForPickup()->create(['resident_id' => $this->resident->id]);

    $page = Livewire::actingAs($staff)
        ->test('pages::residents.show', ['resident' => $this->resident])
        ->assertSee('Send SMS');

    $this->actingAs($staff)->get(route('certificates.show', $certificate))->assertForbidden();

    $page->call('openSmsModal')->assertSet('showSmsModal', true);
});

test('the default template follows the record status', function () {
    $awaiting = Certificate::factory()->create(['resident_id' => $this->resident->id, 'status' => 'awaiting_payment', 'fee' => 50]);

    Livewire::actingAs($this->admin)
        ->test('pages::certificates.show', ['certificate' => $awaiting])
        ->call('openSmsModal')
        ->assertSet('smsTemplate', 'payment_due')
        ->assertSet('smsBody', fn (string $body) => str_contains($body, 'PHP 50.00') && str_contains($body, $awaiting->certificate_number));
});

test('templates fill every placeholder and stay plain ASCII', function () {
    $records = [
        'certificate' => Certificate::factory()->create(['resident_id' => $this->resident->id, 'rejection_reason' => 'Missing ID']),
        'blotter' => Blotter::factory()->create(['resident_id' => $this->resident->id]),
        'appointment' => Appointment::factory()->create(['resident_id' => $this->resident->id, 'cancellation_reason' => 'Holiday']),
        'resident' => $this->resident,
    ];

    foreach ($records as $context => $record) {
        foreach (array_keys(SmsTemplates::options($context)) as $key) {
            $body = SmsTemplates::render($context, $key, $record);

            expect($body)->not->toMatch('/:[a-z]+/')
                ->and($body)->toMatch('/^[\x20-\x7E]+$/');
        }
    }
});
