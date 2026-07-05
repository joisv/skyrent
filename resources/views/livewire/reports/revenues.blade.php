<div x-data class="w-[65%] rounded-2xl border bg-white p-6 shadow">

    <div class="flex items-center justify-between mb-5">

        <button wire:click="previousMonth" class="rounded-lg p-2 hover:bg-gray-100">
            ←
        </button>

        <h2 class="font-semibold text-lg">
            {{ \Carbon\Carbon::create($year, $month)->translatedFormat('F Y') }}
        </h2>

        <button wire:click="nextMonth" class="rounded-lg p-2 hover:bg-gray-100">
            →
        </button>

    </div>

    <div class="grid grid-cols-7 gap-2 text-center text-sm text-gray-500 mb-3">

        <div>Sen</div>
        <div>Sel</div>
        <div>Rab</div>
        <div>Kam</div>
        <div>Jum</div>
        <div>Sab</div>
        <div>Min</div>

    </div>

    <div class="grid grid-cols-7 gap-2">

        @foreach ($this->calendar as $day)
            @php

                $selected = false;

                if ($startDate && !$endDate) {
                    $selected = $day['date'] == $startDate;
                }

                if ($startDate && $endDate) {
                    $selected = $day['date'] >= $startDate && $day['date'] <= $endDate;
                }

            @endphp

            <button @disabled($day['disabled']) wire:click="selectDate('{{ $day['date'] }}')"
                class="h-11 rounded-lg transition text-sm

    {{ !$day['currentMonth'] ? 'text-gray-400' : '' }}

    {{ $day['today'] ? 'border border-orange-500' : '' }}

    {{ $day['disabled'] ? ' text-gray-300' : '' }}

    {{ $selected ? 'bg-orange-500 text-white' : (!$day['disabled'] ? 'hover:bg-orange-100' : '') }}">
                {{ $day['day'] }}
            </button>
        @endforeach

    </div>

    {{-- <div class="mt-5 border-t pt-4 text-sm">

        <div>

            Start :
            {{ $startDate ?? '-' }}

        </div>

        <div>

            End :
            {{ $endDate ?? '-' }}

        </div>

    </div> --}}

</div>
