@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="BeCISS" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-sm">
            <x-app-logo-icon class="size-5 fill-current text-white" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="BeCISS" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-gradient-to-br from-emerald-500 to-emerald-700 text-white shadow-sm">
            <x-app-logo-icon class="size-5 fill-current text-white" />
        </x-slot>
    </flux:brand>
@endif
