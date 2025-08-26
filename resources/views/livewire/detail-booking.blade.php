<div class="max-w-4xl mx-auto p-4 space-y-6" @close-modal="show = false">

    {{-- Header Booking --}}
    <div class="bg-white rounded-xl shadow p-4 space-y-2">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold">Detail Booking</h1>
            <x-mary-dropdown> <x-slot:trigger> <button
                        class="px-2 py-1 rounded text-xs font-semibold {{ $detailBookingIphones?->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : ($detailBookingIphones?->status === 'confirmed' ? 'bg-green-100 text-green-700' : ($detailBookingIphones?->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">{{ ucfirst($detailBookingIphones?->status) }}
                    </button> </x-slot:trigger>
                <div class="p-1"> <button {{ $detailBookingIphones?->status === 'pending' ? 'disabled' : '' }}
                        class="disabled:bg-gray-300 disabled:cursor-not-allowed p-2 text-center w-full text-sm font-semibold hover:bg-yellow-200"
                        wire:click="updateStatusBooking({{ $detailBookingIphones?->id }}, 'pending')">pending</button>
                </div>
                <div class="p-1"> <button {{ $detailBookingIphones?->status === 'confirmed' ? 'disabled' : '' }}
                        class="disabled:bg-gray-300 disabled:cursor-not-allowed p-2 text-center w-full text-sm font-semibold hover:bg-green-200"
                        wire:click="updateStatusBooking({{ $detailBookingIphones?->id }}, 'confirmed')">confirmed</button>
                </div>
                <div class="p-1"> <button {{ $detailBookingIphones?->status === 'returned' ? 'disabled' : '' }}
                        class="disabled:bg-gray-300 disabled:cursor-not-allowed p-2 text-center w-full text-sm font-semibold hover:bg-purple-200"
                        wire:click="updateStatusBooking({{ $detailBookingIphones?->id }}, 'returned')">returned</button>
                </div>
                <div class="p-1"> <button {{ $detailBookingIphones?->status === 'cancelled' ? 'disabled' : '' }}
                        class="disabled:bg-gray-300 disabled:cursor-not-allowed p-2 text-center w-full text-sm font-semibold hover:bg-red-300"
                        wire:click="updateStatusBooking({{ $detailBookingIphones?->id }}, 'cancelled')">cancelled</button>
                </div>
            </x-mary-dropdown>
        </div>
        <div class="text-sm text-gray-600 space-y-1">
            <p>Dibuat:
                <span class="font-mono">
                    {{ $detailBookingIphones?->created
                        ? \Carbon\Carbon::parse($detailBookingIphones->created)->translatedFormat('d M Y H:i')
                        : '-' }}
                </span>
            </p>
            <p>Kode: <span class="font-mono">{{ $detailBookingIphones->booking_code ?? '-' }}</span></p>
            {{-- Customer Info --}}
            <p>Nama: <span class="font-medium">{{ $detailBookingIphones->customer_name ?? '-' }}</span></p>
            <p>Telepon: <span class="font-mono">{{ $detailBookingIphones->customer_phone ?? '-' }}</span></p>
            <p>Email: <span class="font-mono">{{ $detailBookingIphones->customer_email ?? '-' }}</span></p>

            <p>Mulai: <span class="font-mono">
                    {{ $detailBookingIphones?->start_booking_date ? \Carbon\Carbon::parse($detailBookingIphones?->start_booking_date)->format('d M Y') . ' ' . $detailBookingIphones?->start_time : '-' }}
                </span></p>
            <p>Selesai: <span class="font-mono">
                    {{ $detailBookingIphones?->end_booking_date ? \Carbon\Carbon::parse($detailBookingIphones?->end_booking_date)->format('d M Y') . ' ' . $detailBookingIphones?->end_time : '-' }}
                </span></p>


        </div>
    </div>

    {{-- iPhone Info --}}
    <div class="bg-white rounded-xl shadow p-4">
        <h2 class="text-sm font-semibold mb-2 text-gray-700">iPhone</h2>
        @if ($detailBookingIphones?->iphone)
            <div class="text-sm text-gray-600 space-y-1">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('storage/' . $detailBookingIphones->iphone->gallery->image) }}" alt=""
                                 class="w-12 h-12 rounded-lg object-cover">
                    <p>{{ $detailBookingIphones->iphone->name }}</p>
                </div>
            </div>
        @else
            <p class="text-gray-400 italic">Tidak ada data iPhone</p>
        @endif
    </div>

    {{-- Payment Info --}}
    <div class="bg-white rounded-xl shadow p-4">
        <h2 class="text-sm font-semibold mb-2 text-gray-700">Metode Pembayaran</h2>
        @if ($detailBookingIphones?->payment)
            <div class="flex items-center gap-3">
                <img src="{{ asset('storage/' . $detailBookingIphones->payment->icon) }}"
                    class="w-12 h-12 rounded-lg object-cover" alt="">
                <div class="text-sm text-gray-600">
                    <p class="font-medium">{{ $detailBookingIphones->payment->name }}</p>
                    <p class="text-xs text-gray-500">{{ $detailBookingIphones->payment->description }}</p>
                </div>
            </div>
        @else
            <p class="text-gray-400 italic">Belum ada metode pembayaran</p>
        @endif
    </div>

    {{-- Revenue Info --}}
    <div class="bg-white rounded-xl shadow p-4">
        <h2 class="text-sm font-semibold mb-2 text-gray-700">Revenue</h2>
        @if ($detailBookingIphones?->revenue)
            <p class="text-sm text-gray-600">
                Rp {{ number_format($detailBookingIphones->revenue->amount, 0, ',', '.') }} <br>
                <span class="text-xs text-gray-500">
                    {{ \Carbon\Carbon::parse($detailBookingIphones->revenue?->created)->translatedFormat('d F Y H:i') }}
                </span>
            </p>
        @else
            <p class="text-gray-400 italic">Belum ada revenue</p>
        @endif
    </div>

    {{-- Pengembalian --}}
    <div class="bg-white rounded-xl shadow p-4 space-y-4">
        <h2 class="text-sm font-semibold text-gray-700">Pengembalian</h2>

        {{-- Form Pengembalian --}}
        <form wire:submit="save" class="space-y-2">
            <textarea wire:model="condition" class="w-full p-2 text-sm border rounded-lg" placeholder="Kondisi iPhone"
                wire:loading.attr="disabled"></textarea>
            <button type="submit"
                class="w-full py-2 bg-blue-600 text-white rounded-lg text-sm font-medium disabled:opacity-50"
                wire:loading.attr="disabled">
                Simpan Pengembalian
            </button>
        </form>

        {{-- Riwayat Pengembalian --}}
        <div class="space-y-3">
            @if (!empty($returns))
                @forelse($returns as $return)
                    <div class="p-3 bg-gray-50 rounded-lg text-sm">
                        <p class="font-medium">
                            {{ \Carbon\Carbon::parse($return->returned_at)->translatedFormat('d M Y H:i') }}</p>
                        <p><strong>Keterlambatan:</strong> {{ $return->calculatePenalty() > 0 ? 'Ya' : 'Tidak' }}
                        <p><strong>Denda:</strong> Rp {{ number_format($return->calculatePenalty(), 0, ',', '.') }}</p>
                        <p>Kondisi: {{ $return->condition ?? '-' }}</p>
                    </div>
                @empty
                    <p class="text-gray-400 italic">Belum ada pengembalian</p>
                @endforelse

            @endif
        </div>
    </div>

</div>
