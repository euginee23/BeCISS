{{--
    The "Send SMS" composer for a page using App\Concerns\SendsSms. Include it
    once; it also shows the confirmation after a successful send.
--}}
@if ($smsSent)
    <flux:callout variant="success" icon="check-circle" class="mb-6" :heading="$smsSent" />
@endif

@if ($this->smsAllowed())
    <flux:modal wire:model="showSmsModal" class="w-full max-w-lg">
        <form wire:submit="sendSms" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Send SMS') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ $this->smsRecipientName() }}
                    <span class="font-mono text-zinc-500">{{ $this->smsNumber() }}</span>
                    @if ($smsNetwork)
                        <flux:badge size="sm" class="ml-1">{{ $smsNetwork }}</flux:badge>
                    @endif
                </flux:text>
            </div>

            @if ($this->smsTestMode())
                <flux:callout variant="secondary" icon="beaker" :heading="__('SMS is in test mode. Messages are written to the SMS log and not delivered.')" />
            @endif

            @if ($smsUnreachable)
                <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Smart, TNT and Sun numbers cannot be reached yet.')">
                    <flux:callout.text>{{ __('The SMS account needs a sender name approved by IPROG for these networks. Contact the resident another way.') }}</flux:callout.text>
                </flux:callout>
            @endif

            <flux:field>
                <flux:label>{{ __('Template') }}</flux:label>
                <flux:select wire:model.live="smsTemplate">
                    @foreach ($this->smsTemplateOptions() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            {{--
                The counter adds the sender name IPROG puts in front of every
                text. Any non-GSM character (peso sign, curly quotes, emoji)
                makes the whole text Unicode, which is billed per 70 characters.
            --}}
            <flux:field x-data="{ overhead: {{ $this->smsOverhead() }} }">
                <flux:label>{{ __('Message') }}</flux:label>
                <flux:textarea wire:model="smsBody" rows="4" maxlength="480" />
                <div
                    class="mt-1 flex justify-between text-xs text-zinc-500"
                    x-data="{
                        get length() { return ($wire.smsBody ?? '').length + overhead },
                        get unicode() { return /[^\x00-\x7F]/.test($wire.smsBody ?? '') },
                        get perText() { return this.unicode ? 70 : 160 },
                        get texts() { return Math.max(1, Math.ceil(this.length / this.perText)) },
                    }"
                >
                    <span x-show="unicode" class="text-amber-600">{{ __('Special characters make this cost more. Avoid the peso sign and curly quotes.') }}</span>
                    <span class="ml-auto" x-text="length + ' / ' + perText + ' · ' + texts + (texts === 1 ? ' text' : ' texts')"></span>
                </div>
                <flux:error name="smsBody" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showSmsModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="paper-airplane" :disabled="$smsUnreachable" wire:loading.attr="disabled" wire:target="sendSms">
                    <span wire:loading.remove wire:target="sendSms">{{ __('Send') }}</span>
                    <span wire:loading wire:target="sendSms">{{ __('Sending...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
@endif
