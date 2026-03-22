@if(auth()->check() && auth()->user()->isResident())
    <x-layouts::app.topnav :title="$title ?? null">
        {{ $slot }}
    </x-layouts::app.topnav>
@else
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@endif
