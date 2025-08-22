<div class="max-w-xl mx-auto p-6 space-y-6">
    <h1 class="text-2xl font-bold text-center">🔎 Cek Status Booking</h1>

    <!-- Form -->
    <form wire:submit.prevent="checkBooking" class="space-y-4">
        <div>
            <label for="bookingCode" class="block text-sm font-medium">Kode Booking</label>
            <input type="text" id="bookingCode" wire:model="bookingCode" placeholder="Masukkan kode booking Anda SKY******"
                class="w-full border rounded-lg px-3 py-2 mt-1 focus:ring focus:ring-blue-300" autocomplete="off">
            @error('bookingCode')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg">
            Cek Status
        </button>
    </form>

    <!-- Hasil -->
    @if ($booking)
        <div class="mt-6 p-4 border rounded-lg bg-gray-50 shadow-sm space-y-2">
            <h2 class="font-bold text-lg">Detail Booking</h2>
            <p><span class="font-medium">Kode Booking:</span> {{ $booking->booking_code }}</p>
            <p><span class="font-medium">Nama:</span> {{ $booking->customer_name }}</p>
            <p><span class="font-medium">Nomor HP:</span> {{ $booking->customer_phone }}</p>
            <p><span class="font-medium">Perangkat:</span> {{ $booking->iphone->name }}</p>
            <p><span class="font-medium">Tanggal:</span> {{ $booking->requested_booking_date }}</p>
            <p><span class="font-medium">Waktu:</span> {{ $booking->requested_time }}</p>
            <p><span class="font-medium">Durasi:</span> {{ $booking->duration }} jam</p>
            <p><span class="font-medium">Harga:</span> Rp{{ number_format($booking->price, 0, ',', '.') }}</p>
            <p><span class="font-medium">Status:</span>
                <span
                    class="px-2 py-1 rounded text-white 
                    {{ $booking->status == 'pending' ? 'bg-yellow-500' : ($booking->status == 'confirmed' ? 'bg-green-500' : 'bg-red-500') }}">
                    {{ ucfirst($booking->status) }}
                </span>
            </p>
        </div>
        <h2 class="text-lg font-bold mt-6">Detail Pembayaran</h2>
        <div class="space-y-1">
            @if ($booking->payment)
                <p><strong>Metode:</strong> {{ $booking->payment->name }}</p>
                <img src="{{ asset('storage/' . $booking->payment->icon) }}" alt=""
                    class="w-48 h-w-48 object-cover rounded-md">
            @else
                <p><em>Belum ada data pembayaran</em></p>
            @endif
        </div>
        <button wire:click="confirmPayment({{ $booking->id }})"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:bg-blue-300" wire:loading.attr="disabled">
            Konfirmasi Pembayaran
        </button>
    @endif
</div>
