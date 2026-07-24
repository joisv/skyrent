<div class="max-w-4xl mx-auto p-4 space-y-6" @close-modal="show = false">
    <div class="bg-white rounded-xl shadow-sm p-5 sm:p-6 space-y-6" @success-save.window="show = false">

        {{-- HEADER --}}
        <div class="flex items-start justify-between gap-3 ">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">Detail Booking</h1>
                <p class="text-xs text-gray-500 font-mono">
                    {{ $detailBookingIphones?->booking_code ?? '-' }}
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-end space-y-2 sm:space-y-0 sm:space-x-2">
                <div>
                    <button
                        class="px-3 py-1.5 rounded-full text-xs sm:text-sm font-semibold bg-orange-500 text-white hover:bg-white hover:text-black hover:border-black border-2 ease-in duration-100"
                        @click="$dispatch('open-modal', 'tambah-durasi')">Tambah Durasi</button>
                </div>
                <x-mary-dropdown>
                    <x-slot:trigger>
                        <button
                            class="px-3 py-1.5 rounded-full text-xs sm:text-sm font-semibold
                        {{ $detailBookingIphones?->status === 'pending'
                            ? 'bg-yellow-500 text-white'
                            : ($detailBookingIphones?->status === 'confirmed'
                                ? 'bg-green-600 text-white'
                                : ($detailBookingIphones?->status === 'returned'
                                    ? 'bg-purple-600 text-white'
                                    : 'bg-red-600 text-white')) }}">
                            {{ ucfirst($detailBookingIphones?->status) }}
                        </button>
                    </x-slot:trigger>

                    <div class="p-1 space-y-1">
                        @foreach (['pending', 'confirmed', 'returned', 'cancelled'] as $status)
                            <button {{ $detailBookingIphones?->status === $status ? 'disabled' : '' }}
                                wire:click="updateStatusBooking({{ $detailBookingIphones?->id }}, '{{ $status }}')"
                                class="w-full px-3 py-2 text-left text-sm rounded-md
                                   hover:bg-gray-100 disabled:opacity-50">
                                {{ ucfirst($status) }}
                            </button>
                        @endforeach
                    </div>
                </x-mary-dropdown>
            </div>
        </div>

        {{-- PERANGKAT --}}
        @if ($detailBookingIphones?->iphone)
            <div x-data="{ copied: false }" class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                <img src="{{ asset('storage/' . $detailBookingIphones->iphone->gallery->image) }}"
                    class="w-14 h-14 sm:w-16 sm:h-16 rounded-lg object-cover">

                <div class="flex-1 space-y-1">
                    <p class="text-xs text-gray-500">Perangkat</p>
                    <p class="text-sm sm:text-base font-semibold text-gray-900">
                        {{ $detailBookingIphones->iphone->name }}
                    </p>

                    {{-- SERIAL NUMBER --}}
                    @if ($detailBookingIphones?->iphone?->serial_number)
                        <div x-data="{
                            copied: false,
                            copy(text) {
                                if (navigator.clipboard && window.isSecureContext) {
                                    navigator.clipboard.writeText(text);
                                } else {
                                    const textarea = document.createElement('textarea');
                                    textarea.value = text;
                                    textarea.style.position = 'fixed';
                                    textarea.style.opacity = '0';
                                    document.body.appendChild(textarea);
                                    textarea.select();
                                    document.execCommand('copy');
                                    document.body.removeChild(textarea);
                                }
                        
                                this.copied = true;
                                setTimeout(() => this.copied = false, 1500);
                            }
                        }" class="flex items-center gap-2">
                            <span class="text-xs text-gray-500">SN:</span>

                            <span @click="copy('{{ $detailBookingIphones->iphone->serial_number }}')"
                                class="relative font-mono text-sm text-gray-900 cursor-pointer select-all hover:text-blue-600">
                                {{ $detailBookingIphones->iphone->serial_number }}

                                {{-- TOOLTIP --}}
                                <span x-show="copied" x-transition
                                    class="absolute -top-6 left-1/2 -translate-x-1/2
                   px-2 py-1 rounded-md text-xs
                   bg-black text-white whitespace-nowrap">
                                    Copied
                                </span>
                            </span>
                        </div>
                    @endif
                </div>
                {{-- REVENUE DETAIL (MOBILE FRIENDLY) --}}
                @if ($detailBookingIphones?->revenue)
                    <div class="hidden sm:flex sm:flex-col">
                        <p class=" font-bold text-green-600">
                            Rp {{ number_format($sumRevenues, 0, ',', '.') }}
                        </p>
                        <p class="text-xs text-gray-400">
                            {{ \Carbon\Carbon::parse($detailBookingIphones->revenue->created)->translatedFormat('d M Y H:i') }}
                        </p>
                    </div>
                @endif
            </div>
        @endif


        {{-- INFO BOOKING --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">

            <div>
                <p class="text-xs text-gray-500">Nama Customer</p>
                <p class="font-medium text-gray-900">
                    {{ $detailBookingIphones->customer_name ?? '-' }}
                </p>
            </div>

            <div>
                <p class="text-xs text-gray-500">Alamat</p>
                <p class="font-medium text-gray-900">
                    {{ $detailBookingIphones->address ?? '-' }}
                </p>
            </div>

            <div>
                <p class="text-xs text-gray-500">Email</p>
                <p class="font-medium text-gray-900">
                    {{ $detailBookingIphones->customer_email ?? '-' }}
                </p>
            </div>

            <div>
                <p class="text-xs text-gray-500">Jaminan</p>
                <p class="font-medium text-gray-900">
                    {{ optional($detailBookingIphones)->kia
                        ? 'Kartu Identitas Anak'
                        : optional($detailBookingIphones)->jaminan_type ?? '-' }}
                </p>
            </div>

            <div>
                <p class="text-xs text-gray-500">Telepon</p>
                <div class="flex items-center">
                    <p class="font-mono text-gray-900">
                        {{ $detailBookingIphones->customer_phone ?? '-' }}
                    </p>
                    <button class="ml-2 text-xs text-blue-600 hover:underline"
                        @click="$dispatch('open-modal', 'edit-booking')">
                        edit
                    </button>
                </div>
            </div>

            <div>
                <p class="text-xs text-gray-500">Mulai</p>
                <p class="font-medium text-gray-900">
                    {{ \Carbon\Carbon::parse($detailBookingIphones?->start_booking_date)->format('d M Y') }}
                    {{ $detailBookingIphones?->start_time }}
                </p>
            </div>

            <div>
                <p class="text-xs text-gray-500">Selesai</p>
                <p class="font-medium text-gray-900">
                    {{ \Carbon\Carbon::parse($detailBookingIphones?->end_booking_date)->format('d M Y') }}
                    {{ $detailBookingIphones?->end_time }}
                </p>
            </div>

        </div>

        {{-- REVENUE DETAIL (MOBILE FRIENDLY) --}}
        @if ($detailBookingIphones?->revenue)
            <div class="sm:hidden border-t pt-4">
                <p class="text-xs text-gray-500">Revenue</p>
                <p class="text-lg font-bold text-green-600">
                    Rp {{ number_format($detailBookingIphones->revenue->amount, 0, ',', '.') }}
                </p>
                <p class="text-xs text-gray-400">
                    {{ \Carbon\Carbon::parse($detailBookingIphones->revenue->created)->translatedFormat('d M Y H:i') }}
                </p>
            </div>
        @endif

    </div>

    {{-- Pembayaran --}}
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-3">Pembayaran</h2>

        @if ($detailBookingIphones?->payment)
            <div class="flex items-center gap-4">
                <img src="{{ asset('storage/' . $detailBookingIphones->payment->icon) }}"
                    class="w-12 h-12 rounded-md object-cover">
                <div>
                    <p class="text-sm font-medium text-gray-800">
                        {{ $detailBookingIphones->payment->name }}
                    </p>
                    <p class="text-xs text-gray-500">
                        {{ $detailBookingIphones->payment->description }}
                    </p>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-400 italic">Belum ada metode pembayaran</p>
        @endif
    </div>

    {{-- Pengembalian --}}
    <div class="bg-white rounded-xl shadow-sm px-6 space-y-4">
        @php
            $nonLateStatuses = ['returned', 'canceled', 'pending'];
        @endphp

        <button
            class="w-full py-2.5 rounded-lg font-semibold text-sm
           bg-orange-500 text-white
           disabled:bg-orange-300 disabled:text-gray-500 disabled:cursor-not-allowed"
            wire:click="returnIphone" wire:loading.attr="disabled" @disabled(in_array($detailBookingIphones?->status, $nonLateStatuses))>
            Tandai Selesai
        </button>

        @forelse($returns ?? [] as $return)
            <div class="p-4 bg-gray-50 rounded-lg text-sm space-y-1">
                <p class="font-semibold text-gray-800">
                    {{ \Carbon\Carbon::parse($return->returned_at)->translatedFormat('d M Y H:i') }}
                </p>
                <p>Denda: <span class="font-medium">
                        Rp {{ number_format($return->penalty_fee, 0, ',', '.') }}
                    </span></p>
                <p class="text-gray-600">Kondisi: {{ $return->condition ?? '-' }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-400 italic">Belum ada data pengembalian</p>
        @endforelse
    </div>
    {{-- Detail Booking Edit --}}
    <x-modal name="edit-booking" :show="$errors->IsNotEmpty()" maxWidth="sm">
        <div class="p-3 space-y-3 flex flex-col justify-end" @modal-edit.window="show = false">
            <div x-data="{
                countryCode: @entangle('countryCode'),
                customerPhone: @entangle('customer_phone').live,
                countries: [
                    { code: '+62', name: 'Indonesia', flag: 'ID' },
                    { code: '+60', name: 'Malaysia', flag: 'MY' },
                    { code: '+65', name: 'Singapore', flag: 'SG' },
                    { code: '+66', name: 'Thailand', flag: 'TH' },
                    { code: '+63', name: 'Philippines', flag: 'PH' },
                    { code: '+95', name: 'Myanmar', flag: 'MM' },
                    { code: '+855', name: 'Cambodia', flag: 'KH' },
                    { code: '+856', name: 'Laos', flag: 'LA' },
                    { code: '+84', name: 'Vietnam', flag: 'VN' },
                    { code: '+673', name: 'Brunei', flag: 'BN' }
                ]
            }" class="flex gap-2">

                <!-- Dropdown Kode Negara -->
                <select x-model="countryCode"
                    class="w-[40%] bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white p-2.5">
                    <template x-for="country in countries" :key="country.code">
                        <option :value="country.code" x-text="country.flag + ' ' + country.code"></option>
                    </template>
                </select>

                <!-- Input Nomor -->
                <input type="tel" id="customer_phone" x-model="customerPhone"
                    @input="
        let raw = $event.target.value.replace(/[^0-9]/g, '');
        customerPhone = raw.match(/.{1,4}/g)?.join('-') || '';
    "
                    class="w-full bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg 
           focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 
           dark:text-white p-2.5"
                    placeholder="8123-4567-8901" />

            </div>
            <button class="bg-orange-500 text-white py-2 px-4 rounded-lg justify-end disabled:opacity-50"
                wire:click="updateDetailBooking" wire:loading.attr="disabled">simpan</button>
        </div>
    </x-modal>

    <x-modal name="tambah-durasi" :show="$errors->IsNotEmpty()" maxWidth="sm">
        <div class="p-3" @modal-durasi.window="show = false">
            <!-- Duration options -->
            <div class="space-y-3">
                <p class="text-sm font-semibold">Pilih Durasi</p>

                <div class="grid grid-cols-3 gap-2">
                    @if ($durations && $durations->count())
                        @foreach ($durations as $duration)
                            <button wire:click="$set('selectedDurationId', {{ $duration->id }})"
                                class="border rounded-lg p-3 text-center {{ $selectedDurationId === $duration->id ? 'bg-black text-white' : 'hover:bg-gray-100' }}">
                                <div class="font-semibold">{{ $duration->hours }} Jam</div>
                                @if ($duration->pivot)
                                    <div class="text-xs opacity-70"> Rp
                                        {{ number_format($duration->pivot->price, 0, ',', '.') }} </div>
                                @endif
                            </button>
                        @endforeach
                    @endif

                </div>
            </div>

            <!-- Multiplier -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Jumlah
                </label>

                <div class="flex items-center gap-3">
                    <!-- Decrease -->
                    <button wire:click="changeMultiplier('decrease')" @disabled($multiplier <= 1)
                        class="w-9 h-9 flex items-center justify-center
                   rounded-lg border border-gray-300
                   text-lg font-semibold
                   transition
                   {{ $multiplier <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100 active:scale-95' }}">
                        −
                    </button>

                    <!-- Value -->
                    <span class="min-w-[3rem] text-center text-lg font-semibold">
                        {{ $multiplier }}
                    </span>

                    <!-- Increase -->
                    <button wire:click="changeMultiplier('increase')"
                        class="w-9 h-9 flex items-center justify-center
                   rounded-lg border border-orange-500
                   hover:bg-orange-500 hover:text-white
                   active:scale-95 transition">
                        +
                    </button>
                </div>
            </div>

            <!-- Preview -->
            @if ($totalHours > 0)
                <div class="mt-4 rounded-lg border p-4 text-sm space-y-1">
                    <p>Total Jam: <strong>{{ $totalHours }}</strong></p>
                    <p>Selesai Baru: <strong>{{ $newEnd }}</strong></p>

                    @if (!$available)
                        <p class="text-red-600">❌ Tidak tersedia</p>
                    @endif
                </div>
            @endif

            <!-- Action -->
            <button wire:click="extend"
                class="mt-4 w-full bg-orange-500 text-white py-2 rounded-lg
        disabled:opacity-50"
                @disabled(!$available) wire:loading.attr="disabled">
                Tambah Durasi
            </button>

        </div>
    </x-modal>
    <x-modal name="tambah-denda" :show="$errors->IsNotEmpty()" maxWidth="lg">
        @if ($errors->any())
            <div>
                <div class="flex items-start gap-3">
                    <!-- Icon Error -->
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-red-500 dark:text-red-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M12 5a7 7 0 100 14a7 7 0 000-14z" />
                        </svg>
                    </div>
                    <!-- Text -->
                    <div class="flex-1">
                        <p class="font-medium text-gray-900 dark:text-gray-100">
                            Error
                        </p>
                        <ul class="mt-1 text-sm list-disc list-inside text-gray-700 dark:text-gray-300">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <!-- Close button -->
                    <button @click="show = false"
                        class="text-gray-400 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif
        <div class="bg-white w-full rounded-sm shadow-xl p-5" @success-save.window="show = false">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">
                    Status Pengembalian
                </h2>
                <button @click="open = false" class="text-gray-400 hover:text-gray-600">
                    ✕
                </button>
            </div>

            <!-- Status -->
            <div class="mb-4">
                <!-- contoh kondisi -->
                <div class="text-sm text-gray-500 mb-1">Status</div>

                @if ($is_late)
                    <!-- TELAT -->
                    <div
                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-600">
                        Telat
                    </div>
                @else
                    <!-- TIDAK TELAT -->
                    <div
                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-600">
                        Tepat Waktu
                    </div>
                @endif

            </div>

            <!-- Durasi -->
            @php
                $hours = floor($diff_hours);
                $minutes = round(($diff_hours - $hours) * 60);
            @endphp
            <div class="mb-4">
                <div class="text-sm text-gray-500 mb-1">Durasi Keterlambatan</div>
                <div class="text-gray-800 font-semibold">
                    {{ $hours }} jam {{ $minutes }} menit
                </div>
            </div>

            <!-- Input Denda -->
            <div class="mb-6" x-data="{
                raw: @entangle('penaltyFee').live,
            
                formatRupiah(value) {
                    if (!value) return 'Rp0';
                    return 'Rp' + new Intl.NumberFormat('id-ID').format(value);
                },
            
                parseNumber(value) {
                    return value.replace(/[^0-9]/g, '');
                }
            }">
                <label class="text-sm text-gray-500 mb-1 block">
                    Denda (Rp)
                </label>
                <input type="text" placeholder="Masukkan nominal" :value="formatRupiah(raw)"
                    @input="raw = parseNumber($event.target.value)" @focus="$event.target.select()"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
            </div>

            <div class="mb-4">

                <label class="text-sm text-gray-500">
                    Metode Pembayaran
                </label>
                @if (!empty($payments))
                    <x-mary-select wire:model.live="payment_method_id" :options="$payments"
                        placeholder="Metode pembayaran" placeholder-value="1" option-value="id"
                        option-label="name" />
                @endif

            </div>
            @if (!empty($payments))

                @if (optional($payments->firstWhere('id', $payment_method_id))->slug == 'cash')
                    <div class="mb-4">

                        <label class="font-semibold mb-2">
                            Saran Pembayaran
                        </label>

                        <div class="grid grid-cols-2 gap-2 mt-2">

                            @foreach ($cashSuggestions as $cash)
                                <button type="button" wire:click="$set('pay', {{ $cash['pay'] }})"
                                    class="p-1 ease-in rounded-sm {{ $pay == $cash['pay'] ? 'bg-orange-500 text-white ring-1 ring-gray-300' : 'text-center ring-1 ring-black hover:ring-orange-300' }}">

                                    <div class="font-bold">
                                        Rp {{ number_format($cash['pay'], 0, ',', '.') }}
                                    </div>

                                    <div class="text-xs opacity-70">

                                        Kembali
                                        Rp {{ number_format($cash['change'], 0, ',', '.') }}

                                    </div>

                                </button>
                            @endforeach

                        </div>

                    </div>


                @endif

                {{-- @if (optional($payments->firstWhere('id', $payment_method_id))->slug == 'cash')
                    <div class="mb-4">

                        <label class="text-sm text-gray-500">
                            Uang Diterima
                        </label>

                        <input type="number" wire:model.live="pay" class="w-full border rounded-lg p-2">

                    </div>
                @endif

                @if (optional($payments->firstWhere('id', $payment_method_id))->slug == 'cash')
                    <div class="mb-4">

                        <label class="text-sm text-gray-500">
                            Kembalian
                        </label>

                        <div class="text-lg font-bold text-green-600">

                            Rp {{ number_format($change, 0, ',', '.') }}

                        </div>

                    </div>
                @endif --}}
            @endif
            {{-- Catatan --}}
            {{-- <div class="mb-4">

                <label class="text-sm text-gray-500">
                    Catatan
                </label>

                <textarea wire:model="note" rows="3" class="w-full border rounded-lg"></textarea>

            </div> --}}

            <!-- Action -->
            <div class="flex justify-end gap-2">
                <button class="px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100" @click="show = false">
                    Batal
                </button>

                <button
                    class="px-4 py-2 rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition disabled:opacity-50"
                    wire:click="savePenaltyPayment" wire:loading.attr="disabled">
                    Simpan
                </button>
            </div>
        </div>

    </x-modal>
</div>
