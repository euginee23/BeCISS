{{--
    "Send SMS" action button for a page using App\Concerns\SendsSms.
    Hidden without the page's permission; disabled when the resident has no
    mobile number, so staff can see why texting is unavailable.
--}}
@if ($this->smsAllowed())
    @if ($this->smsNumber())
        <flux:button icon="chat-bubble-left-ellipsis" wire:click="openSmsModal">
            {{ __('Send SMS') }}
        </flux:button>
    @else
        <flux:tooltip :content="__('No mobile number on file')">
            <div>
                <flux:button icon="chat-bubble-left-ellipsis" disabled>
                    {{ __('Send SMS') }}
                </flux:button>
            </div>
        </flux:tooltip>
    @endif
@endif
