@props([
    'card' => 'rounded-lg border border-zinc-200 p-6 dark:border-zinc-700',
])

{{-- Personal Details --}}
<div class="{{ $card }}">
    <flux:heading size="lg" class="mb-4">{{ __('Personal Details') }}</flux:heading>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:field class="sm:col-span-2">
            <flux:label>{{ __('Place of Birth') }}</flux:label>
            <flux:input wire:model="place_of_birth" placeholder="{{ __('City / Municipality, Province') }}" />
            <flux:error name="place_of_birth" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Citizenship') }} <span class="text-red-500">*</span></flux:label>
            <flux:input wire:model="citizenship" required />
            <flux:error name="citizenship" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Blood Type') }}</flux:label>
            <flux:select wire:model="blood_type">
                <option value="">{{ __('Unknown') }}</option>
                @foreach (\App\Models\Resident::BLOOD_TYPES as $bloodType)
                    <option value="{{ $bloodType }}">{{ $bloodType }}</option>
                @endforeach
            </flux:select>
            <flux:error name="blood_type" />
        </flux:field>

        <flux:field class="sm:col-span-2">
            <flux:label>{{ __('Religion') }}</flux:label>
            <flux:input wire:model="religion" />
            <flux:error name="religion" />
        </flux:field>
    </div>
</div>

{{-- Education & Employment --}}
<div class="{{ $card }}">
    <flux:heading size="lg" class="mb-4">{{ __('Education & Employment') }}</flux:heading>

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:field>
            <flux:label>{{ __('Highest Educational Attainment') }}</flux:label>
            <flux:select wire:model="educational_attainment">
                <option value="">{{ __('Select') }}</option>
                @foreach (\App\Models\Resident::EDUCATION_LEVELS as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </flux:select>
            <flux:error name="educational_attainment" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Employment Status') }}</flux:label>
            <flux:select wire:model="employment_status">
                <option value="">{{ __('Select') }}</option>
                @foreach (\App\Models\Resident::EMPLOYMENT_STATUSES as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </flux:select>
            <flux:error name="employment_status" />
        </flux:field>

        {{ $slot }}
    </div>
</div>

{{-- Sector Classification --}}
<div class="{{ $card }}">
    <flux:heading size="lg" class="mb-1">{{ __('Sector Classification') }}</flux:heading>
    <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Senior citizen status is set automatically from the birthdate.') }}</flux:text>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (\App\Models\Resident::SECTORS as $column => $label)
            <flux:checkbox wire:model="{{ $column }}" label="{{ __($label) }}" />
        @endforeach
    </div>

    <flux:field class="mt-4 max-w-sm" x-show="$wire.is_pwd" x-cloak>
        <flux:label>{{ __('PWD ID No.') }}</flux:label>
        <flux:input wire:model="pwd_id_number" />
        <flux:error name="pwd_id_number" />
    </flux:field>
</div>
