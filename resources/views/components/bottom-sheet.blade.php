@props([
    'id' => 'bottomSheet',
    'title' => null,
])

<div 
    x-data="{ open: false }" 
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center sm:hidden"
    id="{{ $id }}"
    @open-bottom-sheet.window="
        if ($event.detail.id === '{{ $id }}') {
            open = true
        }
    "
>
    <!-- Overlay -->
    <div 
        class="absolute inset-0 bg-black/50 "
        @click="open = false"
    ></div>

    <!-- Bottom Sheet Container -->
    <div 
        class="relative bg-white w-full rounded-t-2xl p-5 shadow-lg transform transition-all duration-300 overflow-y-auto max-h-[90vh] "
        x-show="open"
        x-transition:enter="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="translate-y-0"
        x-transition:leave-end="translate-y-full"
    >
        <!-- Tombol close -->
        <button 
            class="absolute top-2 right-2 text-gray-500"
            @click="open = false"
        >
            ✕
        </button>

        <!-- Slot isi konten -->
        {{ $slot }}
    </div>
</div>

