<div class="max-w-5xl mx-auto p-6 bg-white shadow-lg rounded-2xl space-y-6">

    {{-- Header Booking --}}
    <div class="border-b pb-4">
        <h1 class="text-2xl font-bold text-gray-800">Detail Booking</h1>
        <p class="text-gray-600">Kode: <span class="font-mono">{{ $detailBookingIphones->booking_code ?? '' }}</span></p>
        <p class="text-gray-600">Mulai: <span class="font-mono">{{  $detailBookingIphones?->start_booking_date ? \Carbon\Carbon::parse( $detailBookingIphones?->start_booking_date)->format('d M Y') . ' ' . $detailBookingIphones?->start_time : '-' }}</span></p>
        <p class="text-gray-600">Selesai: <span class="font-mono">{{ $detailBookingIphones?->end_booking_date ? \Carbon\Carbon::parse($detailBookingIphones?->end_booking_date)->format('d M Y') . ' ' . $detailBookingIphones?->end_time : '-' }}</span></p>
        <p class="text-gray-600">Status:
            <span
                class="px-2 py-1 rounded text-sm 
        {{ optional($detailBookingIphones)->status === 'completed'
            ? 'bg-green-100 text-green-700'
            : (optional($detailBookingIphones)->status === 'active'
                ? 'bg-blue-100 text-blue-700'
                : 'bg-gray-100 text-gray-700') }}">
                {{ ucfirst(optional($detailBookingIphones)->status) ?? '-' }}
            </span>
        </p>
    </div>

    {{-- iPhone Info --}}
    <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-2">iPhone</h2>
        @if ($detailBookingIphones?->iphone)
            <div class="p-4 bg-gray-50 rounded-lg border">
                <p><strong>Nama:</strong> {{ $detailBookingIphones->iphone->name }}</p>
                <p><strong>Deskripsi:</strong> {{ $detailBookingIphones->iphone->description }}</p>
                <p><strong>Slug:</strong> {{ $detailBookingIphones->iphone->slug }}</p>
            </div>
        @else
            <p class="text-gray-500 italic">Tidak ada data iPhone</p>
        @endif
    </div>

    {{-- Payment Info --}}
    <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-2">Metode Pembayaran</h2>
        @if ($detailBookingIphones?->payment)
            <div class="p-4 bg-gray-50 rounded-lg border flex items-center gap-3">
                @if ($detailBookingIphones->payment->icon)
                    <img src="{{ $detailBookingIphones->payment->icon }}" alt="icon" class="w-8 h-8">
                @endif
                <div>
                    <p><strong>Nama:</strong> {{ $detailBookingIphones->payment->name }}</p>
                    <p><strong>Deskripsi:</strong> {{ $detailBookingIphones->payment->description }}</p>
                    <p><strong>Status:</strong> {{ $detailBookingIphones->payment->is_active ? 'Aktif' : 'Nonaktif' }}
                    </p>
                </div>
            </div>
        @else
            <p class="text-gray-500 italic">Belum ada metode pembayaran</p>
        @endif
    </div>

    {{-- Revenue Info --}}
    <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-2">Pendapatan (Revenue)</h2>
        @if ($detailBookingIphones?->revenue)
            <div class="p-4 bg-gray-50 rounded-lg border">
                <p><strong>Amount:</strong> Rp
                    {{ number_format($detailBookingIphones?->revenue->amount, 0, ',', '.') }}
                </p>
                <p><strong>Tanggal:</strong>
                    {{ \Carbon\Carbon::parse($detailBookingIphones?->revenue?->created)->translatedFormat('d F Y H:i') }}
                </p>
            </div>
        @else
            <p class="text-gray-500 italic">Belum ada revenue</p>
        @endif
    </div>

    {{-- Returns Info --}}
    <div>
        <h2 class="text-xl font-semibold text-gray-700 mb-2">Pengembalian</h2>
        @if ($detailBookingIphones?->returns->count())
            <div class="space-y-3">
                @foreach ($detailBookingIphones->returns as $return)
                    <div class="p-4 bg-gray-50 rounded-lg border">
                        <p><strong>Dikembalikan:</strong> {{ \Carbon\Carbon::parse($return->returned_at)->format('d M Y H:i') }}</p>
                        <p><strong>Kondisi:</strong> {{ $return->condition ?? '-' }}</p>
                        <p><strong>Denda:</strong>  Rp {{ number_format($return->calculatePenalty(), 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-gray-500 italic">Belum ada data pengembalian</p>
        @endif
    </div>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Pengembalian iPhone</h2>

        {{-- Form pengembalian --}}
        <form wire:submit="save">
            <div class="p-4 bg-gray-100 rounded-lg">
                <label class="block mb-2 font-medium">Kondisi iPhone</label>
                <textarea wire:model="condition" class="w-full p-2 border rounded"></textarea>
    
                <button type="submit" class="mt-3 px-4 py-2 bg-blue-600 text-white rounded">
                    Simpan Pengembalian
                </button>
            </div>
        </form>

        {{-- Riwayat pengembalian --}}
        <div class="p-4 bg-gray-50 rounded-lg border">
            <h3 class="font-semibold mb-2">Riwayat</h3>
            @if (!empty($returns))
                @forelse($returns as $return)
                    <div class="mb-3 p-3 bg-white rounded shadow">
                        <p><strong>Dikembalikan:</strong>
                            {{ Carbon\Carbon::parse($return->returned_at)->translatedFormat('d F Y H:i') }}</p>
                        <p><strong>Keterlambatan:</strong>
                             {{ $return->calculatePenalty() > 0 ? 'Ya' : 'Tidak' }}
                        <p><strong>Denda:</strong>
                            Rp {{ number_format($return->calculatePenalty(), 0, ',', '.') }}</p>
                        <p><strong>Kondisi:</strong>
                            {{ $return->condition ?? '-' }}</p>
                    </div>
                @empty
                    <p class="text-gray-500 italic">Belum ada pengembalian</p>
                @endforelse
            @endif
        </div>
    </div>
</div>
