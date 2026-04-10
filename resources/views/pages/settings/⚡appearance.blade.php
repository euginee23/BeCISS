<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Title('Appearance settings')]
#[Layout('layouts::app')]
class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div x-data class="space-y-3">
            <div class="inline-flex rounded-xl border border-zinc-200 p-1 dark:border-zinc-700">
                <button
                    type="button"
                    x-on:click="$flux.appearance = 'light'"
                    x-bind:class="$flux.appearance === 'light' ? 'bg-accent text-white' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                >
                    <flux:icon.sun variant="mini" />
                    {{ __('Light') }}
                </button>

                <button
                    type="button"
                    x-on:click="$flux.appearance = 'dark'"
                    x-bind:class="$flux.appearance === 'dark' ? 'bg-accent text-white' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                >
                    <flux:icon.moon variant="mini" />
                    {{ __('Dark') }}
                </button>

                <button
                    type="button"
                    x-on:click="$flux.appearance = 'system'"
                    x-bind:class="$flux.appearance === 'system' ? 'bg-accent text-white' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                >
                    <flux:icon.computer-desktop variant="mini" />
                    {{ __('System') }}
                </button>
            </div>

            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Current preference:') }}
                <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="$flux.appearance"></span>
            </flux:text>
        </div>
    </x-pages::settings.layout>
</section>
