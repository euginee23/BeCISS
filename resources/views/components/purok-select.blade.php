@props([
    'wire',
    'label' => 'Purok / Zone',
    'required' => false,
])

<flux:field>
    <flux:label>
        {{ __($label) }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </flux:label>
    <flux:select wire:model="{{ $wire }}" :required="$required">
        <option value="">{{ __('Select purok') }}</option>
        @foreach (\App\Models\Resident::PUROKS as $purok)
            <option value="{{ $purok }}">{{ $purok }}</option>
        @endforeach
    </flux:select>
    <flux:error name="{{ $wire }}" />
</flux:field>
