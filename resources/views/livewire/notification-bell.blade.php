<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    #[Computed]
    public function notifications()
    {
        return auth()->user()->notifications()->latest()->take(8)->get();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->where('id', $id)->update(['read_at' => now()]);
    }
}; ?>

<div x-data="{ open: false }" class="relative">
    {{-- Bell Button --}}
    <button
        @click="open = !open"
        class="relative flex items-center justify-center size-9 rounded-lg text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
        aria-label="Notifications"
    >
        <flux:icon name="bell" class="size-5" />

        @if ($this->unreadCount > 0)
            <span class="absolute top-1 right-1 flex size-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white leading-none">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown Panel --}}
    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 top-full mt-2 w-80 sm:w-96 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xl z-50 overflow-hidden"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800">
            <span class="text-sm font-semibold text-zinc-900 dark:text-white">Notifications</span>
            @if ($this->unreadCount > 0)
                <button
                    wire:click="markAllRead"
                    class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline font-medium"
                >
                    Mark all as read
                </button>
            @endif
        </div>

        {{-- Notification List --}}
        <div class="max-h-96 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($this->notifications as $notification)
                @php
                    $data = $notification->data;
                    $isUnread = is_null($notification->read_at);
                    $iconMap = [
                        'registration_approved'   => ['icon' => 'check-circle',    'color' => 'text-emerald-500'],
                        'registration_rejected'   => ['icon' => 'x-circle',        'color' => 'text-red-500'],
                        'certificate_processing'  => ['icon' => 'cog-6-tooth',     'color' => 'text-blue-500'],
                        'certificate_ready'       => ['icon' => 'document-check',  'color' => 'text-emerald-500'],
                        'certificate_completed'   => ['icon' => 'check-badge',     'color' => 'text-green-600'],
                        'certificate_rejected'    => ['icon' => 'x-circle',        'color' => 'text-red-500'],
                        'appointment_booked'      => ['icon' => 'calendar',        'color' => 'text-blue-500'],
                        'appointment_confirmed'   => ['icon' => 'calendar-days',   'color' => 'text-emerald-500'],
                        'appointment_completed'   => ['icon' => 'check-circle',    'color' => 'text-green-600'],
                        'appointment_cancelled'   => ['icon' => 'x-circle',        'color' => 'text-red-500'],
                        'appointment_no_show'     => ['icon' => 'no-symbol',       'color' => 'text-amber-500'],
                    ];
                    $icon = $iconMap[$data['type']] ?? ['icon' => 'bell', 'color' => 'text-zinc-400'];
                @endphp

                <div
                    wire:click="markRead('{{ $notification->id }}')"
                    class="flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/60 transition-colors {{ $isUnread ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : '' }}"
                    @if ($data['url'])
                        onclick="window.location.href='{{ $data['url'] }}'"
                    @endif
                >
                    <div class="mt-0.5 shrink-0 flex items-center justify-center size-8 rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="{{ $icon['icon'] }}" class="size-4 {{ $icon['color'] }}" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white leading-snug {{ $isUnread ? '' : 'font-medium' }}">
                            {{ $data['title'] }}
                        </p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 leading-relaxed line-clamp-2">
                            {{ $data['body'] }}
                        </p>
                        <p class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-1">
                            {{ $notification->created_at->diffForHumans() }}
                        </p>
                    </div>

                    @if ($isUnread)
                        <span class="mt-2 shrink-0 size-2 rounded-full bg-emerald-500"></span>
                    @endif
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-10 gap-2 text-center px-4">
                    <flux:icon name="bell-slash" class="size-8 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No notifications yet</p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        @if ($this->notifications->isNotEmpty())
            <div class="border-t border-zinc-100 dark:border-zinc-800 px-4 py-2.5 text-center">
                <a
                    href="{{ route('resident.notifications') }}"
                    wire:navigate
                    class="text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:underline"
                    @click="open = false"
                >
                    See all notifications
                </a>
            </div>
        @endif
    </div>
</div>
