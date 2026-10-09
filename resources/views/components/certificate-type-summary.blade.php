@props([
    'type',
])

<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-900/20">
    <div class="flex items-start gap-3">
        <flux:icon name="banknotes" class="size-5 text-emerald-600 mt-1" />
        <div class="flex-1 space-y-3">
            <div>
                <flux:text class="font-medium text-emerald-900 dark:text-emerald-100">
                    {{ __('Processing Fee') }}
                </flux:text>
                <flux:text class="text-2xl font-bold text-emerald-600">
                    @if ($type->isFree())
                        {{ __('Free') }}
                    @else
                        ₱{{ number_format((float) $type->fee, 2) }}
                    @endif
                </flux:text>
            </div>

            @if ($type->description)
                <flux:text class="text-sm text-emerald-900 dark:text-emerald-100">{{ $type->description }}</flux:text>
            @endif

            @if ($type->requirements)
                <div>
                    <flux:text class="text-sm font-medium text-emerald-900 dark:text-emerald-100">{{ __('Requirements') }}</flux:text>
                    <flux:text class="text-sm whitespace-pre-line text-emerald-800 dark:text-emerald-200">{{ $type->requirements }}</flux:text>
                </div>
            @endif
        </div>
    </div>
</div>
