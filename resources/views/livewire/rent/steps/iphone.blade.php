<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">

    @foreach ($iphones as $iphone)
        <button wire:click="selectIphone({{ $iphone->id }})" @disabled(!$iphone->is_available)
            class="
                relative rounded-xl border p-4 text-left transition
                {{ $selectedIphoneId === $iphone->id ? 'border-black ring-2 ring-black' : 'border-gray-200' }}
                {{ !$iphone->is_available ? 'opacity-40 cursor-not-allowed' : 'hover:border-black' }}
            ">
            @dump([
                'available' => $iphone->is_available,
                'status' => $iphone->bookings->last()?->status
            ])
            {{-- IMAGE --}}
            <div class="flex justify-center mb-4">
                <img src="{{ $iphone->image }}" alt="{{ $iphone->name }}" class="h-40 object-contain">
            </div>

            {{-- NAME --}}
            <p class="text-sm font-medium text-gray-900">
                {{ $iphone->name }}
            </p>

            {{-- CODE / SERIAL --}}
            <p class="text-xs text-gray-500 mt-1">
                {{ $iphone->code ?? '—' }}
            </p>

            {{-- STATUS --}}
            @if (!$iphone->is_available)
                <span class="absolute top-3 right-3 text-xs bg-red-100 text-red-600 px-2 py-1 rounded">
                    Sedang disewa
                </span>
            @endif
        </button>
    @endforeach

</div>
