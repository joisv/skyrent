<div class="max-w-7xl mx-auto p-6">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    {{-- STEP INDICATOR --}}
    <div class="flex items-center justify-between mb-8">
        {{-- LEFT --}}
        <div>
            {{-- STEP INDICATOR --}}
            <div class="flex items-center gap-4 text-sm">

                @php
                    $steps = [
                        1 => 'iPhone',
                        2 => 'Jam Sewa',
                        3 => 'Detail Sewa',
                        4 => 'Review',
                    ];
                @endphp

                @foreach ($steps as $number => $label)
                    <div class="flex items-center gap-1">
                        <input type="radio" name="step" value="{{ $number }}" @checked($step === $number)
                            disabled
                            class="appearance-none w-4 h-4 rounded-full border hover:scale-125
                        {{ $step >= $number ? 'bg-black border-black' : 'border-gray-300' }}" />

                        <label class="text-lg font-normal {{ $step >= $number ? 'text-black' : 'text-gray-400' }}">
                            {{ $label }}
                        </label>
                    </div>

                    @if (!$loop->last)
                        <span class="w-6 h-px bg-gray-300"></span>
                    @endif
                @endforeach

            </div>
        </div>

        {{-- RIGHT --}}
        <div class="flex items-center gap-4">

            {{-- SEARCH ICON --}}
            <button class="p-2 rounded hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>

            {{-- NEXT BUTTON --}}
            {{-- <button wire:click="next" class="flex items-center gap-2 px-4 py-2 bg-black text-white text-sm rounded">
                Lanjut
                <span>→</span>
            </button> --}}

        </div>
    </div>


    {{-- CONTENT --}}
    @if ($step === 1)
        <livewire:rent.steps.iphone wire:model="requested_booking_date" wire:model="requested_time" />
    @elseif ($step === 2)
        <livewire:rent.steps.duration />
    @elseif ($step === 3)
        <livewire:rent.steps.detail />
    @elseif ($step === 4)
        <livewire:rent.steps.review />
    @endif

    {{-- NAV --}}
    <div class="flex justify-between mt-8">
        @if ($step > 1)
            <button wire:click="back" class="px-4 py-2 border">
                Kembali
            </button>
        @endif

        @if ($step < 4)
            <button wire:click="next" class="px-6 py-2 bg-black text-white">
                Lanjut →
            </button>
        @else
            <button wire:click="submit" class="px-6 py-2 bg-green-600 text-white">
                Konfirmasi
            </button>
        @endif
    </div>
</div>
