@php
    $unreadCount = $this->getUnreadCount();
    $notifications = $this->getUnreadNotifications();
    $hasNotifications = $notifications->isNotEmpty();
@endphp

<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative">
    <button
        @click="open = !open"
        type="button"
        class="relative p-2 text-gray-600 hover:text-gray-900 transition-colors duration-150 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        @if($unreadCount > 0)
            <span class="absolute top-0 right-0 flex items-center justify-center w-5 h-5 text-xs text-white bg-red-500 rounded-full">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        @click="open = false"
        class="absolute right-0 z-50 mt-2 w-80 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 overflow-hidden"
        style="display: none;"
    >
        <div class="p-3 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                @if($unreadCount > 0)
                    <button
                        wire:click="markAllAsRead"
                        class="text-xs text-cyan-600 hover:text-cyan-800"
                    >
                        Mark all read
                    </button>
                @endif
            </div>
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse($notifications as $notification)
                <div
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="p-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors duration-150"
                >
                    <p class="text-sm font-medium text-gray-900">{{ $notification->data['title'] ?? 'Notification' }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $notification->data['message'] ?? '' }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <div class="p-4 text-center text-gray-500 text-sm">
                    No new notifications
                </div>
            @endforelse
        </div>

        @if($unreadCount > 0)
            <div class="p-2 border-t border-gray-200 bg-gray-50">
                <a href="/admin/notifications" class="block text-center text-sm text-cyan-600 hover:text-cyan-800 font-medium">
                    View all notifications
                </a>
            </div>
        @endif
    </div>
</div>