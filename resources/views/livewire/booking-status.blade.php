<div class="max-w-4xl mx-auto space-y-6">
    <div class="w-full ">
        <div class="flex items-center justify-center">
            <span class="text-5xl font-semibold">SKYRENTAL</span>
        </div>
        <div>

        </div>
    </div>
    <form wire:submit.prevent="checkBooking" class="space-y-6 shadow-2xl p-6">
        {{-- Judul --}}
        <h2 class="text-xl font-bold text-blue-800 border-b border-gray-300 pb-2">
            Cek Status Booking
        </h2>

        {{-- Input Kode Booking --}}
        <div>
            <label for="bookingCode" class="block text-sm font-semibold text-blue-800">Kode Booking</label>
            <input type="text" id="bookingCode" wire:model="bookingCode"
                placeholder="Masukkan kode booking Anda (contoh: SKY123456)"
                class="w-full border border-gray-400 px-4 py-2 mt-1 
                   focus:outline-none focus:ring-2 focus:ring-blue-300 
                   placeholder:text-gray-400 bg-white"
                autocomplete="off">
            @error('bookingCode')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>

        {{-- Tombol --}}
        <button type="submit"
            class="w-full py-2 bg-blue-800 hover:bg-blue-900 text-white 
               font-semibold shadow transition sm:text-lg">
            Cek Status
        </button>

        {{-- Note kecil --}}
        <p class="text-xs text-blue-800 italic">
            *Gunakan kode booking yang dikirim melalui WhatsApp/Email.
        </p>
    </form>
    @if ($booking)
        <div class="px-4 py-2 flex flex-col justify-between">
            {{-- Header --}}
            <div class="flex justify-between items-start mb-8">
                <div></div>
                <div class="text-right text-sm text-blue-800">
                    <p>{{ $booking->booking_code ?? '-' }}</p>
                    <p>{{ \Carbon\Carbon::parse($booking->created)->format('d M Y') }}</p>
                    <div class="flex items-center justify-center gap-2 mt-1">
                        @if ($booking->status == 'pending')
                            <span
                                class="px-3 py-1 rounded bg-yellow-500 text-white font-medium flex items-center gap-1">
                                <x-heroicon-o-clock class="w-4 h-4" /> Menunggu Pembayaran
                            </span>
                        @elseif($booking->status == 'confirmed')
                            <span class="px-3 py-1 rounded bg-green-500 text-white font-medium flex items-center gap-1">
                                <x-heroicon-o-check-circle class="w-4 h-4" /> Dikonfirmasi
                            </span>
                        @elseif($booking->status == 'returned')
                            <span class="px-3 py-1 rounded bg-blue-500 text-white font-medium flex items-center gap-1">
                                <x-heroicon-o-arrow-uturn-left class="w-4 h-4" /> Dikembalikan
                            </span>
                        @elseif($booking->status == 'cancelled')
                            <span class="px-3 py-1 rounded bg-red-500 text-white font-medium flex items-center gap-1">
                                <x-heroicon-o-x-circle class="w-4 h-4" /> Dibatalkan
                            </span>
                        @endif
                    </div>

                </div>
            </div>

            {{-- Billed To --}}
            <div class="mb-6">
                <h2 class="text-sm font-bold text-blue-800">BILLED TO</h2>
                <p class="text-blue-800">{{ $booking->customer_name ?? '-' }}</p>
                <p class="text-blue-800">{{ $booking->customer_email ?? '-' }}</p>
                <p class="text-blue-800">{{ $booking->customer_phone ?? '-' }}</p>
            </div>

            {{-- Tabel --}}
            <table class="w-full border-t border-b border-gray-400 text-sm mb-8">
                <thead>
                    <tr class="text-blue-800 font-bold">
                        <th class="text-left py-2">DESKRIPSI</th>
                        <th class="text-right py-2">DETAIL</th>
                    </tr>
                </thead>
                <tbody>

                    <tr class="text-blue-800">
                        <td class="py-2">Mulai Booking</td>
                        <td class="text-right">
                            {{ \Carbon\Carbon::parse($booking->start_booking_date)->format('d M Y') }}
                            {{ $booking->start_time }}
                        </td>
                    </tr>
                    <tr class="text-blue-800">
                        <td class="py-2">Selesai Booking</td>
                        <td class="text-right">
                            {{ \Carbon\Carbon::parse($booking->end_booking_date)->format('d M Y') }}
                            {{ $booking->end_time }}
                        </td>
                    </tr>
                    <tr class="text-blue-800">
                        <td class="py-2">Durasi</td>
                        <td class="text-right">{{ $booking->duration }} Jam</td>
                    </tr>
                    <tr class="text-blue-800">
                        <td class="py-2">Pembayaran</td>
                        <td class="text-right">{{ $booking->payment->name }}</td>
                    </tr>
                    <tr class="font-bold text-blue-800 border-t border-gray-400">
                        <td class="py-2">TOTAL</td>
                        <td class="text-right">Rp {{ number_format($booking->price, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>

            {{-- Payment --}}
            <div class="flex justify-between items-start text-sm">
                <div class="space-y-2">
                    <h2 class="font-bold text-blue-800">PAYMENTS</h2>
                    <p class="text-blue-800">Scan QRIS pembayaran otomatis.</p>
                    <a href="{{ route('contacts') }}" wire:navigate>
                        <h2 class="font-bold text-orange-600 mt-4">PERTANYAAN?</h2>
                    </a>
                    <p class="text-blue-800">
                        Hubungi kami di <br>
                        <span class="text-orange-600">gwennrepair@gmail.com</span>
                    </p>
                </div>

                {{-- QR Code --}}
                @if ($booking->payment)
                    <img src="{{ asset('storage/' . $booking->payment->icon) }}" alt=""
                        class="w-32 h-w-32 sm:w-44 sm:h-44 object-cover rounded-md"
                        @click="() => $dispatch('open-modal', 'payment')">
                @else
                    <p><em>Belum ada data pembayaran</em></p>
                @endif
            </div>
            {{-- Tombol Konfirmasi Pembayaran --}}
            <div class="mt-1">
                <button wire:click="confirmPayment({{ $booking->id }})"
                    class="w-full sm:w-auto px-6 py-3 bg-gray-900 hover:bg-white hover:text-black 
               text-white font-semibold shadow-xl 
               transition duration-200 ease-in-out flex items-center justify-center gap-2">
                    <svg width="24px" height="24px" viewBox="0 0 24 24" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                        <g id="SVGRepo_iconCarrier">
                            <circle cx="12" cy="12" r="10" stroke="#ffffff" stroke-width="1.5"></circle>
                            <path d="M8.5 12.5L10.5 14.5L15.5 9.5" stroke="#ffffff" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round"></path>
                        </g>
                    </svg>
                    Konfirmasi Pembayaran
                </button>
                <p class="text-xs text-gray-500 mt-2 italic">
                    *Dengan menekan tombol ini, Anda menyatakan sudah melakukan pembayaran.
                </p>
            </div>
        </div>
        @if ($booking->payment)
            <x-modal name="payment" :show="$errors->isNotEmpty()">
                <img src="{{ asset('storage/' . $booking->payment->icon) }}" alt=""
                        class="w-full h-full object-cover " >
            </x-modal>
        @endif  
    @endif
</div>
