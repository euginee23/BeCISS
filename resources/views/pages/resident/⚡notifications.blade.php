<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Title('Notifications')]
#[Layout('layouts::app')]
class extends Component {
    use WithPagination;

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->where('id', $id)->update(['read_at' => now()]);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    #[Computed]
    public function notifications()
    {
        return auth()->user()->notifications()->latest()->paginate(15);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }
}; ?>

<div>
    {{-- Page Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Notifications') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Your activity and updates') }}</flux:text>
        </div>

        @if ($this->unreadCount > 0)
            <flux:button variant="ghost" wire:click="markAllRead" icon="check">
                {{ __('Mark all as read') }}
            </flux:button>
        @endif
    </div>

    @if ($this->notifications->isEmpty())
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <div class="flex flex-col items-center justify-center py-20 text-center gap-3">
                <div class="size-16 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                    <flux:icon name="bell-slash" class="size-8 text-zinc-400" />
                </div>
                <div>
                    <flux:heading>No notifications</flux:heading>
                    <flux:text class="text-zinc-400 mt-1 max-w-xs">You're all caught up! Notifications about your certificates, appointments, and registration will appear here.</flux:text>
                </div>
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($this->notifications as $notification)
                @php
                    $data = $notification->data;
                    $isUnread = is_null($notification->read_at);
                    $iconMap = [
                        'registration_approved'  => ['icon' => 'check-circle',   'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50 dark:bg-emerald-900/20'],
                        'registration_rejected'  => ['icon' => 'x-circle',       'color' => 'text-red-500',     'bg' => 'bg-red-50 dark:bg-red-900/20'],
                        'certificate_processing' => ['icon' => 'cog-6-tooth',    'color' => 'text-blue-500',    'bg' => 'bg-blue-50 dark:bg-blue-900/20'],
                        'certificate_ready'      => ['icon' => 'document-check', 'color' => 'text-emerald-500', 'bg' => 'bg-emerald-50 dark:bg-emerald-900/20'],
                        'certificate_completed'  => ['icon' => 'check-badge',    'color' => 'text-green-600',   'bg' => 'bg-green-50 dark:bg-green-900/20'],
                        'certificate_rejected'   => ['icon' => 'x-circle',       'color' => 'text-red-500',     'bg' => 'bg-red-50 dark:bg-red-900/20'],
                    ];
                    $icon = $iconMap[$data['type']] ?? ['icon' => 'bell', 'color' => 'text-zinc-400', 'bg' => 'bg-zinc-100 dark:bg-zinc-800'];
                @endphp

                <div
                    wire:click="markRead('{{ $notification->id }}')"
                    class="flex items-start gap-4 px-6 py-4 transition-colors cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/40 {{ $isUnread ? 'bg-emerald-50/40 dark:bg-emerald-900/5' : '' }}"
                    @if (!empty($data['url']))
                        onclick="window.location.href='{{ $data['url'] }}'"
                    @endif
                >
                    {{-- Icon --}}
                    <div class="mt-0.5 shrink-0 flex items-center justify-center size-10 rounded-full {{ $icon['bg'] }}">
                        <flux:icon name="{{ $icon['icon'] }}" class="size-5 {{ $icon['color'] }}" />
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                                {{ $data['title'] }}
                            </p>
                            <span class="shrink-0 text-xs text-zinc-400 dark:text-zinc-500 whitespace-nowrap">
                                {{ $notification->created_at->diffForHumans() }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                            {{ $data['body'] }}
                        </p>
                    </div>

                    {{-- Unread dot --}}
                    @if ($isUnread)
                        <span class="mt-2.5 shrink-0 size-2.5 rounded-full bg-emerald-500"></span>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($this->notifications->hasPages())
            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @endif
    @endif
</div>
