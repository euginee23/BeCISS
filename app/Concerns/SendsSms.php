<?php

namespace App\Concerns;

use App\Models\Resident;
use App\Services\Sms\IprogSmsSender;
use App\Services\Sms\NetworkDetector;
use App\Services\Sms\ResidentTexter;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SmsTemplates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

/**
 * The "Send SMS" composer, shared by the pages that text residents.
 *
 * A host page implements the abstract methods, adds the button partial to
 * its action bar and includes the modal partial once.
 *
 * @mixin Component
 */
trait SendsSms
{
    public bool $showSmsModal = false;

    public string $smsTemplate = '';

    public string $smsBody = '';

    /**
     * Network the resident's number is on, e.g. "Globe/TM". Null when not looked up.
     */
    public ?string $smsNetwork = null;

    /**
     * True when IPROG cannot deliver to that network on this account.
     */
    public bool $smsUnreachable = false;

    /**
     * Confirmation shown on the page after a successful send.
     */
    public ?string $smsSent = null;

    /**
     * The resident the text goes to.
     */
    abstract protected function smsResident(): ?Resident;

    /**
     * The record the text is about, used for the template and the log entry.
     */
    abstract protected function smsSubject(): Model;

    /**
     * Which group of config/sms-templates.php this page uses.
     */
    abstract protected function smsContext(): string;

    /**
     * The staff permission needed to text from this page.
     */
    abstract protected function smsPermission(): string;

    /**
     * The template that fits the record's current status.
     */
    abstract protected function smsDefaultTemplate(): string;

    public function openSmsModal(): void
    {
        abort_unless($this->smsAllowed(), 403);

        if (! $this->smsNumber()) {
            return;
        }

        $this->resetErrorBag('smsBody');
        $this->smsSent = null;
        $this->smsTemplate = SmsTemplates::exists($this->smsContext(), $this->smsDefaultTemplate())
            ? $this->smsDefaultTemplate()
            : (string) array_key_first(SmsTemplates::options($this->smsContext()));
        $this->applySmsTemplate();
        $this->detectSmsNetwork();

        $this->showSmsModal = true;
    }

    public function updatedSmsTemplate(): void
    {
        $this->applySmsTemplate();
    }

    public function sendSms(ResidentTexter $texter): void
    {
        abort_unless($this->smsAllowed(), 403);

        $this->validate(
            ['smsBody' => ['required', 'string', 'max:480']],
            ['smsBody.required' => __('Please write a message.')],
        );

        $resident = $this->smsResident();

        if (! $resident) {
            $this->addError('smsBody', __('This record has no resident to text.'));

            return;
        }

        try {
            $number = $texter->send($resident, $this->smsBody, $this->smsSubject(), $this->smsTemplate ?: null);
        } catch (Throwable $e) {
            /**
             * Nothing is logged on failure, so the activity log stays an
             * accurate record of what residents were actually told.
             */
            $this->addError('smsBody', __('Could not send: :reason', ['reason' => $e->getMessage()]));

            return;
        }

        $this->showSmsModal = false;
        $this->smsBody = '';
        $this->smsTemplate = '';
        $this->smsSent = __('SMS sent to :number.', ['number' => $number]);
    }

    /**
     * Whether the current user may text from this page.
     */
    public function smsAllowed(): bool
    {
        return (bool) Auth::user()?->hasPermission($this->smsPermission());
    }

    /**
     * The resident's sendable mobile number, or null when there is none.
     */
    public function smsNumber(): ?string
    {
        return $this->smsResident()?->smsNumber();
    }

    public function smsRecipientName(): string
    {
        return (string) $this->smsResident()?->full_name;
    }

    /**
     * @return array<string, string>
     */
    public function smsTemplateOptions(): array
    {
        return SmsTemplates::options($this->smsContext());
    }

    /**
     * Characters IPROG adds before the body (the account's sender name and a
     * space). They count towards the 160 of a billed text.
     */
    public function smsOverhead(): int
    {
        if (config('sms.default') !== 'iprogsms') {
            return 0;
        }

        $senderName = trim((string) config('sms.drivers.iprogsms.sender_name', ''), " \"'");

        return $senderName === '' ? 0 : mb_strlen($senderName) + 1;
    }

    /**
     * True when texts are logged instead of delivered.
     */
    public function smsTestMode(): bool
    {
        return config('sms.default') !== 'iprogsms';
    }

    private function applySmsTemplate(): void
    {
        if ($this->smsTemplate === '' || ! SmsTemplates::exists($this->smsContext(), $this->smsTemplate)) {
            return;
        }

        $this->smsBody = SmsTemplates::render($this->smsContext(), $this->smsTemplate, $this->smsSubject());
    }

    /**
     * Ask IPROG which network the number is on, so staff are warned before
     * trying a Smart, TNT or Sun number this account cannot reach.
     */
    private function detectSmsNetwork(): void
    {
        $this->smsNetwork = null;
        $this->smsUnreachable = false;

        $number = $this->smsNumber();
        $sender = app(SmsSender::class);

        if ($number === null || ! $sender instanceof NetworkDetector) {
            return;
        }

        $detected = $sender->detectNetwork($number);

        /**
         * An unlisted prefix still sends fine (0952 is a Globe number that
         * comes back as "Unknown Network"), so only a known network is shown.
         */
        if ($detected === null || $detected['network'] === IprogSmsSender::NETWORK_UNKNOWN) {
            return;
        }

        $this->smsNetwork = $detected['network'];
        $this->smsUnreachable = $detected['is_smart_tnt']
            && ! config('sms.drivers.iprogsms.sender_name_approved', false);
    }
}
